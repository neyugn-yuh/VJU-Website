<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    #[Url]
    public string $display = 'grid';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleView')
                ->label(fn () => $this->display === 'grid' ? 'List view' : 'Grid view')
                ->icon(fn () => $this->display === 'grid' ? 'heroicon-o-list-bullet' : 'heroicon-o-squares-2x2')
                ->color('gray')
                ->action(function () {
                    $this->display = $this->display === 'grid' ? 'list' : 'grid';
                    $this->resetTable();
                }),
            CreateAction::make()->label('Upload'),
        ];
    }
}
