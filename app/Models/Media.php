<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        // Files are removed only on hard delete; soft-deleted media stays restorable.
        static::forceDeleted(function (Media $media) {
            $disk = Storage::disk($media->disk);
            $disk->delete([$media->path, ...array_column($media->metadata['derivatives'] ?? [], 'path')]);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /** URL of a derivative (thumb, web, webp, avif...), falling back to the original. */
    public function derivativeUrl(string $name): string
    {
        $path = $this->metadata['derivatives'][$name]['path'] ?? null;

        return $path ? Storage::disk($this->disk)->url($path) : $this->url();
    }

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        $d = $this->metadata['derivatives'] ?? [];

        return [
            'url' => isset($d['web']) ? $this->derivativeUrl('web') : $this->url(),
            'original' => $this->url(),
            'alt' => $this->alt ?: $this->title,
            'width' => $this->width,
            'height' => $this->height,
            'thumb' => $this->derivativeUrl('thumb'),
            'webp' => isset($d['webp']) ? $this->derivativeUrl('webp') : null,
            'avif' => isset($d['avif']) ? $this->derivativeUrl('avif') : null,
            'caption' => $this->caption,
        ];
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $i = 0;
        while ($size >= 1024 && $i < 3) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1).' '.$units[$i];
    }
}
