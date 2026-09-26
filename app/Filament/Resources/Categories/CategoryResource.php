<?php

namespace App\Filament\Resources\Categories;

use App\Domain\Content\TaxonomyService;
use App\Filament\Forms\TranslationTabs;
use App\Models\Category;
use App\Support\Locales;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Taxonomy';

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->name ?? 'category';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('parent_id')->label('Parent')->searchable()
                ->options(fn (?Category $record) => Category::with('translations')
                    ->when($record, fn ($q) => $q->whereNotIn('id', $record->descendantIds()))
                    ->get()->mapWithKeys(fn (Category $c) => [$c->id => $c->translation(Locales::default())?->path ?? $c->name])),
            TranslationTabs::make(fn (string $p, string $locale) => [
                TextInput::make("$p.name")->label('Name')->required(fn () => $locale === Locales::default())->maxLength(255),
                TextInput::make("$p.slug")->label('Slug')->maxLength(190)->helperText('Generated from the name when empty.'),
                Textarea::make("$p.description")->label('Description')->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->state(fn (Category $r) => $r->name)
                    ->description(fn (Category $r) => $r->translations->map(fn ($t) => strtoupper($t->locale).': /'.$t->path)->implode('  '))
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('translations', fn ($t) => $t->where('name', 'like', "%$s%")->orWhere('path', 'like', "%$s%"))),
                TextColumn::make('parent.slug')->label('Parent')->placeholder('—'),
                TextColumn::make('contents_count')->counts('contents')->label('Items')->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Category $record) => [...$data, 'translations' => self::translationsFor($record)])
                    ->using(fn (Category $record, array $data) => app(TaxonomyService::class)->saveCategory($record, $data['parent_id'] ?? null, $data['translations'] ?? [])),
                DeleteAction::make()->modalDescription('Content stays; it is only detached from this category. Child categories move to the top level.'),
            ]);
    }

    public static function translationsFor(Category $category): array
    {
        return $category->translations->mapWithKeys(fn ($t) => [$t->locale => $t->only(['name', 'slug', 'description'])])->all();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCategories::route('/')];
    }
}
