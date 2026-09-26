<?php

namespace App\Domain\Migration\WordPress\Sources;

use App\Domain\Migration\WordPress\SeoMapper;
use App\Domain\Migration\WordPress\Transform\MenuHtmlExtractor;
use App\Support\Locales;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Public WordPress REST API (+ Polylang "lang"/"translations" fields, + Yoast "yoast_head_json").
 * Only published content is visible; users, private meta and menus are not exposed, so menus are
 * extracted from the rendered header navigation instead.
 */
class RestApiSource implements WordPressSource
{
    private const REST_BASES = [
        'post' => 'posts', 'page' => 'pages', 'attachment' => 'media',
        'download-documents' => 'download-documents', 'notification' => 'notification',
        'tuition-fees' => 'tuition-fees', 'current-opportunitie' => 'current-opportunitie',
    ];

    private const POST_FIELDS = 'id,date_gmt,modified_gmt,slug,status,type,link,title,content,excerpt,author,featured_media,comment_status,menu_order,template,parent,categories,tags,lang,translations,yoast_head_json,meta';

    public function __construct(private readonly string $baseUrl) {}

    public function name(): string
    {
        return 'rest';
    }

    public function users(): iterable
    {
        return []; // /wp/v2/users requires authentication; authors are created as placeholders.
    }

    public function terms(string $taxonomy): iterable
    {
        $endpoint = $taxonomy === 'category' ? 'categories' : 'tags';

        foreach ($this->paginate($endpoint, ['_fields' => 'id,name,slug,description,parent,link,lang,translations,count', 'hide_empty' => 'false']) as $t) {
            yield [
                'id' => (int) $t['id'],
                'taxonomy' => $taxonomy,
                'name' => $this->text($t['name']),
                'slug' => rawurldecode($t['slug']),
                'description' => $t['description'] ?? null,
                'parent' => (int) ($t['parent'] ?? 0),
                'lang' => $t['lang'] ?? null,
                'translations' => $t['translations'] ?? [],
                'link' => $t['link'] ?? null,
                'count' => $t['count'] ?? null,
            ];
        }
    }

    public function media(array $filters = []): iterable
    {
        $params = $this->filterParams($filters) + ['_fields' => 'id,date_gmt,modified_gmt,source_url,title,alt_text,caption,description,mime_type,lang,media_details'];

        foreach ($this->paginate('media', $params, $filters['limit'] ?? null) as $m) {
            yield [
                'id' => (int) $m['id'],
                'url' => $m['source_url'],
                'title' => $this->text($m['title']['rendered'] ?? ''),
                'alt' => $m['alt_text'] ?? null,
                'caption' => $this->text($m['caption']['rendered'] ?? '') ?: null,
                'description' => null,
                'mime' => $m['mime_type'] ?? null,
                'date_gmt' => $m['date_gmt'] ?? null,
                'modified_gmt' => $m['modified_gmt'] ?? null,
                'lang' => $m['lang'] ?? null,
                'filesize' => $m['media_details']['filesize'] ?? null,
            ];
        }
    }

    public function posts(string $type, array $filters = []): iterable
    {
        $base = self::REST_BASES[$type] ?? throw new RuntimeException("Unknown post type {$type}");
        $params = $this->filterParams($filters) + ['_fields' => self::POST_FIELDS];
        if (! empty($filters['locale'])) {
            $params['lang'] = $filters['locale'];
        }

        foreach ($this->paginate($base, $params, $filters['limit'] ?? null) as $p) {
            $title = $this->text($p['title']['rendered'] ?? '');

            yield [
                'id' => (int) $p['id'],
                'type' => $p['type'],
                'status' => $p['status'],
                'lang' => $p['lang'] ?? null,
                'translations' => $p['translations'] ?? [],
                'parent' => (int) ($p['parent'] ?? 0),
                'slug' => rawurldecode((string) $p['slug']),
                'title' => $title,
                'excerpt' => $p['excerpt']['rendered'] ?? null,
                'content' => $p['content']['rendered'] ?? '',
                'content_format' => 'rendered',
                'author' => (int) ($p['author'] ?? 0),
                'date_gmt' => $p['date_gmt'] ?? null,
                'modified_gmt' => $p['modified_gmt'] ?? null,
                'featured_media' => (int) ($p['featured_media'] ?? 0),
                'categories' => $p['categories'] ?? [],
                'tags' => $p['tags'] ?? [],
                'comment_status' => $p['comment_status'] ?? 'closed',
                'menu_order' => (int) ($p['menu_order'] ?? 0),
                'link' => $p['link'] ?? null,
                'template' => $p['template'] ?? '',
                'seo' => isset($p['yoast_head_json']) ? SeoMapper::fromYoastHead($p['yoast_head_json'], $title, $p['link'] ?? null) : null,
                'meta' => array_filter((array) ($p['meta'] ?? []), fn ($k) => ! str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY),
            ];
        }
    }

