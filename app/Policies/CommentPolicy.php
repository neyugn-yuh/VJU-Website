<?php

namespace App\Policies;

use App\Models\User;

class CommentPolicy extends PermissionPolicy
{
    protected string $permission = 'comments.moderate';

    /** Comments are created by the public site, never in the admin. */
    public function create(User $user): bool
    {
        return false;
    }
}
