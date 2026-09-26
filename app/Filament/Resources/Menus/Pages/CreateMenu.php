<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Domain\Menu\MenuService;
use App\Filament\Resources\Menus\MenuResource;
use App\Models\Menu;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $menu = Menu::create(Arr::only($data, ['name', 'location', 'locale']));
            app(MenuService::class)->sync($menu, $data['tree'] ?? []);

            return $menu;
        });
    }
}
