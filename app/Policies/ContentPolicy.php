<?php

namespace App\Policies;

use App\Domain\Content\ContentStatus;
use App\Models\Content;
use App\Models\User;

/**
 * Capability (Spatie permission) + ownership. Status transitions are checked by ContentWorkflow.
 */
class ContentPolicy
{
    private function owns(User $user, Content $content): bool
    {
        return $content->author_id !== null && $content->author_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->canAny(['content.viewAny', 'content.viewOwn']);
    }

    public function view(User $user, Content $content): bool
    {
        return $user->can('content.viewAny') || ($user->can('content.viewOwn') && $this->owns($user, $content));
    }

    public function create(User $user): bool
    {
        return $user->can('content.create');
    }

    public function update(User $user, Content $content): bool
    {
        if ($content->trashed()) {
            return false;
        }

        if ($user->can('content.update')) {
            return true;
        }

        if (! $user->can('content.updateOwn') || ! $this->owns($user, $content)) {
            return false;
        }

        // Users who cannot publish (contributors) lose edit rights once content is live.
        return ! $content->status->isLive() || $this->publish($user, $content);
    }

    public function publish(User $user, Content $content): bool
    {
        return $user->can('content.publish') || ($user->can('content.publishOwn') && $this->owns($user, $content));
    }

    public function schedule(User $user, Content $content): bool
    {
        return $user->can('content.schedule') && $this->publish($user, $content);
    }

    /** Move to trash (soft delete). */
    public function delete(User $user, Content $content): bool
    {
        if ($user->can('content.delete')) {
            return true;
        }

        return $user->can('content.deleteOwn') && $this->owns($user, $content)
            && (! $content->status->isLive() || $this->publish($user, $content));
    }

    public function deleteAny(User $user): bool
    {
        return $user->canAny(['content.delete', 'content.deleteOwn']);
    }

    public function restore(User $user, Content $content): bool
    {
        return $user->can('content.restore');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('content.restore');
    }

    /** Permanent delete. */
    public function forceDelete(User $user, Content $content): bool
    {
        return $user->can('content.forceDelete') && ($content->trashed() || $content->status === ContentStatus::Trash);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('content.forceDelete');
    }

    /** Reassigning authorship is an editorial capability, never an own-content one. */
    public function assignAuthor(User $user): bool
    {
        return $user->can('content.update');
    }
}
