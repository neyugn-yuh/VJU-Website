<?php

namespace App\Domain\Migration\WordPress;

use App\Domain\Content\ContentService;
use App\Domain\Content\ContentStatus;
use App\Domain\Content\ContentType;
use App\Domain\Content\TaxonomyService;
use App\Domain\Media\MediaService;
use App\Domain\Menu\MenuService;
use App\Domain\Migration\WordPress\Sources\WordPressSource;
use App\Domain\Migration\WordPress\Transform\HtmlTransformer;
use App\Domain\Migration\WordPress\Transform\TransformContext;
use App\Domain\SEO\RedirectResolver;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Comment;
use App\Models\Content;
use App\Models\ContentTranslation;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Redirect;
use App\Models\Tag;
use App\Models\User;
use App\Models\WpMigrationMap;
use App\Models\WpMigrationRun;
use App\Support\Locales;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Repeatable, idempotent WordPress import. Every record goes through the CMS domain services,
 * is keyed by its WordPress identity in wp_migration_map, and is skipped when its checksum is
 * unchanged. Recoverable problems are logged per record; source failures abort the run.
 */
class WordPressImporter
{
    public const TYPES = ['users', 'taxonomies', 'media', 'pages', 'posts', 'structured', 'comments', 'menus', 'redirects', 'seo'];

    private const POST_TYPES = [
        'pages' => ['page'],
        'posts' => ['post'],
        'structured' => ['download-documents', 'notification', 'tuition-fees', 'current-opportunitie'],
        'seo' => ['page', 'post', 'download-documents', 'notification', 'tuition-fees', 'current-opportunitie'],
    ];

    private const STATUS_MAP = [
        'publish' => ContentStatus::Published,
        'future' => ContentStatus::Scheduled,
        'private' => ContentStatus::Private,
        'pending' => ContentStatus::PendingReview,
        'draft' => ContentStatus::Draft,
    ];

    /** Higher wins when translations of one content disagree. */
    private const STATUS_PRIORITY = ['draft' => 1, 'pending_review' => 2, 'private' => 3, 'scheduled' => 4, 'published' => 5];

    private WordPressSource $source;

    private bool $dryRun = false;

    private bool $force = false;

    private array $stats = [];

    private ?Closure $progress = null;

    /** @var array<string, int>|null normalized legacy media URL -> media id */
    private ?array $mediaIndex = null;

    public function __construct(
        private readonly ContentService $contents,
        private readonly TaxonomyService $taxonomy,
        private readonly MediaService $media,
        private readonly MenuService $menus,
        private readonly HtmlTransformer $transformer,
        private readonly MigrationMap $map,
    ) {}

    /**
     * @param  array{since?: ?string, id?: ?int, limit?: ?int, locale?: ?string}  $filters
     * @return array<string, int> stats
     */
    public function run(WordPressSource $source, string $type, array $filters = [], bool $dryRun = false, bool $force = false, ?Closure $progress = null): array
    {
        $this->source = $source;
        $this->dryRun = $dryRun;
        $this->force = $force;
        $this->progress = $progress;
        $this->stats = ['imported' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0, 'warnings' => 0];

        if (($filters['since'] ?? null) === 'last-run') {
            $filters['since'] = WpMigrationRun::where('type', $type)->where('status', 'completed')->where('dry_run', false)->latest('started_at')->value('started_at')?->toDateTimeString();
        }

        $run = WpMigrationRun::create([
            'type' => $type, 'source' => $source->name(), 'dry_run' => $dryRun,
            'options' => $filters, 'status' => 'running', 'started_at' => now(),
        ]);

        try {
            match ($type) {
                'users' => $this->each('user', $source->users(), fn ($u) => $this->importUser($u)),
                'taxonomies' => $this->importTaxonomies(),
                'media' => $this->each('attachment', $source->media($filters), fn ($m) => $this->importMedia($m)),
                'pages', 'posts', 'structured', 'seo' => $this->importPostTypes(self::POST_TYPES[$type], $filters, seoOnly: $type === 'seo'),
                'comments' => $this->each('comment', $source->comments($filters), fn ($c) => $this->importComment($c)),
                'menus' => $this->each('menu', $source->menus(), fn ($m) => $this->importMenu($m)),
                'redirects' => $this->each('redirect', $source->redirects(), fn ($r) => $this->importRedirect($r)),
            };

            $run->update(['status' => 'completed', 'stats' => $this->stats, 'finished_at' => now()]);
        } catch (Throwable $e) {
            // Fatal: the source is unavailable or broken. Stop the batch.
            $run->update(['status' => 'failed', 'stats' => $this->stats, 'error' => $e->getMessage(), 'finished_at' => now()]);
            Log::channel('migration')->error("wp:import {$type} failed", ['error' => $e->getMessage()]);

            throw $e;
        }

        return $this->stats;
    }

    // ------------------------------------------------------------------ record loop

