<?php

namespace App\Console\Commands;

use App\Domain\Media\MediaService;
use App\Models\ContentTranslation;
use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Import image files exported by the crawler when the public WordPress media
 * endpoint did not expose every legacy upload.
 */
class WpImportCrawlImages extends Command
{
    protected $signature = 'wp:import-crawl-images
        {path : Directory containing the split crawl ZIP files}
        {--rewrite : Rewrite legacy image URLs in imported content}
        {--dry-run : Inspect the archive without writing media or content}';

    protected $description = 'Import crawled WordPress images and optionally rewrite legacy image URLs';

    /** @var array<string, int> */
    private array $urlIndex = [];

    /** @var array<string, Media> */
    private array $mediaCache = [];

    /** @var array<string, string> */
    private array $assetUrls = [];

    public function __construct(private readonly MediaService $media)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $directory = rtrim((string) $this->argument('path'), '\\/');
        $archives = glob($directory.DIRECTORY_SEPARATOR.'*.zip') ?: [];
        sort($archives);

        if ($archives === []) {
            $this->error("No ZIP files found in {$directory}.");

            return self::INVALID;
        }

        $this->buildUrlIndex();
        $stats = ['archives' => 0, 'assets' => 0, 'imported' => 0, 'existing' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($archives as $archivePath) {
            $zip = new ZipArchive;
            if ($zip->open($archivePath) !== true) {
                $this->warn('Could not open '.basename($archivePath));
                $stats['failed']++;

                continue;
            }

            $stats['archives']++;
            $metadata = json_decode((string) $zip->getFromName('metadata/images.json'), true);
            if (! is_array($metadata)) {
                $this->warn('Missing metadata/images.json in '.basename($archivePath));
                $zip->close();

                continue;
            }

            foreach ($metadata as $asset) {
                if (! is_array($asset)) {
                    continue;
                }

                $stats['assets']++;
                $url = trim((string) ($asset['url'] ?? ''));
                $filename = (string) ($asset['filename'] ?? '');
                if ($url === '' || $filename === '') {
                    $stats['skipped']++;

                    continue;
                }
                if (! $this->isLegacyImageUrl($url)) {
                    $stats['skipped']++;

                    continue;
                }

                $this->assetUrls[$url] = $url;
                $existingId = $this->findMediaId($url);
                if ($existingId) {
                    $this->assetUrls[$url] = (string) $existingId;
                    $stats['existing']++;

                    continue;
                }

                $entry = 'images/'.$filename;
                $stream = $zip->getStream($entry);
                if (! is_resource($stream)) {
                    $this->warn("Missing {$entry} in ".basename($archivePath));
                    $stats['failed']++;

                    continue;
                }

                $temporary = tempnam(sys_get_temp_dir(), 'vju-crawl-');
                $output = $temporary ? fopen($temporary, 'wb') : false;
                if (! $output) {
                    fclose($stream);
                    $stats['failed']++;

                    continue;
                }
                stream_copy_to_stream($stream, $output);
                fclose($output);
                fclose($stream);

                try {
                    $mime = $this->media->detectMime($temporary);
                    if (! array_key_exists($mime, config('cms.media.mimes', []))) {
                        $stats['skipped']++;

                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $stats['imported']++;

                        continue;
                    }

                    $media = $this->media->store($temporary, [
                        'title' => pathinfo($filename, PATHINFO_FILENAME),
                        'alt' => $asset['alt'] ?? null,
                        'caption' => $asset['caption'] ?? null,
                        'description' => $asset['description'] ?? null,
                        'source_system' => 'wordpress',
                        'source_id' => 'crawl_'.sha1($url),
                        'source_url' => $url,
                        'created_at' => $this->uploadDate($url),
                    ]);

                    // MediaService reuses an identical checksum. Keep the
                    // crawl URL as an alias in the index for this run even
                    // when the bytes already existed under another URL.
                    $this->indexMedia($url, $media->id);
                    $this->mediaCache[$url] = $media;
                    $stats[$this->media->lastWasDuplicate ? 'existing' : 'imported']++;
                } catch (\Throwable $e) {
                    $this->warn("{$filename}: {$e->getMessage()}");
                    $stats['failed']++;
                } finally {
                    @unlink($temporary);
                }
            }

            $zip->close();
            $this->line(basename($archivePath).' processed.');
        }

        if ($this->option('rewrite') && ! $this->option('dry-run')) {
            $rewritten = $this->rewriteContent();
            $stats['rewritten'] = $rewritten;
        }

        $this->table(array_keys($stats), [array_values($stats)]);

        return self::SUCCESS;
    }

