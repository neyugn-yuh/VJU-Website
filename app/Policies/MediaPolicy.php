<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('media.view');
    }

    public function view(User $user, Media $media): bool
    {
        return $user->can('media.view');
    }

    public function create(User $user): bool
    {
        return $user->can('media.upload');
    }

    /** Uploaders may edit metadata of their own files. */
    public function update(User $user, Media $media): bool
    {
        return $user->can('media.update') || ($user->can('media.upload') && $media->created_by === $user->id);
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->can('media.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('media.delete');
    }

    public function restore(User $user, Media $media): bool
    {
        return $user->can('media.delete');
    }

    public function forceDelete(User $user, Media $media): bool
    {
        return $user->can('media.delete') && $user->can('content.forceDelete');
    }
}