    private function each(string $sourceType, iterable $records, Closure $import): void
    {
        foreach ($records as $record) {
            $this->one($sourceType, $record, $import);
        }
    }

    /** One record = one transaction; dry runs roll back. Failures are recorded and the batch continues. */
    private function one(string $sourceType, array $record, Closure $import): void
    {
        $id = $record['id'];
        DB::beginTransaction();

        try {
            $result = $import($record) ?? 'imported';
            $this->dryRun ? DB::rollBack() : DB::commit();
            $this->stats[$result] = ($this->stats[$result] ?? 0) + 1;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->stats['failed']++;
            if (! $this->dryRun) {
                $this->map->record($sourceType, $id, ['status' => 'failed', 'error' => Str::limit($e->getMessage(), 2000), 'locale' => $record['lang'] ?? null, 'source_url' => $record['link'] ?? $record['url'] ?? null]);
            }
            Log::channel('migration')->warning("{$sourceType} {$id} failed", ['error' => $e->getMessage()]);
        }

        if ($this->dryRun) {
            $this->map->forget();
            $this->mediaIndex = null;
        }

        if ($this->progress) {
            ($this->progress)($sourceType, $id, $this->stats);
        }
    }

    private function checksum(array $record): string
    {
        return hash('sha256', json_encode(Arr::except($record, ['count']), JSON_UNESCAPED_UNICODE));
    }

    private function unchanged(string $type, int|string $id, string $checksum): bool
    {
        $row = $this->map->find($type, $id);

        return ! $this->force && $row && $row->status === 'success' && $row->checksum === $checksum;
    }

    private function success(string $type, int|string $id, array $record, string $targetType, int $targetId, string $checksum, array $warnings = [], ?string $targetUrl = null, ?string $locale = null): void
    {
        $this->stats['warnings'] += count($warnings);
        foreach ($warnings as $w) {
            Log::channel('migration')->notice("{$type} {$id}: {$w}");
        }

        $this->map->record($type, $id, [
            'source_parent_id' => isset($record['parent']) && $record['parent'] ? (string) $record['parent'] : null,
            'locale' => $locale ?? ($record['lang'] ?? null),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'source_url' => $record['link'] ?? $record['url'] ?? null,
            'target_url' => $targetUrl,
            'status' => 'success',
            'checksum' => $checksum,
            'warnings' => $warnings ?: null,
            'error' => null,
        ]);
    }

    private function locale(?string $lang): ?string
    {
        $lang ??= Locales::default();

        return Locales::isSupported($lang) ? $lang : null;
    }

    private function date(?string $gmt): ?Carbon
    {
        return $gmt && ! str_starts_with($gmt, '0000') ? Carbon::parse($gmt, 'UTC')->setTimezone(config('app.timezone')) : null;
    }

    /** Legacy URL -> redirect when the CMS URL differs (loops/chains prevented by RedirectResolver). */
    private function redirectIfMoved(?string $legacyUrl, ?string $target): void
    {
        if (! $legacyUrl || ! $target) {
            return;
        }
        $path = (string) parse_url($legacyUrl, PHP_URL_PATH);
        // Never redirect a language homepage (the WP front page's link is "/", "/en/"...).
        if (in_array(RedirectResolver::normalize($path), array_map(fn ($l) => RedirectResolver::normalize(Locales::path($l)), Locales::codes()), true)) {
            return;
        }
        // Never shadow a URL that is live in the CMS with a redirect.
        [$locale, $rest] = Locales::split(rawurldecode($path));
        $live = ContentTranslation::where('locale', $locale)->where('path', $rest)->exists()
            || CategoryTranslation::where('locale', $locale)->where('path', $rest)->exists();
        if (! $live && RedirectResolver::normalize($path) !== RedirectResolver::normalize($target)) {
            RedirectResolver::record($path, $target, 'migration');
        }
    }

    // ------------------------------------------------------------------ users

    private function importUser(array $u): string
    {
        $checksum = $this->checksum($u);
        if ($this->unchanged('user', $u['id'], $checksum)) {
            return 'unchanged';
        }

        $user = User::where('source_system', 'wordpress')->where('source_id', (string) $u['id'])->first()
            ?? (($u['email'] ?? null) ? User::where('email', $u['email'])->first() : null);
        $isNew = ! $user;

        $user ??= new User;
        $user->name = $u['name'];
        if ($isNew) {
            $user->email = $u['email'] ?: "wp-user-{$u['id']}@migrated.invalid";
            // Imported accounts get no role and cannot sign in until an Admin grants access.
            $user->forceFill(['is_active' => false]);
        }
        $user->forceFill(['source_system' => 'wordpress', 'source_id' => (string) $u['id']])->save();

        $this->success('user', $u['id'], $u, 'user', $user->id, $checksum);

        return $isNew ? 'imported' : 'updated';
    }

