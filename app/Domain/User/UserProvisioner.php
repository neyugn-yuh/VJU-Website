<?php

namespace App\Domain\User;

use App\Domain\Audit\Audit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

/**
 * Maps validated OIDC claims to a CMS account. Never grants a role: new accounts
 * wait for an Admin to assign one.
 */
class UserProvisioner
{
    /** @param  array<string, mixed>  $claims */
    public function fromGoogleClaims(array $claims): User
    {
        $subject = (string) ($claims['sub'] ?? '');
        $email = Str::lower((string) ($claims['email'] ?? ''));

        if ($subject === '' || $email === '') {
            throw new AuthorizationException('Incomplete identity.');
        }

        $allowed = config('cms.auth.google_allowed_domains');
        $domain = (string) ($claims['hd'] ?? Str::after($email, '@'));
        if ($allowed && ! in_array(Str::lower($domain), array_map('strtolower', $allowed), true)) {
            Audit::record('login_denied', null, null, ['email' => $email, 'reason' => 'domain']);
            throw new AuthorizationException('This Google account is not allowed to access the CMS.');
        }

        $user = User::where('google_subject', $subject)->first();

        if (! $user && $user = User::where('email', $email)->first()) {
            if ($user->google_subject && $user->google_subject !== $subject) {
                throw new AuthorizationException('This e-mail is linked to a different Google identity.');
            }
            $user->forceFill(['google_subject' => $subject])->save();
        }

        if (! $user) {
            if (! config('cms.auth.google_auto_provision')) {
                Audit::record('login_denied', null, null, ['email' => $email, 'reason' => 'not_provisioned']);
                throw new AuthorizationException('No CMS account exists for this Google account.');
            }

            $user = new User(['name' => $claims['name'] ?? $email, 'email' => $email]);
            $user->forceFill(['google_subject' => $subject, 'email_verified_at' => now(), 'is_active' => true])->save();
        }

        if (! $user->is_active) {
            Audit::record('login_denied', $user, null, ['reason' => 'suspended'], $user->id);
            throw new AuthorizationException('This account is suspended.');
        }

        $user->forceFill(['avatar_url' => $claims['picture'] ?? $user->avatar_url, 'last_login_at' => now()])->save();

        return $user;
    }
}
