<?php

namespace App\Console\Commands;

use App\Domain\Migration\WordPress\Sources\DatabaseSource;
use App\Domain\Migration\WordPress\Sources\RestApiSource;
use App\Domain\Migration\WordPress\Sources\WordPressSource;
use App\Domain\Migration\WordPress\WordPressImporter;
use Illuminate\Console\Command;

class WpImport extends Command
{
    protected $signature = 'wp:import
        {--type=all : users|taxonomies|media|pages|posts|structured|comments|menus|seo|all}
        {--source=rest : rest (public API) or db (restored dump in the "wordpress" connection)}
        {--dry-run : Run everything, then roll back each record}
        {--force : Re-import records even when their checksum is unchanged}
        {--locale= : Only this language (vi|en|ja)}
        {--id= : Only this WordPress ID}
        {--since= : Only records modified since this time, or "last-run"}
        {--limit= : Stop after N records per type}';

    protected $description = 'Import WordPress content into the CMS (idempotent, repeatable)';

    public function handle(WordPressImporter $importer): int
    {
        $types = $this->option('type') === 'all'
            ? ['users', 'taxonomies', 'media', 'pages', 'posts', 'structured', 'comments', 'menus']
            : [$this->option('type')];

        if (array_diff($types, WordPressImporter::TYPES)) {
            $this->error('Unknown --type. Use: '.implode(', ', WordPressImporter::TYPES).', all');

            return self::INVALID;
        }

        $source = $this->source();
        $filters = array_filter([
            'locale' => $this->option('locale'),
            'id' => $this->option('id') ? (int) $this->option('id') : null,
            'since' => $this->option('since'),
            'limit' => $this->option('limit') ? (int) $this->option('limit') : null,
        ]);

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN: nothing will be persisted.');
        }

        foreach ($types as $type) {
            $this->info("→ {$type} ({$source->name()})");
            $bar = $this->output->createProgressBar();
            $stats = $importer->run($source, $type, $filters, (bool) $this->option('dry-run'), (bool) $this->option('force'),
                fn () => $bar->advance());
            $bar->finish();
            $this->newLine();
            $this->table(array_keys($stats), [array_values($stats)]);
        }

        $this->line('Details: storage/logs/migration-*.log, `php artisan wp:report`, `php artisan wp:validate`.');

        return self::SUCCESS;
    }

    private function source(): WordPressSource
    {
        return $this->option('source') === 'db'
            ? DatabaseSource::make()
            : new RestApiSource(config('cms.wordpress.base_url'));
    }
}
