<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Domain\Content\TaxonomyService;
use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => app(TaxonomyService::class)->saveCategory(new Category, $data['parent_id'] ?? null, $data['translations'] ?? [])),
        ];
    }
}
