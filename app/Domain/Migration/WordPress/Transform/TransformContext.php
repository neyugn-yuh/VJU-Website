<?php

namespace App\Domain\Migration\WordPress\Transform;

use Closure;

/**
 * Callbacks the transformer uses to look things up in the target CMS, plus the warnings
 * collected for the migration report (recoverable problems are logged, never silently dropped).
 */
class TransformContext
{
    /** @var list<string> */
    public array $warnings = [];

    /**
     * @param  Closure(string): ?string  $mediaUrl  legacy uploads URL -> new media URL (null = missing)
     * @param  Closure(int): ?string  $attachmentUrl  WP attachment id -> new media URL
     * @param  string  $legacyHost  e.g. "vju.ac.vn"; absolute links to it become site-relative
     */
    public function __construct(
        public readonly Closure $mediaUrl,
        public readonly Closure $attachmentUrl,
        public readonly string $legacyHost,
    ) {}

    public static function passthrough(string $legacyHost = 'vju.ac.vn'): self
    {
        return new self(fn (string $url) => $url, fn (int $id) => null, $legacyHost);
    }

    /** The site host plus former domains (config cms.wordpress.legacy_hosts), all treated as "same site". */
    public function legacyHosts(): array
    {
        return array_values(array_unique(array_map('strtolower', [$this->legacyHost, ...(array) config('cms.wordpress.legacy_hosts', [])])));
    }

    public function warn(string $code, string $detail = ''): void
    {
        $message = $detail === '' ? $code : "{$code}: {$detail}";
        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }
}
