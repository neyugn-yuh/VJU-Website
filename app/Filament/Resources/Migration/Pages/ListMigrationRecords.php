<?php

namespace App\Filament\Resources\Migration\Pages;

use App\Filament\Resources\Migration\MigrationMapResource;
use Filament\Resources\Pages\ListRecords;

class ListMigrationRecords extends ListRecords
{
    protected static string $resource = MigrationMapResource::class;

    public function getSubheading(): ?string
    {
        return 'Run imports with `php artisan wp:import`; validate with `wp:validate`; export with `wp:report`.';
    }
}
