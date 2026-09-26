<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Public-site cache with generation-based invalidation: flush() bumps the version so every
 * cached menu/settings/listing becomes stale at once, on any cache driver.
 */
class PublicCache
{
    public static function remember(string $key, int $seconds, Closure $callback): mixed
    {
        return Cache::remember('public:'.self::version().':'.$key, $seconds, $callback);
    }

    public static function flush(): void
    {
        Cache::forever('public:version', self::version() + 1);
    }

    private static function version(): int
    {
        return (int) Cache::rememberForever('public:version', fn () => 1);
    }
}
