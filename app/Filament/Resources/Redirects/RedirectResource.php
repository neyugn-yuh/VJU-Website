<?php

namespace App\Filament\Resources\Redirects;

use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('old_url')->label('Old URL or path')->required()->maxLength(700)
                ->helperText('Matched without domain, query string, case or trailing slash. Example: /tin-tuc/bai-cu/')
                ->unique(ignoreRecord: true),
            Select::make('status_code')->options([301 => '301 Moved permanently', 302 => '302 Temporary', 410 => '410 Gone (retired)'])->default(301)->required()->live(),
            TextInput::make('new_url')->label('Target')->maxLength(1000)
                ->required(fn (Get $get) => (int) $get('status_code') !== 410)
                ->hidden(fn (Get $get) => (int) $get('status_code') === 410)
                ->helperText('Relative path (/en/news/) or absolute URL.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('old_url')->searchable()->limit(60)->copyable(),
                TextColumn::make('status_code')->badge()->color(fn (int $state) => $state === 410 ? 'danger' : 'gray'),
                TextColumn::make('new_url')->searchable()->limit(60)->placeholder('—'),
                TextColumn::make('source')->badge()->color('gray'),
                TextColumn::make('hits')->sortable(),
                TextColumn::make('last_hit_at')->since()->sortable()->placeholder('never'),
            ])
            ->filters([
                SelectFilter::make('status_code')->options([301 => '301', 302 => '302', 410 => '410']),
                SelectFilter::make('source')->options(['manual' => 'Manual', 'auto' => 'Automatic (slug change)', 'migration' => 'WordPress migration']),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageRedirects::route('/')];
    }
}