    private function buildUrlIndex(): void
    {
        Media::query()->whereNotNull('source_url')->pluck('id', 'source_url')->each(function ($id, $url): void {
            $this->indexMedia((string) $url, (int) $id);
        });
    }

    private function findMediaId(string $url): ?int
    {
        foreach (self::urlVariants($url) as $variant) {
            if (isset($this->urlIndex[$variant])) {
                return $this->urlIndex[$variant];
            }
        }

        return null;
    }

    private function indexMedia(string $url, int $id): void
    {
        foreach (self::urlVariants($url) as $variant) {
            $this->urlIndex[$variant] ??= $id;
        }
    }

    /**
     * Match WordPress resized names and both the modern uploads path and the
     * older /upload_images path used by VJU's legacy pages.
     *
     * @return list<string>
     */
    private static function urlVariants(string $url): array
    {
        $rawPath = (string) parse_url($url, PHP_URL_PATH);
        $path = rawurldecode($rawPath);
        $lower = Str::lower($path);
        $variants = [$url, $rawPath, $path, $lower, Str::lower($rawPath)];
        $base = preg_replace('/-(\d+x\d+|scaled|rotated|e\d{10,})(?=\.\w+$)/i', '', $path);
        if (is_string($base)) {
            $variants[] = $base;
            $variants[] = Str::lower($base);
        }
        $rawBase = preg_replace('/-(\d+x\d+|scaled|rotated|e\d{10,})(?=\.\w+$)/i', '', $rawPath);
        if (is_string($rawBase)) {
            $variants[] = $rawBase;
            $variants[] = Str::lower($rawBase);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function uploadDate(string $url): ?Carbon
    {
        return preg_match('#/wp-content/uploads/(\d{4})/(\d{2})/#', $url, $m)
            ? Carbon::create((int) $m[1], (int) $m[2], 1)
            : null;
    }

    private function isLegacyImageUrl(string $url): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: $host;
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        return in_array($host, ['vju.ac.vn', 'vju.vnu.edu.vn'], true)
            && ! str_ends_with($path, '/wp-includes/images/blank.gif');
    }

    private function rewriteContent(): int
    {
        $this->buildUrlIndex();
        $rewritten = 0;

        ContentTranslation::query()->select(['id', 'body', 'blocks'])->chunkById(100, function ($translations) use (&$rewritten): void {
            foreach ($translations as $translation) {
                $body = $this->replaceUrls((string) ($translation->body ?? ''));
                $blocks = $translation->blocks;
                $newBlocks = $this->replaceNested($blocks);

                if ($body !== (string) ($translation->body ?? '') || $newBlocks !== $blocks) {
                    $translation->forceFill(['body' => $body, 'blocks' => $newBlocks])->saveQuietly();
                    $rewritten++;
                }
            }
        });

        return $rewritten;
    }

    private function replaceNested(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->replaceUrls($value);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replaceNested($item);
            }
        }

        return $value;
    }

    private function replaceUrls(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $replacements = [];
        foreach ($this->assetUrls as $assetUrl => $mapped) {
            $id = ctype_digit($mapped) ? (int) $mapped : $this->findMediaId($assetUrl);
            if (! $id) {
                continue;
            }
            $media = $this->mediaCache[$assetUrl] ??= Media::find($id);
            if (! $media || ! $media->isImage()) {
                continue;
            }
            $target = $media->derivativeUrl('web');
            foreach (self::urlVariants($assetUrl) as $variant) {
                $replacements[$variant] = $target;
            }
        }

        if ($replacements === []) {
            return $value;
        }
        uksort($replacements, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace(array_keys($replacements), array_values($replacements), $value);
    }
}
