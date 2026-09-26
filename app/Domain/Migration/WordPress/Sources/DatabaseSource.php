<?php

namespace App\Domain\Migration\WordPress\Sources;

use App\Domain\Migration\WordPress\SeoMapper;
use App\Support\Locales;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * WordPress database (production dump restored into the "wordpress" connection). Authoritative for
 * drafts, private meta, JetEngine fields, Elementor data, users, menus and Polylang relationships:
 *  - post/term language: taxonomy "language" / "term_language" (term slug "vi" / "pll_vi")
 *  - translation groups: taxonomy "post_translations" / "term_translations", serialized description
 */
class DatabaseSource implements WordPressSource
{
    private const STATUSES = ['publish', 'draft', 'pending', 'private', 'future'];

    private ?array $languageTerms = null;

    public function __construct(
        private readonly Connection $db,
        private readonly string $prefix,
        private readonly string $baseUrl,
    ) {}

    public static function make(): self
    {
        return new self(DB::connection('wordpress'), config('cms.wordpress.prefix'), config('cms.wordpress.base_url'));
    }

    public function name(): string
    {
        return 'db';
    }

    private function t(string $table): string
    {
        return $this->prefix.$table;
    }

    public function users(): iterable
    {
        foreach ($this->db->table($this->t('users'))->orderBy('ID')->cursor() as $u) {
            yield ['id' => (int) $u->ID, 'name' => $u->display_name ?: $u->user_login, 'email' => strtolower($u->user_email), 'login' => $u->user_login, 'registered' => $u->user_registered];
        }
    }

