<?php

namespace App\Domain\Content;

/**
 * Content types. "post" and "page" are generic; the rest mirror the JetEngine CPTs found on the
 * current site during discovery (docs/discovery/README.md) and keep their legacy URL bases.
 */
enum ContentType: string
{
    case Post = 'post';
    case Page = 'page';
    case Document = 'document';
    case Notification = 'notification';
    case TuitionFee = 'tuition_fee';
    case Opportunity = 'opportunity';

    public function label(): string
    {
        return match ($this) {
            self::Post => 'Post',
            self::Page => 'Page',
            self::Document => 'Download document',
            self::Notification => 'Notification',
            self::TuitionFee => 'Tuition fee notice',
            self::Opportunity => 'Job opportunity',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Post => 'Posts',
            self::Page => 'Pages',
            self::Document => 'Download documents',
            self::Notification => 'Notifications',
            self::TuitionFee => 'Tuition fee notices',
            self::Opportunity => 'Job opportunities',
        };
    }

    /** URL prefix before the slug ("" = site root). Also the archive path for listing types. */
    public function urlBase(): string
    {
        return match ($this) {
            self::Post, self::Page => '',
            self::Document => 'download-documents',
            self::Notification => 'notification',
            self::TuitionFee => 'tuition-fees',
            self::Opportunity => 'current-opportunitie',
        };
    }

    public function isHierarchical(): bool
    {
        return $this === self::Page;
    }

    public function hasTaxonomy(): bool
    {
        return in_array($this, [self::Post, self::Document], true);
    }

    public function hasArchive(): bool
    {
        return ! in_array($this, [self::Post, self::Page], true);
    }

    public function wordpressType(): string
    {
        return match ($this) {
            self::Post => 'post',
            self::Page => 'page',
            self::Document => 'download-documents',
            self::Notification => 'notification',
            self::TuitionFee => 'tuition-fees',
            self::Opportunity => 'current-opportunitie',
        };
    }

    public static function fromWordpress(string $postType): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->wordpressType() === $postType) {
                return $case;
            }
        }

        return null;
    }

    public static function fromArchiveBase(string $base): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->hasArchive() && $case->urlBase() === $base) {
                return $case;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
