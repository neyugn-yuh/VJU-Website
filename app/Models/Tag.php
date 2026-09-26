<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function translations(): HasMany
    {
        return $this->hasMany(TagTranslation::class);
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class);
    }

    public function translation(?string $locale = null): ?TagTranslation
    {
        return $this->translations->firstWhere('locale', $locale ?? app()->getLocale());
    }

    public function getNameAttribute(): string
    {
        $t = $this->translation() ?? $this->translation(Locales::default()) ?? $this->translations->first();

        return $t->name ?? $this->slug;
    }
}
