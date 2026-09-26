<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Content\ContentStatus;
use App\Domain\Content\ContentType;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ContentType $type
 * @property ContentStatus $status
 */
class Content extends Model
{
    use Auditable, SoftDeletes;

    /** Create/update/publish are audited by ContentService with full context. */
    protected array $auditEvents = ['deleted', 'restored'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'is_commentable' => false, 'is_featured' => false, 'menu_order' => 0, 'view_count' => 0];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'is_commentable' => 'boolean',
            'is_featured' => 'boolean',
            'fields' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Keep soft-delete and "trash" status consistent whichever path (UI action, bulk, service) is used.
        static::deleting(function (Content $content) {
            if (! $content->isForceDeleting()) {
                $content->status = ContentStatus::Trash;
                $content->saveQuietly();
            }
        });

        static::restoring(function (Content $content) {
            $content->status = ContentStatus::Draft;
        });
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ContentTranslation::class);
    }

    public function translation(?string $locale = null): ?ContentTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale);
    }

    /** Translation for display in admin: current locale, else default, else any. */
    public function displayTranslation(): ?ContentTranslation
    {
        return $this->translation(app()->getLocale())
            ?? $this->translation(Locales::default())
            ?? $this->translations->first();
    }

    public function getTitleAttribute(): string
    {
        return $this->displayTranslation()->title ?? '#'.$this->id;
    }

    public function seo(): HasMany
    {
        return $this->hasMany(SeoMeta::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'content_category');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class)->orderByDesc('version');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(ContentDraft::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function scopeOfType(Builder $query, ContentType $type): void
    {
        $query->where('type', $type->value);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published->value)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published && (! $this->published_at || $this->published_at->isPast());
    }

    public function url(?string $locale = null): ?string
    {
        $translation = $this->translation($locale ?? app()->getLocale());

        return $translation ? Locales::path($translation->locale, $translation->path) : null;
    }
}
