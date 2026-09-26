<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'content_category');
    }

    public function translation(?string $locale = null): ?CategoryTranslation
    {
        return $this->translations->firstWhere('locale', $locale ?? app()->getLocale());
    }

    public function getNameAttribute(): string
    {
        $t = $this->translation() ?? $this->translation(Locales::default()) ?? $this->translations->first();

        return $t->name ?? $this->slug;
    }

    /** @return list<int> this category and all of its descendants */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];
        while ($frontier) {
            $frontier = self::whereIn('parent_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    /** @return list<int> ancestors from root to direct parent */
    public function ancestorIds(): array
    {
        $ids = [];
        $parentId = $this->parent_id;
        while ($parentId && ! in_array($parentId, $ids, true)) {
            array_unshift($ids, $parentId);
            $parentId = self::whereKey($parentId)->value('parent_id');
        }

        return $ids;
    }
}
