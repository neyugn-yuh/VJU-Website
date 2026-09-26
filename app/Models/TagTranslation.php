<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagTranslation extends Model
{
    protected $guarded = ['id'];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function url(): string
    {
        return Locales::path($this->locale, 'tag/'.$this->slug);
    }
}
