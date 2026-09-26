<?php

if (! function_exists('absolute_url')) {
    /** Absolute URL for a site-relative path; absolute URLs pass through. */
    function absolute_url(string $path): string
    {
        return preg_match('#^https?://#i', $path) ? $path : rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
