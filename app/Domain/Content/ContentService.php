<?php

namespace App\Domain\Content;

use App\Domain\Audit\Audit;
use App\Domain\Content\ContentStatus as S;
use App\Domain\SEO\RedirectResolver;
use App\Models\Content;
use App\Models\ContentRevision;
use App\Models\ContentTranslation;
use App\Models\User;
use App\Support\Locales;
use App\Support\PublicCache;
use App\Support\Slug;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The only write path for content: authorization, workflow, sanitation, URL paths,
 * SEO, taxonomy, revisions and audit happen here, whatever the caller (admin UI, bulk action,
 * scheduler, WordPress importer).
 *
 * Payload shape (all keys optional on update):
 *  type, status, author_id, parent_id, published_at, scheduled_at, is_commentable, is_featured,
 *  featured_media_id, template, fields, menu_order, categories[], tags[],
 *  translations[locale] => {title, slug, excerpt, body, blocks, seo{...}}
 */
class ContentService
{
    public const ATTRIBUTES = ['parent_id', 'featured_media_id', 'template', 'fields', 'is_commentable', 'is_featured', 'menu_order'];

    public const SEO_FIELDS = ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image_id', 'robots_index', 'robots_follow'];

    public function __construct(
        private readonly ContentWorkflow $workflow,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  User|null  $user  null = trusted system actor (scheduler, importer)
     * @param  array{sanitize?: bool, revision?: bool, redirects?: bool}  $options
     */
    public function save(Content $content, array $data, ?User $user, ?string $note = null, array $options = []): Content
    {
        $options += ['sanitize' => true, 'revision' => true, 'redirects' => true];
        $isNew = ! $content->exists;

        if ($user) {
            $this->authorize($user, $isNew ? 'create' : 'update', $isNew ? Content::class : $content);
        }

        return DB::transaction(function () use ($content, $data, $user, $note, $options, $isNew) {
            $before = $isNew ? null : $this->snapshot($content, withState: true);
            $fromStatus = $isNew ? null : $content->status;

            if ($isNew) {
                $content->type = ContentType::from($data['type'] ?? ContentType::Post->value);
                $content->author_id = $user?->id;
            }

            // Only editorial roles may (re)assign authorship; everyone else's payload value is ignored.
            if (array_key_exists('author_id', $data) && (! $user || Gate::forUser($user)->allows('assignAuthor', Content::class))) {
                $content->author_id = $data['author_id'];
            }

            $content->fill(Arr::only($data, self::ATTRIBUTES));
            $this->validateParent($content);

            $this->applyStatus($content, $data, $user);
            $content->save();

            $pathChanges = [];
            if (isset($data['translations'])) {
                $pathChanges = $this->syncTranslations($content, $data['translations'], $options['sanitize']);
            }

            if (array_key_exists('categories', $data) && $content->type->hasTaxonomy()) {
                $content->categories()->sync(array_filter((array) $data['categories']));
            }
            if (array_key_exists('tags', $data) && $content->type->hasTaxonomy()) {
                $content->tags()->sync(array_filter((array) $data['tags']));
            }

            $content->unsetRelation('translations')->unsetRelation('seo')->unsetRelation('categories')->unsetRelation('tags');

            if ($options['redirects'] && $fromStatus === S::Published) {
                foreach ($pathChanges as [$locale, $old, $new]) {
                    RedirectResolver::record(Locales::path($locale, $old), Locales::path($locale, $new));
                }
            }

            if ($options['revision']) {
                $this->recordRevision($content, $user, $note);
            }

            $after = $this->snapshot($content, withState: true);
            if ($before !== $after) {
                Audit::record($isNew ? 'create' : 'update', $content, $before, $after, $user?->id);
            }
            $this->auditStatusChange($content, $fromStatus, $user);

            if ($user) {
                $content->drafts()->where('user_id', $user->id)->delete();
            }

            PublicCache::flush();

            return $content;
        });
    }

    /** Status-only change (table actions, bulk actions). */
    public function transition(Content $content, S $to, ?User $user, ?Carbon $scheduledAt = null): Content
    {
        return $this->save($content, array_filter(['status' => $to->value, 'scheduled_at' => $scheduledAt]), $user, null, ['revision' => false]);
    }

    /** Idempotent: publishes scheduled content whose time has come. Returns the number published. */
    public function publishDue(): int
    {
        $count = 0;

        Content::where('status', S::Scheduled->value)->where('scheduled_at', '<=', now())->orderBy('scheduled_at')
            ->each(function (Content $content) use (&$count) {
                // Atomic claim so parallel schedulers cannot double-publish.
                $claimed = Content::whereKey($content->id)->where('status', S::Scheduled->value)
                    ->update(['status' => S::Published->value, 'published_at' => $content->scheduled_at, 'updated_at' => now()]);

                if ($claimed) {
                    Audit::record('publish', $content, ['status' => 'scheduled'], ['status' => 'published', 'via' => 'scheduler']);
                    $count++;
                }
            });

        if ($count) {
            PublicCache::flush();
        }

        return $count;
    }

    public function restoreRevision(Content $content, ContentRevision $revision, User $user): Content
    {
        $this->authorize($user, 'update', $content);

        if ($revision->content_id !== $content->id) {
            throw new AuthorizationException('Revision does not belong to this content.');
        }

        return DB::transaction(function () use ($content, $revision, $user) {
            // Guarantee the current state is itself restorable before overwriting it.
            $latest = $content->revisions()->first();
            if (! $latest || $latest->snapshot != $this->snapshot($content)) {
                $this->recordRevision($content, $user, 'Automatic backup before restore');
            }

            $snapshot = $revision->snapshot;
            $content = $this->save($content, [
                ...Arr::only($snapshot, [...self::ATTRIBUTES, 'categories', 'tags']),
                'translations' => $this->withRemovedLocales($content, $snapshot['translations'] ?? []),
            ], $user, "Restored version {$revision->version}");

            Audit::record('restore', $content, null, ['revision' => $revision->version], $user->id);

            return $content;
        });
    }

    /** Restorable state. With $withState also status/author/dates, used for audit and form filling. */
    public function snapshot(Content $content, bool $withState = false): array
    {
        $content->loadMissing(['translations', 'seo', 'categories', 'tags']);

        $snapshot = [
            ...collect(self::ATTRIBUTES)->mapWithKeys(fn ($a) => [$a => $content->getAttribute($a)])->all(),
            'categories' => $content->categories->pluck('id')->sort()->values()->all(),
            'tags' => $content->tags->pluck('id')->sort()->values()->all(),
            'translations' => $content->translations->sortBy('locale')->mapWithKeys(function (ContentTranslation $t) use ($content) {
                $seo = $content->seo->firstWhere('locale', $t->locale);

                return [$t->locale => [
                    'title' => $t->title,
                    'slug' => $t->slug,
                    'excerpt' => $t->excerpt,
                    'body' => $t->body,
                    'blocks' => $t->blocks,
                    'seo' => $seo ? Arr::only($seo->toArray(), self::SEO_FIELDS) : null,
                ]];
            })->all(),
        ];

        if ($withState) {
            $snapshot += [
                'status' => $content->status->value,
                'author_id' => $content->author_id,
                'published_at' => $content->published_at?->toDateTimeString(),
                'scheduled_at' => $content->scheduled_at?->toDateTimeString(),
            ];
        }

        return $snapshot;
    }

    private function authorize(User $user, string $ability, mixed $target): void
    {
        if (Gate::forUser($user)->denies($ability, $target)) {
            throw new AuthorizationException("Not allowed to {$ability} this content.");
        }
    }

    private function applyStatus(Content $content, array $data, ?User $user): void
    {
        $to = isset($data['status']) ? S::from($data['status'] instanceof S ? $data['status']->value : $data['status']) : ($content->status ?? S::Draft);

        if ($to === S::Trash) {
            throw ValidationException::withMessages(['status' => 'Use the delete action to move content to trash.']);
        }

        if ($user && ! $this->workflow->can($user, $content, $to)) {
            throw ValidationException::withMessages(['status' => "You are not allowed to set status \"{$to->label()}\"."]);
        }

        if (array_key_exists('published_at', $data)) {
            $content->published_at = $data['published_at'];
        }

        if ($to === S::Scheduled) {
            $at = isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : $content->scheduled_at;
            if (! $at || ($user && $at->isPast())) {
                throw ValidationException::withMessages(['scheduled_at' => 'Scheduled content needs a publication time in the future.']);
            }
            $content->scheduled_at = $at;
            $content->published_at = $at;
        } elseif ($to === S::Published && ! $content->published_at) {
            $content->published_at = now();
        }

        $content->status = $to;
    }

    private function validateParent(Content $content): void
    {
        if (! $content->parent_id) {
            return;
        }

        if (! $content->type->isHierarchical()) {
            $content->parent_id = null;

            return;
        }

        // Walk up the chain: the parent must exist, be a page, and not be this page or its descendant.
        $seen = [$content->id];
        $parentId = $content->parent_id;
        while ($parentId) {
            if (in_array($parentId, $seen, true)) {
                throw ValidationException::withMessages(['parent_id' => 'A page cannot be nested under itself.']);
            }
            $seen[] = $parentId;
            $parent = Content::withTrashed()->find($parentId, ['id', 'type', 'parent_id']);
            if (! $parent || $parent->type !== $content->type) {
                throw ValidationException::withMessages(['parent_id' => 'Invalid parent page.']);
            }
            $parentId = $parent->parent_id;
        }
    }

    /** @return list<array{0: string, 1: string, 2: string}> [locale, old path, new path] */
    private function syncTranslations(Content $content, array $translations, bool $sanitize): array
    {
        $changes = [];
        $existing = $content->translations()->get()->keyBy('locale');

        foreach ($translations as $locale => $input) {
            if (! Locales::isSupported($locale)) {
                continue;
            }

            $translation = $existing->get($locale);

            // An empty title removes the translation (explicit "this language does not exist").
            if (blank($input['title'] ?? null)) {
                $translation?->delete();
                $content->seo()->where('locale', $locale)->delete();

                continue;
            }

            $slug = Slug::make(filled($input['slug'] ?? null) ? $input['slug'] : $input['title'], $locale) ?: (string) $content->id;
            $path = $this->uniquePath($content, $locale, $this->basePath($content, $locale), $slug);

            $translation ??= new ContentTranslation(['locale' => $locale]);
            $oldPath = $translation->exists ? $translation->path : null;

            $translation->fill([
                'title' => trim($input['title']),
                'slug' => basename($path),
                'path' => $path,
                'excerpt' => $input['excerpt'] ?? $translation->excerpt,
                'body' => array_key_exists('body', $input) ? ($sanitize ? $this->sanitizer->clean($input['body']) : $input['body']) : $translation->body,
                'blocks' => array_key_exists('blocks', $input) ? $this->sanitizeBlocks($input['blocks'], $sanitize) : $translation->blocks,
            ]);
            $content->translations()->save($translation);
            if ($oldPath !== $path) {
                RedirectResolver::clear(Locales::path($locale, $path));
            }

            if ($oldPath !== null && $oldPath !== $path) {
                $changes[] = [$locale, $oldPath, $path];
                $this->rebaseChildren($content, $locale, $oldPath, $path);
            }

            if (array_key_exists('seo', $input) && is_array($input['seo'])) {
                $seo = Arr::only($input['seo'], self::SEO_FIELDS);
                $seo['robots_index'] = (bool) ($seo['robots_index'] ?? true);
                $seo['robots_follow'] = (bool) ($seo['robots_follow'] ?? true);
                $content->seo()->updateOrCreate(['locale' => $locale], $seo);
            }
        }

        return $changes;
    }

    private function basePath(Content $content, string $locale): string
    {
        if ($content->type->isHierarchical() && $content->parent_id) {
            $parentPath = ContentTranslation::where('content_id', $content->parent_id)->where('locale', $locale)->value('path');

            return $parentPath ? $parentPath.'/' : '';
        }

        return $content->type->urlBase() !== '' ? $content->type->urlBase().'/' : '';
    }

    /**
     * WordPress-style de-duplication: slug, slug-2, slug-3... Content may share a path with a category;
     * like WordPress, content wins in the resolver (the category archive stays reachable via its children).
     */
    private function uniquePath(Content $content, string $locale, string $base, string $slug): string
    {
        for ($i = 1; ; $i++) {
            $candidate = $base.($i === 1 ? $slug : "{$slug}-{$i}");

            $taken = ($base === '' && in_array($candidate, Slug::RESERVED, true))
                || ContentTranslation::where('locale', $locale)->where('path', $candidate)->where('content_id', '!=', $content->id)->exists();

            if (! $taken) {
                return $candidate;
            }
        }
    }

    private function rebaseChildren(Content $content, string $locale, string $oldPath, string $newPath): void
    {
        ContentTranslation::where('locale', $locale)
            ->where('path', 'like', addcslashes($oldPath, '%_\\').'/%')
            ->each(function (ContentTranslation $child) use ($locale, $oldPath, $newPath) {
                $old = $child->path;
                $child->update(['path' => $newPath.substr($old, strlen($oldPath))]);
                RedirectResolver::record(Locales::path($locale, $old), Locales::path($locale, $child->path));
            });
    }

    private function sanitizeBlocks(?array $blocks, bool $sanitize): ?array
    {
        if (! $blocks || ! $sanitize) {
            return $blocks ?: null;
        }

        array_walk_recursive($blocks, function (&$value, $key) {
            if (is_string($value) && in_array($key, ['body', 'html', 'answer'], true)) {
                $value = $this->sanitizer->clean($value);
            }
        });

        return array_values($blocks);
    }

    private function withRemovedLocales(Content $content, array $translations): array
    {
        foreach ($content->translations as $t) {
            $translations[$t->locale] ??= ['title' => null];
        }

        return $translations;
    }

    private function recordRevision(Content $content, ?User $user, ?string $note): void
    {
        $snapshot = $this->snapshot($content);
        $latest = $content->revisions()->first();

        if ($latest && $latest->snapshot == $snapshot) {
            return;
        }

        $content->revisions()->create([
            'user_id' => $user?->id,
            'version' => ($latest->version ?? 0) + 1,
            'snapshot' => $snapshot,
            'note' => $note,
        ]);
    }

    private function auditStatusChange(Content $content, ?S $from, ?User $user): void
    {
        $to = $content->status;
        if ($from === $to) {
            return;
        }

        $action = match (true) {
            $to === S::Published => 'publish',
            $to === S::Scheduled => 'schedule',
            $from?->isLive() && ! $to->isLive() => 'unpublish',
            default => null,
        };

        if ($action) {
            Audit::record($action, $content, ['status' => $from?->value], ['status' => $to->value], $user?->id);
        }
    }
}
