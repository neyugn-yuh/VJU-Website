<?php

namespace App\Http\Controllers\Auth;

use App\Domain\User\GoogleOidcProvider;
use App\Domain\User\UserProvisioner;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 404);

        return $this->provider()->redirectWithNonce();
    }

    public function callback(Request $request, UserProvisioner $provisioner): RedirectResponse
    {
        try {
            $user = $provisioner->fromGoogleClaims($this->provider()->claims());
        } catch (AuthorizationException $e) {
            return $this->fail($e->getMessage());
        } catch (Throwable $e) {
            Log::channel('security')->warning('OIDC callback failed', ['error' => $e->getMessage()]);

            return $this->fail('Google sign-in failed. Please try again.');
        }

        if (! $user->roles()->exists()) {
            return $this->fail('Your account exists but has no CMS role yet. Ask an administrator to grant access.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect('/admin/login')->with('auth_error', $message);
    }

    private function provider(): GoogleOidcProvider
    {
        return app(GoogleOidcProvider::class);
    }
}
