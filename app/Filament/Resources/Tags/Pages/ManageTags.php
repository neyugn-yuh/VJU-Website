<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Domain\Content\TaxonomyService;
use App\Filament\Resources\Tags\TagResource;
use App\Models\Tag;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTags extends ManageRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => app(TaxonomyService::class)->saveTag(new Tag, $data['translations'] ?? [])),
        ];
    }
}
