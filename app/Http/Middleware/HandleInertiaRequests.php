<?php

namespace App\Http\Middleware;

use App\Domain\Menu\MenuService;
use App\Models\Media;
use App\Settings\AnalyticsSettings;
use App\Settings\ContactSettings;
use App\Settings\SiteSettings;
use App\Settings\SocialSettings;
use App\Support\Locales;
use App\Support\PublicCache;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Shared props are closures: they resolve after the controller has set the locale.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => fn () => app()->getLocale(),
            'locales' => fn () => collect(config('cms.locales'))->map(fn ($l, $code) => [
                'code' => $code, 'name' => $l['name'], 'short' => $l['short'], 'home' => Locales::path($code),
            ])->values(),
            't' => fn () => trans('public'),
            'site' => fn () => $this->site(app()->getLocale()),
            'menus' => fn () => collect(array_keys(config('cms.menu_locations')))
                ->mapWithKeys(fn ($loc) => [$loc => app(MenuService::class)->forLocation($loc, app()->getLocale())]),
            'flash' => fn () => ['success' => $request->session()->get('success'), 'error' => $request->session()->get('error')],
        ];
    }

    private function site(string $locale): array
    {
        return PublicCache::remember("site:$locale", 3600, function () use ($locale) {
            $site = app(SiteSettings::class);
            $contact = app(ContactSettings::class);
            $analytics = app(AnalyticsSettings::class);
            $default = Locales::default();

            return [
                'name' => $site->site_name[$locale] ?? $site->site_name[$default] ?? config('app.name'),
                'description' => $site->site_description[$locale] ?? null,
                'logo' => $site->logo_media_id ? Media::find($site->logo_media_id)?->url() : null,
                'contact' => [
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'address' => $contact->address[$locale] ?? $contact->address[$default] ?? null,
                    'map_url' => $contact->map_url,
                ],
                // Always a JSON object for the frontend, even when no network is configured.
                'social' => (object) array_filter((array) app(SocialSettings::class)->toArray()),
                'analytics' => ['ga' => $analytics->ga_measurement_id, 'gtm' => $analytics->gtm_container_id],
            ];
        });
    }
}
