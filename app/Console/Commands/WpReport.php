<?php

namespace App\Console\Commands;

use App\Domain\Migration\WordPress\MigrationReports;
use Illuminate\Console\Command;

class WpReport extends Command
{
    protected $signature = 'wp:report';

    protected $description = 'Write migration-report.json/csv and url-inventory.csv to storage/app/private/migration';

    public function handle(MigrationReports $reports): int
    {
        $summary = $reports->report();

        foreach ($summary['by_type'] as $type => $statuses) {
            $this->line(str_pad($type, 24).collect($statuses)->map(fn ($n, $s) => "{$s}={$n}")->implode('  '));
        }
        $this->newLine();
        $this->line('Warnings: '.collect($summary['warnings_by_code'])->map(fn ($n, $c) => "{$c}={$n}")->implode(', '));
        $this->line("Missing media: {$summary['missing_media']} · Redirects: {$summary['redirects']} ({$summary['redirects_from_migration']} from migration)");
        $this->info('Written: storage/app/private/migration/{'.implode(',', $summary['files']).'}');

        return self::SUCCESS;
    }
}
