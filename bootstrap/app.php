<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Public 4xx/5xx pages render through Inertia (resources/js/Pages/Error.tsx).
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            if (in_array($status, [403, 404, 410, 500, 503], true)
                && ! $request->is('admin*', 'livewire*', 'health')
                && ! $request->expectsJson()
                && ! config('app.debug')) {
                try {
                    return inertia('Error', ['status' => $status, 'latest' => [], 'alternates' => [], 'seo' => ['title' => (string) $status, 'robots' => 'noindex,nofollow']])
                        ->toResponse($request)->setStatusCode($status);
                } catch (Throwable) {
                    return $response; // e.g. database down: fall back to the framework's plain error page
                }
            }

            return $response;
        });
    })->create();
