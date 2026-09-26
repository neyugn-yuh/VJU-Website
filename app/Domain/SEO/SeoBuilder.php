<?php

namespace App\Domain\SEO;

use App\Models\Content;
use App\Models\Media;
use App\Settings\SeoSettings;
use App\Settings\SiteSettings;
use App\Support\Locales;
use Illuminate\Support\Str;

/**
 * Builds the head metadata rendered server-side in resources/views/app.blade.php
 * (title, description, canonical, robots, Open Graph, Twitter, hreflang, JSON-LD).
 */
class SeoBuilder
{
    public function __construct(private readonly SeoSettings $seo, private readonly SiteSettings $site) {}

    public function forContent(Content $content, string $locale, array $alternates): array
    {
        $t = $content->translation($locale);
        $meta = $content->seo->firstWhere('locale', $locale);
        $image = $meta?->ogImage ?? $content->featuredMedia;
        $description = $meta?->meta_description ?: $this->summary($t->excerpt ?: $t->body);
        $url = absolute_url($t->url());
        $isArticle = $content->type->value === 'post';

        return $this->build($locale, [
            'title' => $meta?->meta_title ?: $t->title,
            'description' => $description,
            'keywords' => $meta?->meta_keywords,
            'canonical' => $meta?->canonical_url ?: $url,
            'robots' => [$meta?->robots_index ?? true, $meta?->robots_follow ?? true],
            'og_title' => $meta?->og_title,
            'og_description' => $meta?->og_description,
            'image' => $image,
            'type' => $isArticle ? 'article' : 'website',
            'alternates' => $alternates,
            'published' => $content->published_at?->toIso8601String(),
            'modified' => $content->updated_at?->toIso8601String(),
            'json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => $isArticle ? 'NewsArticle' : 'WebPage',
                'headline' => Str::limit($t->title, 110, ''),
                'url' => $url,
                'inLanguage' => config("cms.locales.$locale.hreflang"),
                ...($image ? ['image' => [absolute_url($image->url())]] : []),
                ...($isArticle ? [
                    'datePublished' => $content->published_at?->toIso8601String(),
                    'dateModified' => $content->updated_at?->toIso8601String(),
                    'publisher' => ['@type' => 'CollegeOrUniversity', 'name' => $this->siteName($locale), 'url' => absolute_url(Locales::path($locale))],
                ] : []),
            ],
        ]);
    }

    /** Listings, search, home, errors. */
    public function forPage(string $locale, ?string $title, ?string $description, string $path, array $alternates = [], bool $index = true): array
    {
        return $this->build($locale, [
            'title' => $title,
            'description' => $description,
            'canonical' => absolute_url($path),
            'robots' => [$index, true],
            'type' => 'website',
            'alternates' => $alternates,
            'json_ld' => $title === null ? [
                '@context' => 'https://schema.org',
                '@type' => 'CollegeOrUniversity',
                'name' => $this->siteName($locale),
                'url' => absolute_url(Locales::path($locale)),
            ] : null,
        ]);
    }

    private function build(string $locale, array $d): array
    {
        $site = $this->siteName($locale);
        $title = $d['title']
            ? str_replace(['%title%', '%site%'], [$d['title'], $site], $this->seo->title_template)
            : ($this->seo->default_title[$locale] ?? $site);
        $description = $d['description'] ?: ($this->seo->default_description[$locale] ?? null);
        $image = $d['image'] ?? ($this->seo->default_og_image_id ? Media::find($this->seo->default_og_image_id) : null);
        [$index, $follow] = $d['robots'];
        if ($this->seo->discourage_indexing) {
            $index = $follow = false;
        }

        $alternates = collect($d['alternates'])->map(fn ($a) => [
            'hreflang' => config("cms.locales.{$a['locale']}.hreflang"),
            'url' => absolute_url($a['url']),
        ])->values()->all();
        if ($default = collect($d['alternates'])->firstWhere('locale', Locales::default())) {
            $alternates[] = ['hreflang' => 'x-default', 'url' => absolute_url($default['url'])];
        }

        return [
            'title' => $title,
            'description' => $description ? Str::limit($description, 300) : null,
            'keywords' => $d['keywords'] ?? null,
            'canonical' => $d['canonical'],
            'robots' => ($index ? 'index' : 'noindex').','.($follow ? 'follow' : 'nofollow').($index ? ',max-image-preview:large' : ''),
            'og' => array_filter([
                'og:type' => $d['type'],
                'og:site_name' => $site,
                'og:title' => $d['og_title'] ?? null ?: ($d['title'] ?? $title),
                'og:description' => $d['og_description'] ?? null ?: $description,
                'og:url' => $d['canonical'],
                'og:locale' => config("cms.locales.$locale.og"),
                'og:image' => $image ? absolute_url($image->derivativeUrl('web')) : null,
                'og:image:alt' => $image?->alt,
                'article:published_time' => $d['published'] ?? null,
                'article:modified_time' => $d['modified'] ?? null,
            ]),
            'twitter' => ['twitter:card' => $image ? 'summary_large_image' : 'summary'],
            'alternates' => $alternates,
            'json_ld' => $d['json_ld'] ?? null,
        ];
    }

    private function siteName(string $locale): string
    {
        return $this->site->site_name[$locale] ?? $this->site->site_name[Locales::default()] ?? config('app.name');
    }

    private function summary(?string $html): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html))));

        return $text === '' ? null : Str::limit($text, 160);
    }
}
