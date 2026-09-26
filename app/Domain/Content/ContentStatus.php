<?php

namespace App\Domain\Content;

enum ContentStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Private = 'private';
    case Trash = 'trash';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending review',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Private => 'Private',
            self::Trash => 'Trash',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingReview => 'warning',
            self::Scheduled => 'info',
            self::Published => 'success',
            self::Private => 'primary',
            self::Trash => 'danger',
        };
    }

    /** Statuses that make content visible or committed to go live; require publish rights. */
    public function isLive(): bool
    {
        return in_array($this, [self::Published, self::Scheduled, self::Private], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
