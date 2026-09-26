<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends PermissionPolicy
{
    protected string $permission = 'users.manage';

    /** Nobody deletes their own account (prevents locking out the last admin by accident). */
    public function delete(User $user, Model $model): bool
    {
        return $user->can('users.manage') && ! $user->is($model);
    }
}
