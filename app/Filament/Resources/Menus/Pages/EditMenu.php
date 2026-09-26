<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Domain\Menu\MenuService;
use App\Filament\Resources\Menus\MenuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'tree' => app(MenuService::class)->tree($this->record)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return DB::transaction(function () use ($record, $data) {
                $record->update(Arr::only($data, ['name', 'location', 'locale']));
                app(MenuService::class)->sync($record, $data['tree'] ?? []);

                return $record;
            });
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.tree' => collect($e->errors())->flatten()->all()]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
