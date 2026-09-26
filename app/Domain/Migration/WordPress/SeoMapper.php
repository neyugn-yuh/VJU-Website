<?php

namespace App\Domain\Migration\WordPress;

/**
 * Yoast SEO (Premium) -> normalized SEO. Values equal to what the CMS would generate anyway are
 * dropped, so defaults keep following the templates instead of being frozen at import time.
 *
 * DB source meta keys (Yoast, confirmed during discovery): _yoast_wpseo_title, _yoast_wpseo_metadesc,
 * _yoast_wpseo_focuskw, _yoast_wpseo_canonical, _yoast_wpseo_opengraph-title,
 * _yoast_wpseo_opengraph-description, _yoast_wpseo_opengraph-image,
 * _yoast_wpseo_meta-robots-noindex ("1" = noindex), _yoast_wpseo_meta-robots-nofollow ("1").
 */
class SeoMapper
{
    public const SEPARATORS = [' - ', ' | ', ' – ', ' — ', ' · ', ' » '];

    /** From REST "yoast_head_json" (already resolved by Yoast). */
    public static function fromYoastHead(array $y, string $postTitle, ?string $link): array
    {
        $robots = $y['robots'] ?? [];

        return self::clean([
            'title' => self::customTitle($y['title'] ?? null, $postTitle, $y['og_site_name'] ?? null),
            'description' => $y['description'] ?? null,
            'keywords' => null,
            'canonical' => self::customCanonical($y['canonical'] ?? null, $link),
            'og_title' => ($y['og_title'] ?? null) !== ($y['title'] ?? null) ? self::customTitle($y['og_title'] ?? null, $postTitle, $y['og_site_name'] ?? null) : null,
            // Without a custom meta description Yoast fills og:description with the excerpt; keep only explicit ones.
            'og_description' => isset($y['description']) && ($y['og_description'] ?? null) !== $y['description'] ? ($y['og_description'] ?? null) : null,
            'og_image' => $y['og_image'][0]['url'] ?? null,
            'noindex' => ($robots['index'] ?? 'index') === 'noindex',
            'nofollow' => ($robots['follow'] ?? 'follow') === 'nofollow',
        ]);
    }

    /** From raw postmeta (DB source). */
    public static function fromPostMeta(array $meta, string $postTitle, ?string $siteName, ?string $link): array
    {
        $resolve = fn (?string $v) => $v === null ? null : trim(str_replace(
            ['%%title%%', '%%sitename%%', '%%sep%%', '%%page%%', '%%primary_category%%'],
            [$postTitle, (string) $siteName, '-', '', ''],
            $v
        ));

        return self::clean([
            'title' => self::customTitle($resolve($meta['_yoast_wpseo_title'] ?? null), $postTitle, $siteName),
            'description' => $resolve($meta['_yoast_wpseo_metadesc'] ?? null),
            'keywords' => $meta['_yoast_wpseo_focuskw'] ?? null,
            'canonical' => self::customCanonical($meta['_yoast_wpseo_canonical'] ?? null, $link),
            'og_title' => $resolve($meta['_yoast_wpseo_opengraph-title'] ?? null),
            'og_description' => $resolve($meta['_yoast_wpseo_opengraph-description'] ?? null),
            'og_image' => $meta['_yoast_wpseo_opengraph-image'] ?? null,
            'noindex' => ($meta['_yoast_wpseo_meta-robots-noindex'] ?? '0') === '1',
            'nofollow' => ($meta['_yoast_wpseo_meta-robots-nofollow'] ?? '0') === '1',
        ]);
    }

    /** Null when the title is just "post title{sep}site name" (the CMS default template). */
    public static function customTitle(?string $title, string $postTitle, ?string $siteName): ?string
    {
        $title = trim(html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($title === '' || $title === $postTitle) {
            return null;
        }

        foreach (self::SEPARATORS as $sep) {
            if ($siteName && str_ends_with($title, $sep.$siteName)) {
                $title = substr($title, 0, -strlen($sep.$siteName));
                break;
            }
        }

        return $title === $postTitle ? null : $title;
    }

    private static function customCanonical(?string $canonical, ?string $link): ?string
    {
        if (! $canonical || ! $link) {
            return $canonical ?: null;
        }

        return rtrim(rawurldecode($canonical), '/') === rtrim(rawurldecode($link), '/') ? null : $canonical;
    }

    private static function clean(array $seo): array
    {
        return array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v, $seo);
    }
}
