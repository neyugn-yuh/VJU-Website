<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Resources guarded by a single capability permission. */
abstract class PermissionPolicy
{
    protected string $permission;

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission);
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->permission);
    }

    public function create(User $user): bool
    {
        return $user->can($this->permission);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->permission);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->permission);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can($this->permission);
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->can($this->permission);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $user->can($this->permission);
    }

    public function reorder(User $user): bool
    {
        return $user->can($this->permission);
    }
}
