<?php

namespace App\Console\Commands;

use App\Domain\Migration\WordPress\Sources\DatabaseSource;
use App\Domain\Migration\WordPress\Sources\RestApiSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Phase 00 inventory: content counts per type/language, plugins, custom fields, shortcodes, widgets. */
class WpInspect extends Command
{
    protected $signature = 'wp:inspect {--source=rest : rest or db}';

    protected $description = 'Inventory the WordPress source (writes storage/app/migration/inspect-{source}.json)';

    public function handle(): int
    {
        $source = $this->option('source') === 'db' ? DatabaseSource::make() : new RestApiSource(config('cms.wordpress.base_url'));
        $inventory = ['generated_at' => now()->toIso8601String(), ...$source->inventory()];

        $path = "migration/inspect-{$source->name()}.json";
        Storage::disk('local')->put($path, json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (isset($inventory['counts'])) {
            $this->table(['type', 'all', 'vi', 'en', 'ja'], collect($inventory['counts'])->map(fn ($c, $type) => [$type, $c['all'] ?? '', $c['vi'] ?? '', $c['en'] ?? '', $c['ja'] ?? ''])->values());
        }
        if (isset($inventory['published_by_language'])) {
            foreach ($inventory['published_by_language'] as $lang => $counts) {
                $this->line("<info>{$lang}</info>: ".collect($counts)->map(fn ($c, $t) => "{$t}={$c}")->implode(', '));
            }
            $this->line('Plugins: '.implode(', ', $inventory['active_plugins'] ?? []));
            $this->line('Top shortcodes: '.collect($inventory['shortcodes'])->take(15)->map(fn ($c, $s) => "{$s}({$c})")->implode(' '));
            $this->line('Elementor widgets: '.collect($inventory['elementor_widgets'])->take(25)->map(fn ($c, $w) => "{$w}({$c})")->implode(' '));
        }
        if (isset($inventory['namespaces'])) {
            $this->line('REST namespaces: '.implode(', ', $inventory['namespaces']));
        }

        $this->info("Saved storage/app/private/{$path}");

        return self::SUCCESS;
    }
}
