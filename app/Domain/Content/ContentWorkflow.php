<?php

namespace App\Domain\Content;

use App\Domain\Content\ContentStatus as S;
use App\Models\Content;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Explicit status machine. The UI only offers what allowedTargets() returns, and ContentService
 * re-checks every transition server-side.
 */
class ContentWorkflow
{
    /** @var array<string, list<S>> valid graph edges, independent of who acts */
    private const EDGES = [
        'draft' => [S::PendingReview, S::Published, S::Scheduled, S::Private],
        'pending_review' => [S::Draft, S::Published, S::Scheduled, S::Private],
        'scheduled' => [S::Draft, S::Published],
        'published' => [S::Draft, S::Private],
        'private' => [S::Draft, S::Published],
        'trash' => [],
    ];

    public function isValidEdge(S $from, S $to): bool
    {
        return $from === $to || in_array($to, self::EDGES[$from->value], true);
    }

    public function can(User $user, Content $content, S $to): bool
    {
        $from = $content->exists ? $content->status : S::Draft;

        if (! $this->isValidEdge($from, $to)) {
            return false;
        }

        $gate = Gate::forUser($user);

        if (! $content->exists) {
            if (! $gate->allows('create', Content::class)) {
                return false;
            }
            // Ownership rules apply to the content being created by this user.
            $content = (clone $content)->forceFill(['author_id' => $content->author_id ?? $user->id]);
        } elseif ($from === $to) {
            return $gate->allows('update', $content);
        }

        return match ($to) {
            S::Draft, S::PendingReview => $from->isLive()
                ? $gate->allows('publish', $content)   // unpublishing
                : (! $content->exists || $gate->allows('update', $content)),
            S::Published, S::Private => $gate->allows('publish', $content),
            S::Scheduled => $gate->allows('schedule', $content),
            S::Trash => $gate->allows('delete', $content),
        };
    }

    /** @return array<string, string> status value => label, including the current status */
    public function allowedTargets(User $user, Content $content): array
    {
        return collect(S::cases())
            ->reject(fn (S $s) => $s === S::Trash)
            ->filter(fn (S $s) => $this->can($user, $content, $s))
            ->mapWithKeys(fn (S $s) => [$s->value => $s->label()])
            ->all();
    }
}
