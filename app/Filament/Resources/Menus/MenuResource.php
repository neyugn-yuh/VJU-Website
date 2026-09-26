<?php

namespace App\Filament\Resources\Menus;

use App\Domain\Menu\MenuService;
use App\Models\Category;
use App\Models\ContentTranslation;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\Locales;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('location')->options(config('cms.menu_locations'))->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('locale', $get('locale'))),
                Select::make('locale')->options(Locales::options())->required()->default(Locales::default())->live(),
            ]),
            Section::make('Items')->description('Drag to reorder. Nest items with "Sub-items". Items linking to unpublished content are hidden on the site.')
                ->schema([self::level(1)]),
        ]);
    }

    /** Recursive nested repeater: the tree structure makes cycles impossible. */
    private static function level(int $depth): Repeater
    {
        $fields = [
            Grid::make(4)->schema([
                TextInput::make('label')->required()->maxLength(255)->columnSpan(2),
                Select::make('item_type')->label('Type')->options(MenuItem::TYPES)->default('content')->required()->live(),
                Select::make('target')->options(['' => 'Same tab', '_blank' => 'New tab'])->default(''),
            ]),
            Select::make('content_id')->label('Content')->searchable()->required()
                ->visible(fn (Get $get) => $get('item_type') === 'content')
                ->helperText('The link uses the translation matching the menu language.')
                ->getSearchResultsUsing(fn (string $search) => ContentTranslation::where('title', 'like', "%{$search}%")
                    ->limit(30)->get()->mapWithKeys(fn ($t) => [$t->content_id => strtoupper($t->locale)." · {$t->title} (/{$t->path})"]))
                ->getOptionLabelUsing(fn ($value) => ($t = ContentTranslation::where('content_id', $value)->first()) ? "{$t->title} (/{$t->path})" : "#{$value}"),
            Select::make('category_id')->label('Category')->searchable()->required()
                ->visible(fn (Get $get) => $get('item_type') === 'category')
                ->options(fn () => Category::with('translations')->get()->mapWithKeys(fn (Category $c) => [$c->id => $c->name])),
            TextInput::make('url')->label('URL')->required()->maxLength(1000)
                ->visible(fn (Get $get) => in_array($get('item_type'), ['external_url', 'custom'], true))
                ->placeholder(fn (Get $get) => $get('item_type') === 'custom' ? '/en/contact/' : 'https://'),
            TextInput::make('icon')->maxLength(64)->placeholder('optional icon name'),
        ];

        if ($depth < MenuService::MAX_DEPTH) {
            $fields[] = self::level($depth + 1)->label('Sub-items');
        }

        return Repeater::make($depth === 1 ? 'tree' : 'children')
            ->hiddenLabel($depth === 1)
            ->schema($fields)
            ->defaultItems(0)
            ->reorderableWithDragAndDrop()
            ->collapsible()
            ->collapsed($depth > 1)
            ->itemLabel(fn (array $state) => $state['label'] ?? null)
            ->addActionLabel($depth === 1 ? 'Add item' : 'Add sub-item');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('location')->formatStateUsing(fn ($state) => config("cms.menu_locations.$state", $state))->badge(),
                TextColumn::make('locale')->formatStateUsing(fn ($state) => Locales::name($state)),
                TextColumn::make('items_count')->counts('items')->label('Items'),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
