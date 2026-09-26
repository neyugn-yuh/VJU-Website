<?php

namespace App\Console\Commands;

use App\Domain\Migration\WordPress\MigrationReports;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class WpValidate extends Command
{
    protected $signature = 'wp:validate {--source=rest : which wp:inspect inventory to reconcile against}';

    protected $description = 'Reconcile counts, check every legacy URL (200 or one 301 to a 200), content quality';

    public function handle(MigrationReports $reports): int
    {
        $file = "migration/inspect-{$this->option('source')}.json";
        $inventory = Storage::disk('local')->exists($file) ? json_decode(Storage::disk('local')->get($file), true) : null;
        if (! $inventory) {
            $this->warn("No {$file}; run `php artisan wp:inspect --source={$this->option('source')}` for count reconciliation.");
        }

        $report = $reports->validate($inventory);

        $this->table(['type', 'locale', 'source', 'cms', 'ok'], collect($report['reconciliation'])->map(fn ($r) => [...$r, 'ok' => $r['ok'] ? '✓' : '✗']));
        $this->table(array_keys($report['urls']), [array_values($report['urls'])]);
        $content = collect($report['content'])->except('missing_translation_by_locale');
        $this->table($content->keys()->all(), [$content->values()->all()]);
        $this->line('Content without translation: '.collect($report['content']['missing_translation_by_locale'])->map(fn ($n, $l) => "{$l}={$n}")->implode(', '));
        $this->table(['check', 'count'], collect($report['issues'])->countBy('check')->map(fn ($n, $c) => [$c, $n])->values());
        $this->line('Files: '.implode(', ', $report['files']));

        if (! $report['passed']) {
            $this->error('Validation FAILED (count mismatch, 404, redirect loop/chain or redirect to 404).');

            return self::FAILURE;
        }
        $this->info('Validation passed.');

        return self::SUCCESS;
    }
}
