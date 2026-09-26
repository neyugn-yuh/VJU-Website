<?php

namespace App\Support;

/**
 * Single place for locale <-> URL logic. VI at "/", EN at "/en/", JA at "/ja/".
 * Public URLs keep WordPress' trailing-slash style so legacy URLs resolve as 200.
 */
class Locales
{
    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(config('cms.locales'));
    }

    public static function default(): string
    {
        return config('cms.default_locale');
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, config('cms.locales'));
    }

    public static function name(string $locale): string
    {
        return config("cms.locales.$locale.name", $locale);
    }

    public static function prefix(string $locale): string
    {
        return config("cms.locales.$locale.prefix", '');
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(config('cms.locales'))->map(fn ($l) => $l['name'])->all();
    }

    /** Relative URL for a locale + path (path without locale prefix, unencoded). */
    public static function path(string $locale, string $path = ''): string
    {
        $segments = array_filter([self::prefix($locale), ...explode('/', trim($path, '/'))], fn ($s) => $s !== '');

        return '/'.implode('/', array_map('rawurlencode', $segments)).($segments ? '/' : '');
    }

    public static function url(string $locale, string $path = ''): string
    {
        return rtrim(config('app.url'), '/').self::path($locale, $path);
    }

    /**
     * Split a decoded request path into [locale, remaining path].
     *
     * @return array{0: string, 1: string}
     */
    public static function split(string $path): array
    {
        $path = trim($path, '/');
        $first = explode('/', $path, 2)[0];

        foreach (config('cms.locales') as $code => $locale) {
            if ($locale['prefix'] !== '' && $first === $locale['prefix']) {
                return [$code, trim(substr($path, strlen($first)), '/')];
            }
        }

        return [self::default(), $path];
    }
}
