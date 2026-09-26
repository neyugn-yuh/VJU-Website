<?php

namespace App\Filament\Resources\Migration;

use App\Models\WpMigrationMap;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Read-only view of wp_migration_map: per-record status, warnings and URL mapping. */
class MigrationMapResource extends Resource
{
    protected static ?string $model = WpMigrationMap::class;

    protected static ?string $modelLabel = 'migration record';

    protected static ?string $navigationLabel = 'WordPress migration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('migration.run');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('source_type')->badge()->color('gray'),
                TextColumn::make('source_id')->label('WP ID')->searchable(),
                TextColumn::make('locale'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'success' => 'success', 'failed' => 'danger', default => 'warning',
                }),
                TextColumn::make('source_url')->label('Legacy URL')->limit(50)->searchable()->url(fn (WpMigrationMap $r) => $r->source_url, shouldOpenInNewTab: true),
                TextColumn::make('target_url')->label('CMS URL')->limit(50)->searchable(),
                TextColumn::make('warnings')->state(fn (WpMigrationMap $r) => $r->error ?? implode(' · ', $r->warnings ?? []))->wrap()->limit(160),
                TextColumn::make('last_attempt_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('source_type')->options(fn () => WpMigrationMap::distinct()->orderBy('source_type')->pluck('source_type', 'source_type')),
                SelectFilter::make('status')->options(['success' => 'Success', 'skipped' => 'Skipped', 'failed' => 'Failed']),
                Filter::make('with_warnings')->query(fn (Builder $query) => $query->whereNotNull('warnings')),
                Filter::make('url_changed')->label('URL changed (301)')->query(fn (Builder $query) => $query->where('warnings', 'like', '%url_changed%')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMigrationRecords::route('/')];
    }
}
