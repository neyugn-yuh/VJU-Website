<?php

namespace App\Filament\Resources\Contents\Pages;

use App\Domain\Content\ContentType;
use App\Filament\Resources\Contents\ContentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListContents extends ListRecords
{
    protected static string $resource = ContentResource::class;

    public function getTitle(): string
    {
        return ContentType::tryFrom((string) $this->activeTab)?->pluralLabel() ?? 'Content';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(fn () => 'New '.strtolower(ContentType::tryFrom((string) $this->activeTab)?->label() ?? 'content'))
                ->url(fn () => ContentResource::getUrl('create', ['type' => $this->activeTab])),
        ];
    }

    public function getTabs(): array
    {
        return collect(ContentType::cases())->mapWithKeys(fn (ContentType $type) => [
            $type->value => Tab::make($type->pluralLabel())->modifyQueryUsing(fn ($query) => $query->where('type', $type->value)),
        ])->all();
    }

    public function getDefaultActiveTab(): string
    {
        return ContentType::Post->value;
    }
}
