<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The admin UI language is independent of the public default locale (vi). */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = array_keys(config('cms.locales', ['vi' => [], 'en' => [], 'ja' => []]));

        $locale = $request->session()->get('admin_locale')
            ?? $request->cookie('admin_locale')
            ?? config('cms.admin_locale', 'en');

        if (! in_array($locale, $allowed, true)) {
            $locale = config('cms.admin_locale', 'en');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
