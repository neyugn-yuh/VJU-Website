<?php

namespace App\Filament\Resources\Tags;

use App\Domain\Content\TaxonomyService;
use App\Filament\Forms\TranslationTabs;
use App\Models\Tag;
use App\Support\Locales;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Taxonomy';

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->name ?? 'tag';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TranslationTabs::make(fn (string $p, string $locale) => [
                TextInput::make("$p.name")->label('Name')->required(fn () => $locale === Locales::default())->maxLength(255),
                TextInput::make("$p.slug")->label('Slug')->maxLength(190),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $save = fn (Tag $record, array $data) => app(TaxonomyService::class)->saveTag($record, $data['translations'] ?? []);

        return $table
            ->columns([
                TextColumn::make('name')->state(fn (Tag $r) => $r->name)
                    ->description(fn (Tag $r) => $r->translations->pluck('locale')->map('strtoupper')->implode(' · '))
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('translations', fn ($t) => $t->where('name', 'like', "%$s%"))),
                TextColumn::make('contents_count')->counts('contents')->label('Items')->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Tag $record) => [...$data, 'translations' => $record->translations->mapWithKeys(fn ($t) => [$t->locale => $t->only(['name', 'slug'])])->all()])
                    ->using($save),
                DeleteAction::make(),
            ])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTags::route('/')];
    }
}
