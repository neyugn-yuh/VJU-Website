<?php

namespace App\Domain\User;

use Illuminate\Support\Str;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use RuntimeException;

/**
 * Google as an OpenID Connect provider: authorization-code flow with state + nonce, identity taken
 * from the ID token (signature via Google JWKS, issuer, audience, expiry, nonce) rather than
 * the userinfo endpoint.
 */
class GoogleOidcProvider extends GoogleProvider
{
    public const NONCE_KEY = 'oidc_nonce';

    public function redirectWithNonce()
    {
        $nonce = Str::random(40);
        $this->request->session()->put(self::NONCE_KEY, $nonce);

        return $this->with(['nonce' => $nonce, 'prompt' => 'select_account'])->redirect();
    }

    /** @return array<string, mixed> validated ID token claims */
    public function claims(): array
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException('Invalid OIDC state.');
        }

        $nonce = $this->request->session()->pull(self::NONCE_KEY);
        $response = $this->getAccessTokenResponse($this->getCode());
        $idToken = $response['id_token'] ?? null;

        if (! is_string($idToken)) {
            throw new RuntimeException('Token response contains no ID token.');
        }

        // Verifies signature against Google's JWKS, "iss", "aud" and "exp".
        $claims = $this->getUserFromJwtToken($idToken);

        if (! $nonce || ! hash_equals((string) $nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new RuntimeException('Invalid OIDC nonce.');
        }

        if (($claims['email_verified'] ?? false) !== true) {
            throw new RuntimeException('Google e-mail address is not verified.');
        }

        return $claims;
    }
}