    /** REST cannot list users: authors become inactive placeholders an Admin can later rename/merge. */
    private function author(int $wpId): ?int
    {
        if ($wpId <= 0) {
            return null;
        }
        if ($id = $this->map->targetId('user', $wpId)) {
            return $id;
        }

        $user = User::where('source_system', 'wordpress')->where('source_id', (string) $wpId)->first();
        if (! $user) {
            $user = new User(['name' => "WordPress author #{$wpId}"]);
            $user->email = "wp-user-{$wpId}@migrated.invalid";
            $user->forceFill(['is_active' => false, 'source_system' => 'wordpress', 'source_id' => (string) $wpId])->save();
        }
        $this->map->record('user', $wpId, ['status' => 'success', 'target_type' => 'user', 'target_id' => $user->id, 'warnings' => ['placeholder_author']]);

        return $user->id;
    }

    // ------------------------------------------------------------------ taxonomies

    private function importTaxonomies(): void
    {
        foreach (['category', 'post_tag'] as $taxonomy) {
            $terms = collect($this->source->terms($taxonomy))->keyBy('id');
            // Parents first, so hierarchical paths can be built.
            $depth = function (array $t) use ($terms, &$depth, $taxonomy) {
                return $t['parent'] && $terms->has($t['parent']) && $taxonomy === 'category' ? 1 + $depth($terms[$t['parent']]) : 0;
            };
            foreach ($terms->sortBy(fn ($t) => [$depth($t), $t['id']]) as $term) {
                $this->one($taxonomy, $term, fn ($t) => $taxonomy === 'category' ? $this->importCategory($t) : $this->importTag($t));
            }
        }
    }

    private function importCategory(array $t): string
    {
        $checksum = $this->checksum($t);
        if ($this->unchanged('category', $t['id'], $checksum)) {
            return 'unchanged';
        }
        $locale = $this->locale($t['lang']);
        if (! $locale) {
            $this->map->record('category', $t['id'], ['status' => 'skipped', 'error' => "unsupported language {$t['lang']}"]);

            return 'skipped';
        }

        $group = $t['translations'] ?: [$locale => $t['id']];
        $categoryId = $this->map->groupTarget(['category'], array_values($group));
        $category = $categoryId ? Category::find($categoryId) : null;
        $isNew = ! $category;
        $category ??= (new Category)->forceFill(['source_system' => 'wordpress', 'source_id' => (string) min($group), 'slug' => $t['slug']]);

        $parentId = $this->map->targetId('category', $t['parent']);
        $this->taxonomy->saveCategory($category, $isNew ? $parentId : ($parentId ?? $category->parent_id), [
            $locale => ['name' => $t['name'], 'slug' => $t['slug'], 'description' => $t['description']],
        ], redirects: false);

        $url = CategoryTranslation::where('category_id', $category->id)->where('locale', $locale)->first()?->url();
        $this->redirectIfMoved($t['link'] ?? null, $url);
        $this->success('category', $t['id'], $t, 'category', $category->id, $checksum, [], $url, $locale);

        return $isNew ? 'imported' : 'updated';
    }

    private function importTag(array $t): string
    {
        $checksum = $this->checksum($t);
        if ($this->unchanged('post_tag', $t['id'], $checksum)) {
            return 'unchanged';
        }
        $locale = $this->locale($t['lang']) ?? Locales::default();
        $group = $t['translations'] ?: [$locale => $t['id']];
        $tagId = $this->map->groupTarget(['post_tag'], array_values($group));
        $tag = $tagId ? Tag::find($tagId) : null;
        $isNew = ! $tag;
        $tag ??= (new Tag)->forceFill(['source_system' => 'wordpress', 'source_id' => (string) min($group), 'slug' => $t['slug']]);

        $this->taxonomy->saveTag($tag, [$locale => ['name' => $t['name'], 'slug' => $t['slug']]]);
        $url = $tag->translations()->where('locale', $locale)->first()?->url();
        $this->redirectIfMoved($t['link'] ?? null, $url);
        $this->success('post_tag', $t['id'], $t, 'tag', $tag->id, $checksum, [], $url, $locale);

        return $isNew ? 'imported' : 'updated';
    }

    // ------------------------------------------------------------------ media

