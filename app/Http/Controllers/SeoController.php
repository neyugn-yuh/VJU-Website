<?php

namespace App\Http\Controllers;

use App\Domain\Content\ContentType;
use App\Models\CategoryTranslation;
use App\Models\Content;
use App\Settings\SeoSettings;
use App\Support\Locales;
use App\Support\PublicCache;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    private const GROUPS = ['pages', 'posts', 'content', 'categories'];

    public function robots(SeoSettings $seo): Response
    {
        $lines = $seo->discourage_indexing
            ? ['User-agent: *', 'Disallow: /']
            : ['User-agent: *', 'Disallow: /admin', 'Disallow: /livewire', 'Disallow: /preview', 'Disallow: /search', 'Disallow: /*?s=', '', 'Sitemap: '.absolute_url('/sitemap.xml')];

        if ($seo->robots_extra) {
            $lines[] = '';
            $lines[] = trim($seo->robots_extra);
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function index(): Response
    {
        $xml = PublicCache::remember('sitemap:index', 3600, function () {
            $entries = '';
            foreach (self::GROUPS as $group) {
                foreach (Locales::codes() as $locale) {
                    $entries .= '<sitemap><loc>'.e(absolute_url("/sitemap-{$group}-{$locale}.xml")).'</loc></sitemap>';
                }
            }

            return '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$entries.'</sitemapindex>';
        });

        return $this->xml($xml);
    }

    public function sitemap(string $group, string $locale): Response
    {
        abort_unless(in_array($group, self::GROUPS, true) && Locales::isSupported($locale), 404);

        $xml = PublicCache::remember("sitemap:$group:$locale", 3600, fn () => $group === 'categories'
            ? $this->categories($locale)
            : $this->contents($group, $locale));

        return $this->xml($xml);
    }

    private function contents(string $group, string $locale): string
    {
        $types = match ($group) {
            'pages' => [ContentType::Page->value],
            'posts' => [ContentType::Post->value],
            default => collect(ContentType::cases())->filter->hasArchive()->map->value->values()->all(),
        };

        $out = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

        Content::published()->whereIn('type', $types)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            // Pages/posts marked noindex must not be listed.
            ->whereDoesntHave('seo', fn ($q) => $q->where('locale', $locale)->where('robots_index', false))
            ->with(['translations:id,content_id,locale,path', 'featuredMedia'])
            ->orderBy('id')
            ->chunk(500, function ($contents) use (&$out, $locale) {
                foreach ($contents as $content) {
                    $t = $content->translation($locale);
                    $out .= '<url><loc>'.e(absolute_url($t->url())).'</loc><lastmod>'.$content->updated_at->toAtomString().'</lastmod>';
                    foreach ($content->translations as $alt) {
                        $out .= '<xhtml:link rel="alternate" hreflang="'.e(config("cms.locales.{$alt->locale}.hreflang")).'" href="'.e(absolute_url($alt->url())).'"/>';
                    }
                    if ($content->featuredMedia) {
                        $out .= '<image:image><image:loc>'.e(absolute_url($content->featuredMedia->url())).'</image:loc></image:image>';
                    }
                    $out .= '</url>';
                }
            });

        return $out.'</urlset>';
    }

    private function categories(string $locale): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (CategoryTranslation::where('locale', $locale)->orderBy('path')->cursor() as $t) {
            $out .= '<url><loc>'.e(absolute_url($t->url())).'</loc></url>';
        }

        return $out.'</urlset>';
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
