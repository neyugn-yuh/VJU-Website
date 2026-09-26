<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Per-user autosave of the edit form, kept apart from the saved record. */
class ContentDraft extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
