<?php

namespace App\Console\Commands;

use App\Domain\SEO\RedirectResolver;
use App\Models\CategoryTranslation;
use App\Models\ContentTranslation;
use App\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * REST-source complement to `wp:import --type=redirects`: for internal links that no longer resolve in the
 * CMS (from the latest wp:validate), ask the live WordPress site where it redirects them and store the
 * redirect when the final target is live in the CMS. The DB source reads the Yoast rules directly instead.
 */
class WpHarvestRedirects extends Command
{
    protected $signature = 'wp:harvest-redirects {--dry-run}';

    protected $description = 'Record redirects that the live WordPress site applies to broken legacy links';

    public function handle(): int
    {
        $file = collect(Storage::disk('local')->files('migration'))->filter(fn ($f) => str_starts_with(basename($f), 'validation-') && str_ends_with($f, '.json'))->sort()->last();
        if (! $file) {
            $this->error('Run `php artisan wp:validate` first.');

            return self::FAILURE;
        }

        $paths = collect(json_decode(Storage::disk('local')->get($file), true)['issues'])
            ->where('check', 'broken_link')->pluck('detail')->unique()->values();
        $base = config('cms.wordpress.base_url');
        $stats = ['recorded' => 0, 'not_redirected' => 0, 'target_missing' => 0];

        foreach ($paths as $path) {
            $response = Http::withUserAgent('VJU-CMS-Migrator/1.0')->withoutRedirecting()->timeout(20)->head($base.$this->encode($path));
            $location = $response->header('Location');
            if (! in_array($response->status(), [301, 302, 307, 308], true) || ! $location) {
                $stats['not_redirected']++;

                continue;
            }

            $target = RedirectResolver::nfc(rawurldecode((string) parse_url($location, PHP_URL_PATH))) ?: '/';
            [$locale, $rest] = Locales::split($target);
            $live = $rest === '' || ContentTranslation::where('locale', $locale)->where('path', trim($rest, '/'))->exists()
                || CategoryTranslation::where('locale', $locale)->where('path', trim($rest, '/'))->exists();
            $isUpload = str_contains($target, '/wp-content/uploads/') && RedirectResolver::find($target);

            if (! $live && ! $isUpload) {
                $stats['target_missing']++;
                $this->line("  ? {$path} → {$location} (target not in CMS)");

                continue;
            }

            $final = $isUpload ? RedirectResolver::find($target)->new_url : Locales::path($locale, trim($rest, '/'));
            $this->line("  ✓ {$path} → {$final}");
            if (! $this->option('dry-run')) {
                RedirectResolver::record($path, $final, 'migration');
            }
            $stats['recorded']++;
        }

        $this->table(array_keys($stats), [array_values($stats)]);

        return self::SUCCESS;
    }

    private function encode(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
