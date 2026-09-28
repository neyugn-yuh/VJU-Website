<?php

use App\Domain\SEO\RedirectResolver;
use App\Models\CategoryTranslation;
use App\Models\ContentTranslation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WordPress (Polylang) stored English and Japanese menu items and body links as "/academics/introduce/"
 * and resolved them per language at runtime. Here those paths hit the Vietnamese site and 404. Prefix
 * such links with the locale (or /en/, which the old Japanese menu also linked to) when only that
 * version exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The English menu still links the page's old slug; vju.ac.vn redirects it the same way.
        if (ContentTranslation::query()->where('locale', 'en')->where('path', 'collaboration/collaboration-with-us')->exists()) {
            RedirectResolver::record('/en/collaboration/partner-with-us/', '/en/collaboration/collaboration-with-us/', 'wp');
        }

        foreach (['en', 'ja'] as $locale) {
            $menuIds = DB::table('menus')->where('locale', $locale)->pluck('id');

            foreach (DB::table('menu_items')->whereIn('menu_id', $menuIds)->where('url', 'like', '/%')->get(['id', 'url']) as $item) {
                if (($url = $this->localized($item->url, $locale)) !== $item->url) {
                    DB::table('menu_items')->where('id', $item->id)->update(['url' => $url]);
                }
            }

            foreach (ContentTranslation::query()->where('locale', $locale)->where('body', 'like', '%href="/%')->get() as $translation) {
                $body = preg_replace_callback('/href="(\/[^"]*)"/', fn (array $m): string => 'href="'.$this->localized($m[1], $locale).'"', $translation->body);

                if ($body !== $translation->body) {
                    $translation->forceFill(['body' => $body])->saveQuietly();
                }
            }
        }
    }

    private function localized(string $url, string $locale): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path === '' || preg_match('#^(en|ja|storage|upload_images|wp-content)(/|$)#', $path) || $this->resolves('vi', $path)) {
            return $url;
        }

        foreach (array_unique([$locale, 'en']) as $prefix) {
            if ($this->resolves($prefix, $path)) {
                return "/{$prefix}/{$path}/".substr($url, strlen((string) parse_url($url, PHP_URL_PATH)));
            }
        }

        return $url;
    }

    private function resolves(string $locale, string $path): bool
    {
        return ContentTranslation::query()->where('locale', $locale)->where('path', $path)->exists()
            || CategoryTranslation::query()->where('locale', $locale)->where('path', $path)->exists()
            || RedirectResolver::find($locale === 'vi' ? "/{$path}/" : "/{$locale}/{$path}/") !== null;
    }

    public function down(): void
    {
        // Rewritten links point at pages that exist; restoring the 404ing paths would not help anyone.
    }
};
