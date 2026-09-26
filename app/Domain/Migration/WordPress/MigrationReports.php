<?php

namespace App\Domain\Migration\WordPress;

use App\Domain\SEO\RedirectResolver;
use App\Models\CategoryTranslation;
use App\Models\ContentTranslation;
use App\Models\Media;
use App\Models\Redirect;
use App\Models\WpMigrationMap;
use App\Support\Locales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** wp:validate (reconciliation + URL/content checks) and wp:report (per-record report, URL inventory). */
class MigrationReports
{
    private const CONTENT_SOURCE_TYPES = ['post', 'page', 'download-documents', 'notification', 'tuition-fees', 'current-opportunitie'];

    /** @param  array|null  $inventory  source inventory (wp:inspect) for count reconciliation */
    public function validate(?array $inventory = null): array
    {
        $issues = [];
        $add = function (string $check, string $subject, string $detail = '') use (&$issues) {
            $issues[] = compact('check', 'subject', 'detail');
        };

        // --- reconciliation: WP published counts vs CMS translations, per type and locale
        $target = DB::table('content_translations as t')->join('contents as c', 'c.id', '=', 't.content_id')
            ->where('c.source_system', 'wordpress')->whereNull('c.deleted_at')
            ->select('c.type', 't.locale', DB::raw('count(*) as n'))->groupBy('c.type', 't.locale')->get()
            ->groupBy('type')->map(fn ($rows) => $rows->pluck('n', 'locale'));
        $typeMap = ['post' => 'post', 'page' => 'page', 'download-documents' => 'document', 'notification' => 'notification', 'tuition-fees' => 'tuition_fee', 'current-opportunitie' => 'opportunity'];
        $reconciliation = [];
        foreach ($typeMap as $wpType => $cmsType) {
            foreach (Locales::codes() as $locale) {
                $source = $inventory['counts'][$wpType][$locale] ?? $inventory['published_by_language'][$locale][$wpType] ?? null;
                $cms = (int) ($target[$cmsType][$locale] ?? 0);
                $reconciliation[] = ['type' => $wpType, 'locale' => $locale, 'source' => $source, 'cms' => $cms, 'ok' => $source === null || $cms >= $source];
                if ($source !== null && $cms < $source) {
                    $add('count_mismatch', "{$wpType}/{$locale}", "source {$source}, cms {$cms}");
                }
            }
        }
        foreach (['category' => 'category', 'post_tag' => 'post_tag'] as $tax => $key) {
            $source = $inventory['counts'][$tax]['all'] ?? null;
            $cms = WpMigrationMap::where('source_type', $tax)->where('status', 'success')->count();
            $reconciliation[] = ['type' => $tax, 'locale' => 'all', 'source' => $source, 'cms' => $cms, 'ok' => $source === null || $cms >= $source];
        }
        $reconciliation[] = ['type' => 'attachment', 'locale' => 'all', 'source' => $inventory['counts']['attachment']['all'] ?? null,
            'cms' => WpMigrationMap::where('source_type', 'attachment')->where('status', 'success')->count(), 'ok' => true];

        // --- URLs: every legacy URL must be 200 or a single 301 to a 200
        $contentPaths = ContentTranslation::query()->whereHas('content', fn ($q) => $q->where('status', 'published'))->get(['locale', 'path'])
            ->mapWithKeys(fn ($t) => [RedirectResolver::normalize($t->url()) => true]);
        $categoryPaths = CategoryTranslation::get(['locale', 'path'])->mapWithKeys(fn ($t) => [RedirectResolver::normalize($t->url()) => true]);
        $live = fn (string $url) => isset($contentPaths[RedirectResolver::normalize($url)]) || isset($categoryPaths[RedirectResolver::normalize($url)]) || RedirectResolver::normalize($url) === '/';
        $redirects = Redirect::get()->keyBy('old_url');

        $urlStats = ['ok_200' => 0, 'ok_301' => 0, 'failed' => 0, 'not_public' => 0];
        // Drafts, scheduled and private posts had no public URL in WordPress either.
        $publishedIds = DB::table('contents')->where('status', 'published')->whereNull('deleted_at')->pluck('id')->flip();
        WpMigrationMap::whereIn('source_type', [...self::CONTENT_SOURCE_TYPES, 'category', 'post_tag'])->where('status', 'success')->whereNotNull('source_url')
            ->select(['source_type', 'source_id', 'source_url', 'target_type', 'target_id'])->orderBy('id')
            ->each(function ($row) use ($live, $redirects, &$urlStats, $add, $publishedIds) {
                if ($row->target_type === 'content' && ! isset($publishedIds[$row->target_id])) {
                    $urlStats['not_public']++;

                    return;
                }
                $path = (string) parse_url($row->source_url, PHP_URL_PATH);
                $key = RedirectResolver::normalize($path);
                if ($row->source_type === 'post_tag' || $live($path)) {
                    $urlStats['ok_200']++;

                    return;
                }
                $redirect = $redirects[$key] ?? null;
                if (! $redirect) {
                    $urlStats['failed']++;
                    $add('url_404', $path, "{$row->source_type} {$row->source_id}");
                } elseif ($redirect->status_code === 410) {
                    $urlStats['ok_301']++;
                } elseif (isset($redirects[RedirectResolver::normalize($redirect->new_url)])) {
                    $urlStats['failed']++;
                    $add('redirect_chain', $path, "→ {$redirect->new_url} → …");
                } elseif (! $live((string) $redirect->new_url) && ! preg_match('#^https?://#', (string) $redirect->new_url)) {
                    $urlStats['failed']++;
                    $add('redirect_to_404', $path, "→ {$redirect->new_url}");
                } else {
                    $urlStats['ok_301']++;
                }
            });
        foreach ($redirects as $r) {
            if ($r->new_url && RedirectResolver::normalize($r->new_url) === $r->old_url) {
                $add('redirect_loop', $r->old_url);
            }
        }

        // --- content quality
        $mediaUrls = Media::get(['disk', 'path', 'metadata'])->flatMap(fn (Media $m) => [
            parse_url($m->url(), PHP_URL_PATH), ...array_map(fn ($d) => parse_url(\Storage::disk($m->disk)->url($d['path']), PHP_URL_PATH), $m->metadata['derivatives'] ?? []),
        ])->filter()->flip();
        $referenced = [];
        $counters = ['empty_title' => 0, 'empty_body' => 0, 'broken_image' => 0, 'broken_link' => 0];

        ContentTranslation::with('content:id,type,featured_media_id')->select(['id', 'content_id', 'locale', 'title', 'path', 'body'])->orderBy('id')
            ->chunk(200, function ($chunk) use (&$counters, &$referenced, $mediaUrls, $live, $redirects, $add) {
                foreach ($chunk as $t) {
                    $subject = $t->url();
                    if (trim($t->title) === '' || str_starts_with($t->title, '(untitled')) {
                        $counters['empty_title']++;
                        $add('empty_title', $subject);
                    }
                    if ($t->content?->type->value === 'post' && trim(strip_tags((string) $t->body, '<img>')) === '') {
                        $counters['empty_body']++;
                        $add('empty_body', $subject);
                    }
                    preg_match_all('/<img[^>]+src="([^"]+)"/i', (string) $t->body, $imgs);
                    foreach ($imgs[1] as $src) {
                        $path = parse_url(html_entity_decode($src), PHP_URL_PATH);
                        $referenced[$path] = true;
                        if (str_starts_with((string) $path, '/storage/') && ! isset($mediaUrls[$path])) {
                            $counters['broken_image']++;
                            $add('broken_image', $subject, $src);
                        } elseif (str_contains($src, '/wp-content/uploads/')) {
                            $counters['broken_image']++;
                            $add('legacy_image', $subject, $src);
                        }
                    }
                    preg_match_all('/<a[^>]+href="(\/[^"#?]*)/i', (string) $t->body, $links);
                    foreach ($links[1] as $href) {
                        $href = html_entity_decode($href);
                        if (str_starts_with($href, '/storage/')) {
                            $referenced[$href] = true;
                            if (! isset($mediaUrls[$href])) {
                                $counters['broken_link']++;
                                $add('broken_file_link', $subject, $href);
                            }
                        } elseif (! $live($href) && ! isset($redirects[RedirectResolver::normalize($href)]) && ! preg_match('#^/(en/|ja/)?(tag|search|page)/#', $href)) {
                            $counters['broken_link']++;
                            $add('broken_link', $subject, $href);
                        }
                    }
                }
            });

        // Duplicate slugs within a locale (paths are unique by constraint; slugs may repeat under different parents).
        foreach (DB::table('content_translations')->select('locale', 'slug', DB::raw('count(*) as n'))->groupBy('locale', 'slug')->having('n', '>', 1)->limit(500)->get() as $dup) {
            $add('duplicate_slug', "{$dup->locale}/{$dup->slug}", "{$dup->n} items");
        }

        $featured = DB::table('contents')->whereNotNull('featured_media_id')->pluck('featured_media_id')->flip();
        $orphans = Media::whereNotIn('id', $featured->keys())->get(['id', 'disk', 'path', 'metadata'])
            ->reject(fn (Media $m) => isset($referenced[parse_url($m->url(), PHP_URL_PATH)]) || collect($m->metadata['derivatives'] ?? [])->contains(fn ($d) => isset($referenced[parse_url(\Storage::disk($m->disk)->url($d['path']), PHP_URL_PATH)])))
            ->count();

        $untranslated = collect(Locales::codes())->mapWithKeys(fn ($l) => [$l => DB::table('contents')->whereNull('deleted_at')
            ->whereNotExists(fn ($q) => $q->from('content_translations')->whereColumn('content_translations.content_id', 'contents.id')->where('locale', $l))->count()])->all();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'reconciliation' => $reconciliation,
            'urls' => $urlStats,
            'content' => $counters + ['orphaned_media' => $orphans, 'missing_translation_by_locale' => $untranslated],
            'map_status' => WpMigrationMap::select('source_type', 'status', DB::raw('count(*) as n'))->groupBy('source_type', 'status')->get()
                ->groupBy('source_type')->map(fn ($rows) => $rows->pluck('n', 'status'))->all(),
            'issues' => $issues,
            'passed' => ! collect($issues)->whereIn('check', ['count_mismatch', 'url_404', 'redirect_chain', 'redirect_loop', 'redirect_to_404'])->count(),
        ];