    private function importMedia(array $m): string
    {
        $checksum = $this->checksum($m);
        if ($this->unchanged('attachment', $m['id'], $checksum)) {
            return 'unchanged';
        }

        $existing = Media::withTrashed()->where('source_system', 'wordpress')->where('source_id', (string) $m['id'])->first();
        if ($existing) {
            $existing->fill(array_filter(['title' => $m['title'], 'alt' => $m['alt'], 'caption' => $m['caption']], fn ($v) => $v !== null && $v !== ''))->save();
            $this->success('attachment', $m['id'], $m, 'media', $existing->id, $checksum, [], $existing->url());

            return 'updated';
        }

        if ($this->dryRun) {
            $ok = ($m['file'] ?? null) ? is_file($m['file']) : Http::timeout(30)->head($m['url'])->successful();
            if (! $ok) {
                throw new \RuntimeException("missing_media: {$m['url']}");
            }

            return 'imported';
        }

        $media = $this->storeLegacyFile($m['url'], $m['file'] ?? null, [
            'title' => $m['title'] ?: null,
            'alt' => $m['alt'] ?: null,
            'caption' => $m['caption'],
            'description' => $m['description'] ?? null,
            'source_system' => 'wordpress',
            'source_id' => (string) $m['id'],
            'source_url' => $m['url'],
            'created_at' => $this->date($m['date_gmt'] ?? null),
        ]);

        if (! $media) {
            throw new \RuntimeException("missing_media: {$m['url']}");
        }

        $warnings = $this->media->lastWasDuplicate ? ["duplicate_of_media:{$media->id}"] : [];
        $this->success('attachment', $m['id'], $m, 'media', $media->id, $checksum, $warnings, $media->url());
        $this->indexMedia($m['url'], $media->id);

        return 'imported';
    }

    private function storeLegacyFile(string $url, ?string $localFile, array $attributes): ?Media
    {
        $tmpDir = null;
        try {
            if ($localFile && is_file($localFile)) {
                $path = $localFile;
            } else {
                // Keep the original file name: MediaService derives the stored name from it.
                $tmpDir = sys_get_temp_dir().'/wpm-'.Str::random(12);
                mkdir($tmpDir);
                $path = $tmpDir.'/'.rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));
                $response = Http::timeout(180)->retry(2, 2000, throw: false)->withOptions(['sink' => $path])->get($url);
                if (! $response->successful()) {
                    return null;
                }
            }

            // Files found only in content keep their original upload month (…/uploads/2024/12/…).
            if (empty($attributes['created_at']) && preg_match('#/wp-content/uploads/(\d{4})/(\d{2})/#', $url, $m)) {
                $attributes['created_at'] = Carbon::create((int) $m[1], (int) $m[2], 1);
            }
            $media = $this->media->store($path, $attributes);

            // Old upload URLs (linked from other sites, social posts, PDFs) keep working.
            $legacyPath = (string) parse_url($url, PHP_URL_PATH);
            if (str_contains($legacyPath, '/wp-content/uploads/')) {
                RedirectResolver::record($legacyPath, $media->url(), 'migration');
            }

