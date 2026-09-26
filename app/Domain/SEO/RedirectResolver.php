<?php

namespace App\Domain\SEO;

use App\Models\Redirect;

class RedirectResolver
{
    /**
     * Canonical lookup key for a URL: path only, percent-decoded, lower-case, leading slash,
     * no trailing slash, no query/fragment. "/EN/Foo/?a=1" and "https://vju.ac.vn/en/foo" match.
     */
    public static function normalize(?string $url): string
    {
        $path = parse_url((string) $url, PHP_URL_PATH) ?: '/';
        // NFC: WordPress stored some Vietnamese slugs decomposed (e + combining tilde).
        $path = mb_strtolower(self::nfc(rawurldecode($path)));
        $path = '/'.trim(preg_replace('#/+#', '/', $path), '/');

        return $path;
    }

    public static function nfc(string $value): string
    {
        return \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
    }

    public static function find(string $url): ?Redirect
    {
        return Redirect::where('old_url', self::normalize($url))->first();
    }

    /** A URL that is live again (new content claimed it) must not keep redirecting away. */
    public static function clear(string $url): void
    {
        Redirect::where('old_url', self::normalize($url))->delete();
    }

    /** Record old -> new (301), keeping the table free of chains and loops. */
    public static function record(string $old, string $new, string $source = 'auto'): void
    {
        $oldKey = self::normalize($old);
        $newKey = self::normalize($new);

        if ($oldKey === $newKey) {
            return;
        }

        // The new URL is live again: a redirect away from it would now be a loop.
        Redirect::where('old_url', $newKey)->where('status_code', '!=', 410)->delete();

        // Collapse chains: anything pointing at the old URL now points straight at the new one.
        Redirect::whereIn('new_url', [$old, rtrim($old, '/'), $old.'/'])->update(['new_url' => $new]);

        Redirect::updateOrCreate(['old_url' => $oldKey], ['new_url' => $new, 'status_code' => 301, 'source' => $source]);
    }
}
