<?php

namespace App\Domain\Media;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Web-sized + thumbnail + WebP (+ AVIF when GD supports it) derivatives. Re-encoding strips EXIF/GPS
 * metadata from the served copies; the original is kept untouched. Idempotent: re-running overwrites.
 */
class GenerateMediaDerivatives implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public int $mediaId) {}

    public function handle(): void
    {
        $media = Media::find($this->mediaId);
        if (! $media || ! $media->isImage()) {
            return;
        }

        $disk = Storage::disk($media->disk);
        $source = $disk->get($media->path);
        if ($source === null) {
            return;
        }

        $manager = ImageManager::gd();
        $dir = dirname($media->path).'/derivatives';
        $name = pathinfo($media->path, PATHINFO_FILENAME);
        $derivatives = [];

        foreach (config('cms.media.derivatives') as $label => $width) {
            $image = $manager->read($source)->scaleDown(width: $width);
            $encoded = $media->mime_type === 'image/png' ? $image->toPng() : $image->toJpeg(82);
            $ext = $media->mime_type === 'image/png' ? 'png' : 'jpg';
            $derivatives[$label] = $this->put($disk, "{$dir}/{$name}-{$label}.{$ext}", (string) $encoded, $image->width(), $image->height());

            if ($label === 'web') {
                $derivatives['webp'] = $this->put($disk, "{$dir}/{$name}-web.webp", (string) $image->toWebp(80), $image->width(), $image->height());

                if (function_exists('imageavif')) {
                    $derivatives['avif'] = $this->put($disk, "{$dir}/{$name}-web.avif", (string) $image->toAvif(60), $image->width(), $image->height());
                }
            }
        }

        $media->forceFill(['metadata' => [...($media->metadata ?? []), 'derivatives' => $derivatives]])->saveQuietly();
    }

    private function put($disk, string $path, string $contents, int $width, int $height): array
    {
        $disk->put($path, $contents, ['visibility' => 'public']);

        return ['path' => $path, 'width' => $width, 'height' => $height, 'size' => strlen($contents)];
    }
}
