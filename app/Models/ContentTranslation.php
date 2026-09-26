<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentTranslation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['blocks' => 'array'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function url(): string
    {
        return Locales::path($this->locale, $this->path);
    }
}