    public function terms(string $taxonomy): iterable
    {
        $rows = $this->db->table($this->t('terms').' as t')
            ->join($this->t('term_taxonomy').' as tt', 'tt.term_id', '=', 't.term_id')
            ->where('tt.taxonomy', $taxonomy)
            ->orderBy('t.term_id')
            ->get(['t.term_id', 't.name', 't.slug', 'tt.description', 'tt.parent', 'tt.count']);

        $ids = $rows->pluck('term_id')->all();
        $langs = $this->objectTaxonomy($ids, 'term_language', fn ($slug) => substr($slug, 4));
        $groups = $this->groups($ids, 'term_translations');

        foreach ($rows as $r) {
            yield [
                'id' => (int) $r->term_id,
                'taxonomy' => $taxonomy,
                'name' => html_entity_decode($r->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'slug' => rawurldecode($r->slug),
                'description' => $r->description ?: null,
                'parent' => (int) $r->parent,
                'lang' => $langs[$r->term_id] ?? null,
                'translations' => $groups[$r->term_id] ?? [],
                'link' => null,
                'count' => (int) $r->count,
            ];
        }
    }

    public function media(array $filters = []): iterable
    {
        $query = $this->postQuery('attachment', $filters, ['inherit', 'publish', 'private']);

        foreach ($this->chunked($query, $filters['limit'] ?? null) as $chunk) {
            $meta = $this->meta($chunk->pluck('ID')->all());
            $langs = $this->objectTaxonomy($chunk->pluck('ID')->all(), 'language');
            foreach ($chunk as $p) {
                $file = $meta[$p->ID]['_wp_attached_file'] ?? null;
                $uploads = config('cms.wordpress.uploads_path');
                yield [
                    'id' => (int) $p->ID,
                    'url' => $file ? $this->baseUrl.'/wp-content/uploads/'.$file : $p->guid,
                    'file' => $file && $uploads ? rtrim($uploads, '/').'/'.$file : null,
                    'title' => html_entity_decode($p->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'alt' => $meta[$p->ID]['_wp_attachment_image_alt'] ?? null,
                    'caption' => $p->post_excerpt ?: null,
                    'description' => $p->post_content ?: null,
                    'mime' => $p->post_mime_type,
                    'date_gmt' => $p->post_date_gmt,
                    'modified_gmt' => $p->post_modified_gmt,
                    'lang' => $langs[$p->ID] ?? null,
                ];
            }
        }
    }

    public function posts(string $type, array $filters = []): iterable
    {
        $siteName = $this->option('blogname');
        $query = $this->postQuery($type, $filters, self::STATUSES);

        if (! empty($filters['locale'])) {
            $query->whereIn('ID', $this->idsInLanguage($filters['locale']));
        }

        foreach ($this->chunked($query, $filters['limit'] ?? null) as $chunk) {
            $ids = $chunk->pluck('ID')->all();
            $meta = $this->meta($ids);
            $langs = $this->objectTaxonomy($ids, 'language');
            $groups = $this->groups($ids, 'post_translations');
            $categories = $this->relationships($ids, 'category');
            $tags = $this->relationships($ids, 'post_tag');

            foreach ($chunk as $p) {
                $m = $meta[$p->ID] ?? [];
                $lang = $langs[$p->ID] ?? null;
                $title = html_entity_decode($p->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $elementor = ($m['_elementor_edit_mode'] ?? null) === 'builder' && ! empty($m['_elementor_data'])
                    ? json_decode($m['_elementor_data'], true) : null;
                $link = $this->permalink($p, $lang);

                yield [
                    'id' => (int) $p->ID,
                    'type' => $p->post_type,
                    'status' => $p->post_status,
                    'lang' => $lang,
                    'translations' => $groups[$p->ID] ?? [],
                    'parent' => (int) $p->post_parent,
                    'slug' => rawurldecode($p->post_name),
                    'title' => $title,
                    'excerpt' => $p->post_excerpt ?: null,
                    'content' => $p->post_content,
                    'content_format' => $elementor ? 'elementor' : 'raw',
                    'elementor' => $elementor,
                    'author' => (int) $p->post_author,
                    'date_gmt' => $p->post_date_gmt,
                    'modified_gmt' => $p->post_modified_gmt,
                    'featured_media' => (int) ($m['_thumbnail_id'] ?? 0),
                    'categories' => $categories[$p->ID] ?? [],
                    'tags' => $tags[$p->ID] ?? [],
                    'comment_status' => $p->comment_status,
                    'menu_order' => (int) $p->menu_order,
                    'link' => $link,
                    'template' => $m['_wp_page_template'] ?? '',
                    'seo' => SeoMapper::fromPostMeta($m, $title, $siteName, $link),
                    // JetEngine / custom fields: everything not internal (no leading underscore).
                    'meta' => array_filter($m, fn ($k) => ! str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY),
                ];
            }
        }
    }

    public function menus(): iterable
    {
        $locations = $this->menuLocations();

        $menus = $this->db->table($this->t('terms').' as t')
            ->join($this->t('term_taxonomy').' as tt', 'tt.term_id', '=', 't.term_id')
            ->where('tt.taxonomy', 'nav_menu')->get(['t.term_id', 't.name', 'tt.term_taxonomy_id']);

        foreach ($menus as $menu) {
            $itemIds = $this->db->table($this->t('term_relationships'))->where('term_taxonomy_id', $menu->term_taxonomy_id)->pluck('object_id')->all();
            $posts = $this->db->table($this->t('posts'))->whereIn('ID', $itemIds)->where('post_type', 'nav_menu_item')->where('post_status', 'publish')->orderBy('menu_order')->get();
            $meta = $this->meta($posts->pluck('ID')->all());
            [$location, $lang] = $locations[$menu->term_id] ?? [null, null];

            yield [
                'id' => (int) $menu->term_id,
                'name' => $menu->name,
                'location' => $location,
                'lang' => $lang,
                'items' => $posts->map(fn ($p) => [
                    'id' => (int) $p->ID,
                    'parent' => (int) ($meta[$p->ID]['_menu_item_menu_item_parent'] ?? 0),
                    'title' => html_entity_decode($p->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'url' => $meta[$p->ID]['_menu_item_url'] ?? null,
                    'type' => $meta[$p->ID]['_menu_item_type'] ?? 'custom',
                    'object' => $meta[$p->ID]['_menu_item_object'] ?? null,
                    'object_id' => (int) ($meta[$p->ID]['_menu_item_object_id'] ?? 0),
                    'target' => $meta[$p->ID]['_menu_item_target'] ?? null,
                    'order' => (int) $p->menu_order,
                ])->all(),
            ];
        }
    }

    public function comments(array $filters = []): iterable
    {
        foreach ($this->db->table($this->t('comments'))->whereIn('comment_approved', ['0', '1'])->where('comment_type', '!=', 'pingback')->orderBy('comment_ID')->cursor() as $c) {
            yield [
                'id' => (int) $c->comment_ID,
                'post' => (int) $c->comment_post_ID,
                'parent' => (int) $c->comment_parent,
                'author_name' => $c->comment_author ?: 'Anonymous',
                'author_email' => $c->comment_author_email ?: null,
                'content' => $c->comment_content,
                'date_gmt' => $c->comment_date_gmt,
                'status' => $c->comment_approved === '1' ? 'approved' : 'pending',
            ];
        }
    }

    public function redirects(): iterable
    {
        // Yoast SEO Premium stores rules as [['origin', 'url', 'type', 'format'], ...].
        foreach (['wpseo-premium-redirects-base'] as $option) {
            foreach ((array) $this->unserialize($this->option($option)) as $i => $rule) {
                if (! is_array($rule) || empty($rule['origin'])) {
                    continue;
                }
                yield [
                    'id' => md5(($rule['format'] ?? 'plain').'|'.$rule['origin']),
                    'origin' => (string) $rule['origin'],
                    'target' => (string) ($rule['url'] ?? ''),
                    'status' => (int) ($rule['type'] ?? 301),
                    'format' => $rule['format'] ?? 'plain',
                ];
            }
        }
    }

    public function inventory(): array
    {
        $posts = $this->t('posts');
        $byType = $this->db->table($posts)->select('post_type', 'post_status', DB::raw('count(*) as c'))->groupBy('post_type', 'post_status')->get();
        $langOf = $this->languageTermTaxonomyIds();

        $byLang = collect($langOf)->mapWithKeys(fn ($ttId, $lang) => [$lang => $this->db->table($this->t('term_relationships').' as r')
            ->join($posts.' as p', 'p.ID', '=', 'r.object_id')
            ->where('r.term_taxonomy_id', $ttId)->whereIn('p.post_status', ['publish'])
            ->select('p.post_type', DB::raw('count(*) as c'))->groupBy('p.post_type')->pluck('c', 'post_type')]);

        $metaKeys = $this->db->table($this->t('postmeta').' as m')->join($posts.' as p', 'p.ID', '=', 'm.post_id')
            ->whereNotIn('p.post_type', ['post', 'page', 'attachment', 'revision', 'nav_menu_item'])
            ->select('p.post_type', 'm.meta_key', DB::raw('count(*) as c'))->groupBy('p.post_type', 'm.meta_key')->orderByDesc('c')->limit(300)->get();

        $shortcodes = [];
        $widgets = [];
        $this->db->table($posts)->whereIn('post_status', ['publish', 'draft', 'private'])->whereNotIn('post_type', ['revision', 'attachment', 'nav_menu_item'])
            ->select('ID', 'post_content')->orderBy('ID')->chunk(500, function ($chunk) use (&$shortcodes) {
                foreach ($chunk as $p) {
                    preg_match_all('/\[([a-zA-Z][\w-]*)[\s\]\/]/', (string) $p->post_content, $m);
                    foreach ($m[1] as $code) {
                        $shortcodes[$code] = ($shortcodes[$code] ?? 0) + 1;
                    }
                }
            });
        $this->db->table($this->t('postmeta'))->where('meta_key', '_elementor_data')->select('meta_id', 'meta_value')->orderBy('meta_id')->chunk(200, function ($chunk) use (&$widgets) {
            foreach ($chunk as $row) {
                preg_match_all('/"widgetType":"([\w-]+)"/', (string) $row->meta_value, $m);
                foreach ($m[1] as $w) {
                    $widgets[$w] = ($widgets[$w] ?? 0) + 1;
                }
            }
        });
        arsort($shortcodes);
        arsort($widgets);

        return [
            'source' => 'db',
            'site' => ['name' => $this->option('blogname'), 'url' => $this->option('siteurl'), 'db_version' => $this->option('db_version'), 'template' => $this->option('stylesheet')],
            'active_plugins' => array_values((array) $this->unserialize($this->option('active_plugins'))),
            'counts_by_status' => $byType->groupBy('post_type')->map(fn (Collection $rows) => $rows->pluck('c', 'post_status'))->all(),
            'published_by_language' => $byLang->all(),
            'custom_field_keys' => $metaKeys->groupBy('post_type')->map(fn ($rows) => $rows->pluck('c', 'meta_key'))->all(),
            'shortcodes' => $shortcodes,
            'elementor_widgets' => $widgets,
            'users' => $this->db->table($this->t('users'))->count(),
            'comments' => $this->db->table($this->t('comments'))->select('comment_approved', DB::raw('count(*) as c'))->groupBy('comment_approved')->pluck('c', 'comment_approved'),
        ];
    }

    // ---------------------------------------------------------------------------------------------

    private function postQuery(string $type, array $filters, array $statuses)
    {
        return $this->db->table($this->t('posts'))
            ->where('post_type', $type)
            ->whereIn('post_status', $statuses)
            ->when($filters['since'] ?? null, fn ($q, $since) => $q->where('post_modified_gmt', '>=', $since))
            ->when($filters['id'] ?? null, fn ($q, $id) => $q->where('ID', $id))
            ->orderBy('ID');
    }

    /** @return iterable<Collection> */
    private function chunked($query, ?int $limit): iterable
    {
        $lastId = 0;
        $seen = 0;
        while (true) {
            $size = $limit ? min(200, $limit - $seen) : 200;
            if ($size <= 0) {
                return;
            }
            $chunk = (clone $query)->where('ID', '>', $lastId)->limit($size)->get();
            if ($chunk->isEmpty()) {
                return;
            }
            yield $chunk;
            $seen += $chunk->count();
            $lastId = $chunk->last()->ID;
        }
    }

    /** @return array<int, array<string, string>> post id => meta key => value (first value wins) */
    private function meta(array $ids): array
    {
        $out = [];
        if (! $ids) {
            return $out;
        }
        foreach ($this->db->table($this->t('postmeta'))->whereIn('post_id', $ids)->orderBy('meta_id')->get(['post_id', 'meta_key', 'meta_value']) as $row) {
            $out[$row->post_id][$row->meta_key] ??= $row->meta_value;
        }

        return $out;
    }

    /** object id => term slug (optionally transformed) for a single-valued taxonomy like "language". */
    private function objectTaxonomy(array $ids, string $taxonomy, ?callable $map = null): array
    {
        if (! $ids) {
            return [];
        }

        return $this->db->table($this->t('term_relationships').' as r')
            ->join($this->t('term_taxonomy').' as tt', 'tt.term_taxonomy_id', '=', 'r.term_taxonomy_id')
            ->join($this->t('terms').' as t', 't.term_id', '=', 'tt.term_id')
            ->where('tt.taxonomy', $taxonomy)->whereIn('r.object_id', $ids)
            ->pluck('t.slug', 'r.object_id')
            ->map(fn ($slug) => $map ? $map($slug) : $slug)
            ->all();
    }

    /** object id => [lang => id] from Polylang translation-group terms. */
    private function groups(array $ids, string $taxonomy): array
    {
        if (! $ids) {
            return [];
        }

        $rows = $this->db->table($this->t('term_relationships').' as r')
            ->join($this->t('term_taxonomy').' as tt', 'tt.term_taxonomy_id', '=', 'r.term_taxonomy_id')
            ->where('tt.taxonomy', $taxonomy)->whereIn('r.object_id', $ids)
            ->get(['r.object_id', 'tt.description']);

        $out = [];
        foreach ($rows as $row) {
            $group = $this->unserialize($row->description);
            if (is_array($group)) {
                $out[$row->object_id] = array_map('intval', array_filter($group, 'is_numeric'));
            }
        }

        return $out;
    }

    /** post id => [term ids] */
    private function relationships(array $ids, string $taxonomy): array
    {
        if (! $ids) {
            return [];
        }

        return $this->db->table($this->t('term_relationships').' as r')
            ->join($this->t('term_taxonomy').' as tt', 'tt.term_taxonomy_id', '=', 'r.term_taxonomy_id')
            ->where('tt.taxonomy', $taxonomy)->whereIn('r.object_id', $ids)
            ->get(['r.object_id', 'tt.term_id'])
            ->groupBy('object_id')->map(fn ($rows) => $rows->pluck('term_id')->map(fn ($id) => (int) $id)->all())->all();
    }

    private function idsInLanguage(string $locale): array
    {
        $ttId = $this->languageTermTaxonomyIds()[$locale] ?? 0;

        return $this->db->table($this->t('term_relationships'))->where('term_taxonomy_id', $ttId)->pluck('object_id')->all();
    }

    private function languageTermTaxonomyIds(): array
    {
        return $this->languageTerms ??= $this->db->table($this->t('term_taxonomy').' as tt')
            ->join($this->t('terms').' as t', 't.term_id', '=', 'tt.term_id')
            ->where('tt.taxonomy', 'language')->pluck('tt.term_taxonomy_id', 't.slug')->all();
    }

    /** menu term id => [location, lang], from theme mods and Polylang's per-language locations. */
    private function menuLocations(): array
    {
        $theme = $this->option('stylesheet');
        $out = [];

        foreach ((array) ($this->unserialize($this->option("theme_mods_{$theme}"))['nav_menu_locations'] ?? []) as $location => $menuId) {
            $out[(int) $menuId] = [$location, Locales::default()];
        }
        foreach ((array) ($this->unserialize($this->option('polylang'))['nav_menus'][$theme] ?? []) as $location => $byLang) {
            foreach ((array) $byLang as $lang => $menuId) {
                $out[(int) $menuId] = [$location, $lang];
            }
        }

        return $out;
    }

    /** Approximate permalink (REST "link" is authoritative; DB mode assumes /%postname%/ + Polylang prefixes). */
    private function permalink(object $post, ?string $lang): string
    {
        $segments = [rawurldecode($post->post_name)];
        if ($post->post_type === 'page') {
            $parent = (int) $post->post_parent;
            for ($guard = 0; $parent && $guard < 10; $guard++) {
                $row = $this->db->table($this->t('posts'))->where('ID', $parent)->first(['post_name', 'post_parent']);
                if (! $row) {
                    break;
                }
                array_unshift($segments, rawurldecode($row->post_name));
                $parent = (int) $row->post_parent;
            }
        } elseif (! in_array($post->post_type, ['post', 'page'], true)) {
            array_unshift($segments, $post->post_type);
        }

        return $this->baseUrl.Locales::path($lang && Locales::isSupported($lang) ? $lang : Locales::default(), implode('/', $segments));
    }

    private function option(string $name): ?string
    {
        return $this->db->table($this->t('options'))->where('option_name', $name)->value('option_value');
    }

    private function unserialize(?string $value): mixed
    {
        if ($value === null || ! preg_match('/^[aOsibd]:/', $value)) {
            return $value;
        }

        return @unserialize($value, ['allowed_classes' => false]);
    }
}