            return $media;
        } catch (ValidationException $e) {
            Log::channel('migration')->notice("media rejected: {$url}", ['reason' => collect($e->errors())->flatten()->first()]);

            return null;
        } finally {
            if ($tmpDir && is_dir($tmpDir)) {
                array_map('unlink', glob($tmpDir.'/*') ?: []);
                @rmdir($tmpDir);
            }
        }
    }

    /** Legacy uploads URL (any size variant) -> CMS media URL; imports unknown uploads on the fly. */
    private function resolveMediaUrl(string $url, TransformContext $ctx): ?string
    {
        $id = $this->mediaIdForUrl($url);

        if (! $id && str_contains($url, '/wp-content/uploads/') && ! $this->dryRun) {
            $key = sha1($url);
            $id = $this->map->targetId('attachment_url', $key);
            if (! $id) {
                $media = $this->storeLegacyFile($url, null, ['source_url' => $url]);
                if ($media) {
                    $ctx->warn('media_imported_from_content', basename($url));
                    $this->map->record('attachment_url', $key, ['status' => 'success', 'target_type' => 'media', 'target_id' => $media->id, 'source_url' => $url, 'target_url' => $media->url()]);
                    $this->indexMedia($url, $media->id);
                    $id = $media->id;
                }
            }
        }

        if (! $id) {
            return null;
        }

        $media = Media::find($id);

        // Content referenced a resized copy: serve our web-sized derivative instead of the original.
        return $media ? ($media->isImage() ? $media->derivativeUrl('web') : $media->url()) : null;
    }

    private function mediaIdForUrl(string $url): ?int
    {
        if ($this->mediaIndex === null) {
            $this->mediaIndex = [];
            Media::whereNotNull('source_url')->select(['id', 'source_url'])->orderBy('id')->each(fn ($m) => $this->indexMedia($m->source_url, $m->id));
        }

        foreach (self::urlVariants($url) as $candidate) {
            if (isset($this->mediaIndex[$candidate])) {
                return $this->mediaIndex[$candidate];
            }
        }

        return null;
    }

    private function indexMedia(string $url, int $id): void
    {
        if ($this->mediaIndex !== null) {
            foreach (self::urlVariants($url) as $candidate) {
                $this->mediaIndex[$candidate] ??= $id;
            }
        }
    }

    /** "…/2024/12/photo-1024x768.jpg" and "…/photo-scaled.jpg" all identify "…/2024/12/photo.jpg". */
    public static function urlVariants(string $url): array
    {
        $path = strtolower(rawurldecode((string) parse_url($url, PHP_URL_PATH)));
        $path = substr($path, (int) strpos($path, '/wp-content/uploads/'));
        $base = preg_replace('/-(\d+x\d+|scaled|rotated|e\d{10,})(?=\.\w+$)/', '', $path);

        return array_values(array_unique([$path, $base]));
    }

    // ------------------------------------------------------------------ posts / pages / structured

    private function importPostTypes(array $wpTypes, array $filters, bool $seoOnly): void
    {
        foreach ($wpTypes as $wpType) {
            $records = $this->source->posts($wpType, $filters);

            if ($wpType === 'page' && ! $seoOnly) {
                // Parents before children so hierarchical paths resolve.
                $records = collect($records)->keyBy('id');
                $depth = function (array $p) use ($records, &$depth) {
                    return $p['parent'] && $records->has($p['parent']) ? 1 + $depth($records[$p['parent']]) : 0;
                };
                $records = $records->sortBy(fn ($p) => [$depth($p), $p['id']])->values();
            }

            foreach ($records as $record) {
                $this->one($wpType, $record, fn ($p) => $seoOnly ? $this->importSeoOnly($p) : $this->importPost($p));
            }
        }
    }

    private function importPost(array $p): string
    {
        $type = ContentType::fromWordpress($p['type']);
        $status = self::STATUS_MAP[$p['status']] ?? null;
        $locale = $this->locale($p['lang']);

        if (! $type || ! $status || ! $locale) {
            $this->map->record($p['type'], $p['id'], ['status' => 'skipped', 'locale' => $p['lang'], 'source_url' => $p['link'], 'error' => "not imported: type {$p['type']}, status {$p['status']}, language ".($p['lang'] ?? 'none')]);

            return 'skipped';
        }

        $checksum = $this->checksum($p);
        if ($this->unchanged($p['type'], $p['id'], $checksum)) {
            return 'unchanged';
        }

        $group = $p['translations'] ?: [$locale => $p['id']];
        $contentId = $this->map->groupTarget([$p['type']], array_values($group))
            ?? Content::withTrashed()->where('source_system', 'wordpress')->where('source_id', (string) min($group))->value('id');
        $content = $contentId ? Content::withTrashed()->find($contentId) : null;
        $isNew = ! $content;
        $warnings = [];
        $sourceKey = (string) min($group);

        // Inconsistent Polylang groups: another WordPress post already owns this language on that content.
        // Never overwrite it; this post becomes its own content item.
        if (! $isNew && WpMigrationMap::where('source_type', $p['type'])->where('target_id', $content->id)->where('locale', $locale)
            ->where('source_id', '!=', (string) $p['id'])->where('status', 'success')->exists()) {
            $warnings[] = "translation_group_conflict: content {$content->id}";
            // This post may itself be the group's key (content.source_id); then use a per-locale key.
            $sourceKey = $content->source_id === (string) $p['id'] ? "{$p['id']}:{$locale}" : (string) $p['id'];
            $content = Content::withTrashed()->where('source_system', 'wordpress')->where('source_id', $sourceKey)->whereKeyNot($content->id)->first();
            $isNew = ! $content;
        }

        // One status per content: a less-visible translation never inherits a more-visible status.
        if (! $isNew && self::STATUS_PRIORITY[$status->value] < self::STATUS_PRIORITY[$content->status->value] && $content->translation($locale) === null) {
            $this->map->record($p['type'], $p['id'], ['status' => 'skipped', 'locale' => $locale, 'source_url' => $p['link'], 'error' => "translation status {$p['status']} is less visible than content status {$content->status->value}"]);

            return 'skipped';
        }
        if ($isNew) {
            $content = (new Content)->forceFill(['source_system' => 'wordpress', 'source_id' => $sourceKey]);
        }
        if ($content->trashed()) {
            $content->restoreQuietly();
        }
        $targetStatus = $isNew || self::STATUS_PRIORITY[$status->value] > self::STATUS_PRIORITY[$content->status->value] ? $status : $content->status;

        $ctx = $this->transformContext();
        $body = $this->transformer->transform($p['content'], $p['content_format'], $ctx, $p['elementor'] ?? null);
        $warnings = [...$warnings, ...$ctx->warnings];
        if (trim(strip_tags($body, '<img>')) === '' && $type === ContentType::Post) {
            $warnings[] = 'empty_body';
        }

        $featured = $p['featured_media'] ? $this->attachmentMediaId($p['featured_media']) : null;
        if ($p['featured_media'] && ! $featured) {
            $warnings[] = "missing_featured_media: {$p['featured_media']}";
        }

        $categories = $this->mapIds('category', $p['categories'], $warnings);
        $tags = $this->mapIds('post_tag', $p['tags'], $warnings);
        $date = $this->date($p['date_gmt']);

        $payload = [
            'type' => $type->value,
            'status' => $targetStatus->value,
            'author_id' => $this->author($p['author']) ?? $content->author_id,
            'published_at' => $date,
            'scheduled_at' => $targetStatus === ContentStatus::Scheduled ? $date : null,
            'menu_order' => $p['menu_order'],
            'featured_media_id' => $featured ?? $content->featured_media_id,
            'translations' => [$locale => [
                'title' => $p['title'] !== '' ? $p['title'] : "(untitled #{$p['id']})",
                'slug' => $p['slug'] !== '' ? $p['slug'] : null,
                'excerpt' => $this->excerpt($p['excerpt']),
                'body' => $body,
                'seo' => $this->seoPayload($p['seo'] ?? null, $warnings),
            ]],
        ];
        if ($type->hasTaxonomy()) {
            $payload['categories'] = array_values(array_unique([...($isNew ? [] : $content->categories()->pluck('categories.id')->all()), ...$categories]));
            $payload['tags'] = array_values(array_unique([...($isNew ? [] : $content->tags()->pluck('tags.id')->all()), ...$tags]));
        }
        if ($type->isHierarchical()) {
            $payload['parent_id'] = $this->map->targetId('page', $p['parent']) ?? ($isNew ? null : $content->parent_id);
            if ($p['parent'] && ! $payload['parent_id']) {
                $warnings[] = "missing_parent: {$p['parent']}";
            }
        }
        if (! $type->hasTaxonomy() || $type === ContentType::Document) {
            $payload['fields'] = $this->structuredFields($p, $content->fields ?? []);
        }
        if ($p['title'] === '') {
            $warnings[] = 'empty_title';
        }

        $content = $this->contents->save($content, $payload, null, 'Imported from WordPress', ['redirects' => false]);

        // Preserve WordPress timestamps for listings, sitemaps and audit.
        $content->forceFill([
            'created_at' => $isNew ? ($date ?? $content->created_at) : $content->created_at,
            'updated_at' => $this->date($p['modified_gmt']) ?? $content->updated_at,
        ])->saveQuietly();

        $url = $content->url($locale);
        if ($url && $p['link'] && RedirectResolver::normalize((string) parse_url($p['link'], PHP_URL_PATH)) !== RedirectResolver::normalize($url)) {
            $warnings[] = 'url_changed';
        }
        $this->redirectIfMoved($p['link'], $url);
        $this->success($p['type'], $p['id'], $p, 'content', $content->id, $checksum, $warnings, $url, $locale);

        return $isNew ? 'imported' : 'updated';
    }

    private function importSeoOnly(array $p): string
    {
        $locale = $this->locale($p['lang']);
        $contentId = $this->map->targetId($p['type'], $p['id']);
        $translation = $contentId && $locale ? ContentTranslation::where('content_id', $contentId)->where('locale', $locale)->first() : null;
        if (! $translation) {
            return 'skipped';
        }

        $warnings = [];
        $this->contents->save(Content::find($contentId), ['translations' => [$locale => [
            'title' => $translation->title,
            'slug' => $translation->slug,
            'seo' => $this->seoPayload($p['seo'] ?? null, $warnings),
        ]]], null, 'SEO re-imported from WordPress', ['redirects' => false]);

        return 'updated';
    }

    private function transformContext(): TransformContext
    {
        $ctx = null;
        $ctx = new TransformContext(
            mediaUrl: function (string $url) use (&$ctx) {
                return $this->resolveMediaUrl($url, $ctx);
            },
            attachmentUrl: fn (int $id) => ($mid = $this->attachmentMediaId($id)) ? Media::find($mid)?->derivativeUrl('web') : null,
            legacyHost: (string) parse_url(config('cms.wordpress.base_url'), PHP_URL_HOST),
        );

        return $ctx;
    }

    /** Mapped attachment; on the REST source an unmapped one is fetched and imported on demand. */
    private function attachmentMediaId(int $wpId): ?int
    {
        if ($id = $this->map->targetId('attachment', $wpId)) {
            return $id;
        }
        if ($this->dryRun) {
            return null;
        }

        foreach ($this->source->media(['id' => $wpId]) as $m) {
            try {
                $this->importMedia($m);
            } catch (Throwable $e) {
                // Recoverable (unsupported type, download failure): the referencing record gets a warning.
                $this->map->record('attachment', $wpId, ['status' => 'failed', 'error' => Str::limit($e->getMessage(), 2000), 'source_url' => $m['url']]);

                return null;
            }

            return $this->map->targetId('attachment', $wpId);
        }

        return null;
    }

    private function mapIds(string $type, array $ids, array &$warnings): array
    {
        $out = [];
        foreach ($ids as $id) {
            ($target = $this->map->targetId($type, $id)) ? $out[] = $target : $warnings[] = "missing_{$type}: {$id}";
        }

        return $out;
    }

    private function seoPayload(?array $seo, array &$warnings): ?array
    {
        if (! $seo) {
            return null;
        }

        $ogImage = null;
        if (! empty($seo['og_image'])) {
            $ogImage = $this->mediaIdForUrl($seo['og_image']);
            if (! $ogImage) {
                $warnings[] = 'missing_og_image';
            }
        }

        return [
            'meta_title' => $seo['title'],
            'meta_description' => $seo['description'],
            'meta_keywords' => $seo['keywords'],
            'canonical_url' => $seo['canonical'],
            'og_title' => $seo['og_title'],
            'og_description' => $seo['og_description'],
            'og_image_id' => $ogImage,
            'robots_index' => ! $seo['noindex'],
            'robots_follow' => ! $seo['nofollow'],
        ];
    }

    private function excerpt(?string $html): ?string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = trim(preg_replace('/\s*\[(…|&hellip;|\.\.\.)\]\s*$/u', '…', $text));

        return $text === '' ? null : Str::limit($text, 1000);
    }

    /**
     * Custom fields: kept verbatim under wp_meta; keys listed in config('cms.wordpress.field_map')
     * (decided in Phase 00, never guessed) are mapped onto the CMS fields.
     */
    private function structuredFields(array $p, array $existing): array
    {
        $fields = [...$existing, 'wp_meta' => $p['meta'] ?: null];

        foreach (config("cms.wordpress.field_map.{$p['type']}", []) as $metaKey => $field) {
            $value = $p['meta'][$metaKey] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if ($field === 'file_id') {
                $fields['file_id'] = is_numeric($value) ? $this->attachmentMediaId((int) $value) : null;
                $fields['file_url'] = is_numeric($value) ? null : $value;
            } else {
                $fields[$field] = $value;
            }
        }

        return array_filter($fields, fn ($v) => $v !== null);
    }

    // ------------------------------------------------------------------ comments

    private function importComment(array $c): string
    {
        $checksum = $this->checksum($c);
        if ($this->unchanged('comment', $c['id'], $checksum)) {
            return 'unchanged';
        }

        $contentId = $this->map->groupTarget(array_map(fn (ContentType $t) => $t->wordpressType(), ContentType::cases()), [$c['post']]);
        if (! $contentId) {
            $this->map->record('comment', $c['id'], ['status' => 'skipped', 'error' => "content for post {$c['post']} not imported"]);

            return 'skipped';
        }

        $comment = Comment::updateOrCreate(
            ['source_system' => 'wordpress', 'source_id' => (string) $c['id']],
            [
                'content_id' => $contentId,
                'parent_id' => $this->map->targetId('comment', $c['parent']),
                'name' => Str::limit(strip_tags($c['author_name']), 250, ''),
                'email' => $c['author_email'] ?: 'unknown@migrated.invalid',
                'body' => trim(strip_tags((string) $c['content'])),
                'status' => $c['status'],
                'created_at' => $this->date($c['date_gmt']) ?? now(),
            ],
        );
        // Posts that had discussions keep their comment section.
        Content::whereKey($contentId)->update(['is_commentable' => true]);
        $this->success('comment', $c['id'], $c, 'comment', $comment->id, $checksum);

        return $comment->wasRecentlyCreated ? 'imported' : 'updated';
    }

    // ------------------------------------------------------------------ redirects

    /** Yoast Premium redirects. Regex rules cannot be expressed in the redirects table and are reported. */
    private function importRedirect(array $r): string
    {
        $checksum = $this->checksum($r);
        if ($this->unchanged('redirect', $r['id'], $checksum)) {
            return 'unchanged';
        }

        $origin = '/'.ltrim(preg_replace('#^https?://[^/]+#i', '', $r['origin']), '/');
        if ($r['format'] !== 'plain') {
            $this->map->record('redirect', $r['id'], ['status' => 'skipped', 'source_url' => $r['origin'], 'error' => 'regex redirect: recreate manually or as an nginx rule']);

            return 'skipped';
        }

        [$locale, $rest] = Locales::split(RedirectResolver::nfc(rawurldecode($origin)));
        if (ContentTranslation::where('locale', $locale)->where('path', $rest)->exists()) {
            $this->map->record('redirect', $r['id'], ['status' => 'skipped', 'source_url' => $origin, 'error' => 'origin is live content in the CMS']);

            return 'skipped';
        }

        if (in_array($r['status'], [410, 451], true)) {
            $redirect = Redirect::updateOrCreate(['old_url' => RedirectResolver::normalize($origin)], ['new_url' => null, 'status_code' => 410, 'source' => 'migration']);
        } else {
            // Same-site absolute targets become paths; a target that itself moved is followed once (no chains).
            $target = preg_replace('#^https?://(www\.)?('.implode('|', array_map('preg_quote', $this->transformContext()->legacyHosts())).')#i', '', $r['target']) ?: '/';
            $target = str_starts_with($target, 'http') || str_starts_with($target, '/') ? $target : '/'.$target;
            $target = RedirectResolver::find($target)->new_url ?? $target;
            RedirectResolver::record($origin, $target, 'migration');
            $redirect = RedirectResolver::find($origin);
            $redirect?->update(['status_code' => in_array($r['status'], [301, 302, 307, 308], true) ? ($r['status'] === 307 ? 302 : ($r['status'] === 308 ? 301 : $r['status'])) : 301]);
        }

        if (! $redirect) {
            $this->map->record('redirect', $r['id'], ['status' => 'skipped', 'source_url' => $origin, 'error' => 'origin equals target']);

            return 'skipped';
        }
        $this->success('redirect', $r['id'], $r, 'redirect', $redirect->id, $checksum, [], $redirect->new_url);

        return 'imported';
    }

    // ------------------------------------------------------------------ menus

    private function importMenu(array $m): string
    {
        $checksum = $this->checksum($m);
        if ($this->unchanged('menu', $m['id'], $checksum)) {
            return 'unchanged';
        }

        $location = config("cms.wordpress.menu_locations.{$m['location']}", array_key_exists((string) $m['location'], config('cms.menu_locations')) ? $m['location'] : null);
        $locale = $this->locale($m['lang']);
        if (! $location || ! $locale) {
            $this->map->record('menu', $m['id'], ['status' => 'skipped', 'error' => "menu location \"{$m['location']}\" / language not mapped (config cms.wordpress.menu_locations)"]);

            return 'skipped';
        }

        $warnings = [];
        $byParent = collect($m['items'])->groupBy('parent');
        $descendants = function (int $parent) use (&$descendants, $byParent) {
            return collect($byParent[$parent] ?? [])->sortBy('order')->flatMap(fn ($i) => [$i, ...$descendants($i['id'])])->all();
        };
        $build = function (int $parent, int $depth) use (&$build, $byParent, $descendants, &$warnings) {
            return collect($byParent[$parent] ?? [])->sortBy('order')->map(function ($item) use ($build, $descendants, $depth, $byParent, &$warnings) {
                $node = $this->menuNode($item, $warnings);
                if (! $node) {
                    return null;
                }
                // Deeper than the CMS allows: keep every link, flattened into the last level.
                if ($depth === MenuService::MAX_DEPTH - 2 && count($deep = $descendants($item['id'])) > count($byParent[$item['id']] ?? [])) {
                    $warnings[] = 'menu_flattened: '.$item['title'];

                    return [...$node, 'children' => collect($deep)->map(fn ($d) => $this->menuNode($d, $warnings))->filter()->map(fn ($n) => [...$n, 'children' => []])->values()->all()];
                }

                return [...$node, 'children' => $build($item['id'], $depth + 1)];
            })->filter()->values()->all();
        };

        $menu = Menu::firstOrNew(['location' => $location, 'locale' => $locale]);
        $menu->fill(['name' => $m['name']])->forceFill(['source_system' => 'wordpress', 'source_id' => (string) $m['id']])->save();
        $this->menus->sync($menu, $build(0, 0));
        $this->success('menu', $m['id'], $m, 'menu', $menu->id, $checksum, $warnings, null, $locale);

        return 'imported';
    }

    private function menuNode(array $item, array &$warnings): ?array
    {
        $label = $item['title'];
        $base = ['target' => $item['target'] === '_blank' ? '_blank' : null];

        if ($item['type'] === 'post_type' && $contentId = $this->map->targetId((string) $item['object'], $item['object_id'])) {
            $label = $label !== '' ? $label : (Content::find($contentId)?->title ?? '');

            return [...$base, 'label' => $label ?: 'Link', 'item_type' => 'content', 'content_id' => $contentId];
        }
        if ($item['type'] === 'taxonomy' && $item['object'] === 'category' && $categoryId = $this->map->targetId('category', $item['object_id'])) {
            return [...$base, 'label' => $label ?: 'Category', 'item_type' => 'category', 'category_id' => $categoryId];
        }

        $url = (string) $item['url'];
        if ($label === '') {
            $warnings[] = 'menu_item_without_label';

            return null;
        }
        if ($url === '' || $url === '#') {
            return [...$base, 'label' => $label, 'item_type' => 'custom', 'url' => '#'];
        }

        $host = strtolower(preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST)));
        if ($host !== '' && ! in_array($host, $this->transformContext()->legacyHosts(), true)) {
            return [...$base, 'label' => $label, 'item_type' => 'external_url', 'url' => $url];
        }

        // Same-site URL: link to the CMS record when the path resolves, else keep the path (redirects apply).
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH)) ?: '/';
        [$locale, $rest] = Locales::split($path);
        if ($t = ContentTranslation::where('locale', $locale)->where('path', $rest)->first()) {
            return [...$base, 'label' => $label, 'item_type' => 'content', 'content_id' => $t->content_id];
        }
        if ($c = CategoryTranslation::where('locale', $locale)->where('path', $rest)->first()) {
            return [...$base, 'label' => $label, 'item_type' => 'category', 'category_id' => $c->category_id];
        }
        if ($rest !== '' && ! RedirectResolver::find($path)) {
            $warnings[] = "menu_link_unresolved: {$path}";
        }

        return [...$base, 'label' => $label, 'item_type' => 'custom', 'url' => Locales::path($locale, $rest)];
    }
}
