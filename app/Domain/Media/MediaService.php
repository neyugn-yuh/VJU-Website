<?php

namespace App\Domain\Media;

use App\Domain\Audit\Audit;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Upload pipeline: detected MIME (never the extension) -> size -> SHA-256 duplicate check ->
 * storage under a non-overwriting name -> metadata -> queued derivative generation.
 */
class MediaService
{
    /** Set when the last store() call returned an existing record instead of a new upload. */
    public bool $lastWasDuplicate = false;

    /**
     * @param  UploadedFile|string  $file  uploaded file or local file path
     * @param  array<string, mixed>  $attributes  title, alt, caption, description, source_*, created_at...
     */
    public function store(UploadedFile|string $file, array $attributes = [], ?User $user = null, bool $reuseDuplicates = true): Media
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $originalName = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);

        if (! $path || ! is_file($path)) {
            throw ValidationException::withMessages(['file' => 'File not found.']);
        }

        $mime = $this->detectMime($path);
        $extension = config('cms.media.mimes')[$mime] ?? null;
        if (! $extension) {
            throw ValidationException::withMessages(['file' => "File type {$mime} is not allowed."]);
        }

        // SVG is stored only after active content (scripts, handlers, external refs) is stripped.
        $sanitizedSvg = $mime === 'image/svg+xml' ? app(SvgSanitizer::class)->sanitize((string) file_get_contents($path)) : null;
        if ($sanitizedSvg !== null) {
            $path = tempnam(sys_get_temp_dir(), 'svg');
            file_put_contents($path, $sanitizedSvg);
        }

        $size = filesize($path);
        if ($size > config('cms.media.max_size_kb') * 1024) {
            throw ValidationException::withMessages(['file' => 'File is too large.']);
        }

        $checksum = hash_file('sha256', $path);
        $this->lastWasDuplicate = false;
        if ($reuseDuplicates && $existing = Media::where('checksum', $checksum)->first()) {
            $this->lastWasDuplicate = true;

            return $existing;
        }

        $disk = config('cms.media.disk');
        $storedPath = $this->uniquePath($disk, $originalName, $extension, $attributes['created_at'] ?? null);
        $stream = fopen($path, 'r');
        Storage::disk($disk)->put($storedPath, $stream, ['visibility' => 'public']);
        if (is_resource($stream)) {
            fclose($stream);
        }

        [$width, $height] = str_starts_with($mime, 'image/') ? (@getimagesize($path) ?: [null, null]) : [null, null];
        if ($sanitizedSvg !== null) {
            @unlink($path);
        }

        $media = Media::create([
            'disk' => $disk,
            'path' => $storedPath,
            'filename' => basename($storedPath),
            'original_filename' => $originalName,
            'mime_type' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'title' => $attributes['title'] ?? pathinfo($originalName, PATHINFO_FILENAME),
            'checksum' => $checksum,
            'created_by' => $user?->id,
            ...collect($attributes)->only(['alt', 'caption', 'description', 'source_system', 'source_id', 'source_url', 'created_at'])->all(),
        ]);

        Audit::record('media_upload', $media, null, ['filename' => $media->filename, 'mime' => $mime, 'size' => $size], $user?->id);

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            GenerateMediaDerivatives::dispatch($media->id)->onQueue('media');
        }

        return $media;
    }

    public function detectMime(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';

        // SVG without an XML prolog is often reported as text; confirm by content, not extension.
        if (in_array($mime, ['text/plain', 'text/xml', 'application/xml', 'text/html'], true)
            && preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*<svg[\s>]/is', (string) file_get_contents($path, length: 4096))) {
            return 'image/svg+xml';
        }

        // Legacy Office files are often detected generically; accept only with a matching OLE signature.
        if ($mime === 'application/CDFV2' || $mime === 'application/x-ole-storage') {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            return match ($ext) {
                'xls' => 'application/vnd.ms-excel',
                'ppt' => 'application/vnd.ms-powerpoint',
                default => 'application/msword',
            };
        }

        return $mime;
    }

    private function uniquePath(string $disk, string $originalName, string $extension, mixed $date = null): string
    {
        // Year/month folders like WordPress; imported files keep their original upload month.
        $dir = 'media/'.($date ? Carbon::parse($date) : now())->format('Y/m');
        $base = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: Str::lower(Str::random(8));
        $base = Str::limit($base, 100, '');

        $candidate = "{$dir}/{$base}.{$extension}";
        for ($i = 2; Storage::disk($disk)->exists($candidate); $i++) {
            $candidate = "{$dir}/{$base}-{$i}.{$extension}";
        }

        return $candidate;
    }
}