        $stamp = now()->format('Ymd-His');
        Storage::disk('local')->put("migration/validation-{$stamp}.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->csv("migration/validation-{$stamp}.csv", ['check', 'subject', 'detail'], $issues);
        $report['files'] = ["storage/app/private/migration/validation-{$stamp}.json", "storage/app/private/migration/validation-{$stamp}.csv"];

        return $report;
    }

    /** migration-report.json/csv (one row per source record) + url-inventory.csv (Phase 00 D1 format). */
    public function report(): array
    {
        $rows = [];
        $inventory = [];

        WpMigrationMap::orderBy('source_type')->orderBy('id')->each(function (WpMigrationMap $m) use (&$rows, &$inventory) {
            $rows[] = [
                'source_type' => $m->source_type,
                'source_id' => $m->source_id,
                'locale' => $m->locale,
                'status' => $m->status,
                'target_type' => $m->target_type,
                'target_id' => $m->target_id,
                'source_url' => $m->source_url,
                'target_url' => $m->target_url,
                'warnings' => implode(' | ', $m->warnings ?? []),
                'errors' => $m->error,
            ];

            if ($m->source_url && in_array($m->source_type, [...self::CONTENT_SOURCE_TYPES, 'category', 'post_tag'], true)) {
                $same = $m->target_url && RedirectResolver::normalize(parse_url($m->source_url, PHP_URL_PATH)) === RedirectResolver::normalize($m->target_url);
                $inventory[] = [
                    'url' => $m->source_url,
                    'normalized_url' => RedirectResolver::normalize(parse_url($m->source_url, PHP_URL_PATH)),
                    'locale' => $m->locale,
                    'content_type' => $m->source_type,
                    'wordpress_post_id' => $m->source_id,
                    'migration_action' => $m->status !== 'success' ? 'REVIEW' : ($same ? 'MIGRATE_200' : 'MIGRATE_301'),
                    'target_url' => $m->target_url,
                    'notes' => $m->error ?? implode(' | ', $m->warnings ?? []),
                ];
            }
        });

        $warnings = collect($rows)->pluck('warnings')->filter()->flatMap(fn ($w) => explode(' | ', $w))
            ->map(fn ($w) => explode(':', $w)[0])->countBy()->sortDesc()->all();

        $summary = [
            'generated_at' => now()->toIso8601String(),
            'by_type' => collect($rows)->groupBy('source_type')->map(fn ($r) => $r->countBy('status'))->all(),
            'warnings_by_code' => $warnings,
            'missing_media' => ($warnings['missing_media'] ?? 0) + ($warnings['missing_featured_media'] ?? 0),
            'redirects' => Redirect::count(),
            'redirects_from_migration' => Redirect::where('source', 'migration')->count(),
        ];

        Storage::disk('local')->put('migration/migration-report.json', json_encode(['summary' => $summary, 'records' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->csv('migration/migration-report.csv', array_keys($rows[0] ?? ['source_type' => 1]), $rows);
        $this->csv('migration/url-inventory.csv', ['url', 'normalized_url', 'locale', 'content_type', 'wordpress_post_id', 'migration_action', 'target_url', 'notes'], $inventory);

        return $summary + ['files' => ['migration-report.json', 'migration-report.csv', 'url-inventory.csv']];
    }

    private function csv(string $path, array $header, array $rows): void
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($k) => $row[$k] ?? '', $header));
        }
        rewind($handle);
        Storage::disk('local')->put($path, stream_get_contents($handle));
        fclose($handle);
    }
}