    public function menus(): iterable
    {
        foreach (Locales::codes() as $locale) {
            $url = $this->baseUrl.Locales::path($locale);
            $response = $this->http()->get($url);
            if (! $response->successful()) {
                continue;
            }

            foreach ((new MenuHtmlExtractor)->extract($response->body(), $this->baseUrl) as $location => $items) {
                yield ['id' => "html-{$location}-{$locale}", 'name' => ucfirst($location).' ('.strtoupper($locale).')', 'location' => $location, 'lang' => $locale, 'items' => $items];
            }
        }
    }

    public function comments(array $filters = []): iterable
    {
        foreach ($this->paginate('comments', ['_fields' => 'id,post,parent,author_name,content,date_gmt,status']) as $c) {
            yield [
                'id' => (int) $c['id'],
                'post' => (int) $c['post'],
                'parent' => (int) ($c['parent'] ?? 0),
                'author_name' => $c['author_name'] ?? 'Anonymous',
                'author_email' => null,
                'content' => $this->text($c['content']['rendered'] ?? ''),
                'date_gmt' => $c['date_gmt'] ?? null,
                'status' => $c['status'] === 'approved' ? 'approved' : 'pending',
            ];
        }
    }

    public function inventory(): array
    {
        $root = $this->http()->get($this->baseUrl.'/wp-json/')->json();
        $types = $this->http()->get($this->api('types'))->json() ?? [];

        $counts = [];
        foreach (self::REST_BASES as $type => $base) {
            $counts[$type]['all'] = $this->total($base);
            foreach (Locales::codes() as $locale) {
                $counts[$type][$locale] = $this->total($base, ['lang' => $locale]);
            }
        }
        foreach (['category' => 'categories', 'post_tag' => 'tags'] as $tax => $base) {
            $counts[$tax]['all'] = $this->total($base, ['hide_empty' => 'false']);
            foreach (Locales::codes() as $locale) {
                $counts[$tax][$locale] = $this->total($base, ['lang' => $locale, 'hide_empty' => 'false']);
            }
        }
        $counts['comment']['all'] = $this->total('comments');

        return [
            'source' => 'rest',
            'site' => ['name' => $root['name'] ?? null, 'url' => $root['url'] ?? $this->baseUrl],
            'namespaces' => $root['namespaces'] ?? [],
            'post_types' => collect($types)->map(fn ($t) => $t['rest_base'] ?? null)->filter()->all(),
            'counts' => $counts,
        ];
    }

    private function filterParams(array $filters): array
    {
        return array_filter([
            'modified_after' => ! empty($filters['since']) ? date('Y-m-d\TH:i:s', strtotime($filters['since'])) : null,
            'include' => $filters['id'] ?? null,
            'orderby' => 'id',
            'order' => 'asc',
        ]);
    }

    /** @return iterable<array> */
    private function paginate(string $endpoint, array $params = [], ?int $limit = null): iterable
    {
        $page = 1;
        $yielded = 0;
        do {
            $response = $this->http()->get($this->api($endpoint), $params + ['per_page' => 100, 'page' => $page]);
            if ($response->status() === 400 && $page > 1) {
                break; // past the last page
            }
            if (! $response->successful()) {
                throw new RuntimeException("WordPress REST {$endpoint} page {$page} failed with HTTP {$response->status()}");
            }

            foreach ($response->json() ?? [] as $row) {
                yield $row;
                if ($limit && ++$yielded >= $limit) {
                    return;
                }
            }

            $pages = (int) $response->header('X-WP-TotalPages');
        } while ($page++ < $pages);
    }

    private function total(string $endpoint, array $params = []): ?int
    {
        $response = $this->http()->get($this->api($endpoint), $params + ['per_page' => 1, '_fields' => 'id']);

        return $response->successful() ? (int) $response->header('X-WP-Total') : null;
    }

    private function api(string $endpoint): string
    {
        return $this->baseUrl.'/wp-json/wp/v2/'.$endpoint;
    }

    private function http(): PendingRequest
    {
        return Http::withUserAgent('VJU-CMS-Migrator/1.0')->timeout(60)->retry(3, 2000, throw: false);
    }

    private function text(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
