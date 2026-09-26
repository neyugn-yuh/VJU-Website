<?php

namespace App\Providers;

use App\Domain\Audit\Audit;
use App\Domain\User\GoogleOidcProvider;
use App\Models\User;
use App\Support\PublicCache;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use Spatie\LaravelSettings\Events\SavingSettings;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GoogleOidcProvider::class, fn () => Socialite::buildProvider(GoogleOidcProvider::class, config('services.google')));
    }

    public function boot(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('comments', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(30)->by($request->ip()),
        ]);
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        $this->registerAuditListeners();
    }

    private function registerAuditListeners(): void
    {
        Event::listen(Login::class, function (Login $event) {
            Audit::record('login', $event->user instanceof Model ? $event->user : null, null, ['guard' => $event->guard], $event->user->getAuthIdentifier());
            Log::channel('security')->info('login', ['user_id' => $event->user->getAuthIdentifier(), 'ip' => request()->ip()]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                Audit::record('logout', $event->user instanceof Model ? $event->user : null, null, null, $event->user->getAuthIdentifier());
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            // Credentials are never logged, only the attempted identifier.
            Audit::record('login_failed', null, null, ['email' => $event->credentials['email'] ?? null]);
            Log::channel('security')->warning('login_failed', ['email' => $event->credentials['email'] ?? null, 'ip' => request()->ip()]);
        });

        $roleChange = function (string $direction) {
            return function (RoleAttachedEvent|RoleDetachedEvent $event) use ($direction) {
                if (! $event->model instanceof User) {
                    return;
                }
                $ids = collect(is_iterable($event->rolesOrIds) ? $event->rolesOrIds : [$event->rolesOrIds])
                    ->map(fn ($r) => $r instanceof Role ? $r->id : $r);
                Audit::record('role_change', $event->model, null, [$direction => Role::whereIn('id', $ids)->pluck('name')->all()]);
            };
        };
        Event::listen(RoleAttachedEvent::class, $roleChange('attached'));
        Event::listen(RoleDetachedEvent::class, $roleChange('detached'));

        Event::listen(SavingSettings::class, function (SavingSettings $event) {
            Audit::record('setting_change', null, ['group' => $event->settings::group(), ...($event->originalValues?->all() ?? [])], $event->properties->all());
        });
        Event::listen(SettingsSaved::class, fn () => PublicCache::flush());
    }
}
