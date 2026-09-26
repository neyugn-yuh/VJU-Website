<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;

/**
 * Google OIDC is the production sign-in. The password form exists only when CMS_PASSWORD_LOGIN=true
 * (local/dev); otherwise it is not rendered and authenticate() refuses to run.
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        $components = [];

        if ($error = session('auth_error')) {
            $components[] = Html::make('<div role="alert" style="padding:.75rem;border-radius:.5rem;background:#fef2f2;color:#991b1b;font-size:.875rem">'.e($error).'</div>');
        }

        if (config('services.google.client_id')) {
            $components[] = Html::make(
                '<a href="'.e(route('auth.google')).'" style="display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.625rem;border:1px solid #d1d5db;border-radius:.5rem;font-weight:600">'
                .'<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.6 2.4 30.2 0 24 0 14.6 0 6.6 5.4 2.7 13.2l7.8 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4.1 7.1-10.1 7.1-17.5z"/><path fill="#FBBC05" d="M10.5 28.7c-.5-1.4-.8-3-.8-4.7s.3-3.3.8-4.7l-7.8-6.1C1 16.6 0 20.2 0 24s1 7.4 2.7 10.8l7.8-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.5-5.8c-2.1 1.4-4.9 2.3-8.4 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.8 6.1C6.6 42.6 14.6 48 24 48z"/></svg>'
                .'Sign in with Google</a>'
            );
        }

        if (config('cms.auth.password_login')) {
            $components = [...$components, $this->getEmailFormComponent(), $this->getPasswordFormComponent(), $this->getRememberFormComponent()];
        }

        return $schema->components($components);
    }

    protected function getFormActions(): array
    {
        return config('cms.auth.password_login') ? parent::getFormActions() : [];
    }

    public function authenticate(): ?LoginResponse
    {
        abort_unless(config('cms.auth.password_login'), 403);

        return parent::authenticate();
    }
}
