<?php

namespace App\Filament\Forms;

use App\Models\Category;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * Content modules for pages (home, admissions, education, research...). Rendered by
 * resources/js/Components/Blocks. Add a block here and a matching React component there.
 */
class PageBlocks
{
    public static function make(string $name): Builder
    {
        return Builder::make($name)
            ->label('Content modules')
            ->collapsible()
            ->collapsed()
            ->cloneable()
            ->blockNumbers(false)
            ->blocks([
                Block::make('hero')->icon('heroicon-o-photo')->schema([
                    TextInput::make('heading')->required(),
                    Textarea::make('subheading')->rows(2),
                    Repeater::make('slides')->label('Background slides')->schema([
                        MediaPicker::make('image_id')->label('Image')->required(),
                        TextInput::make('caption'),
                    ])->collapsible()->defaultItems(0),
                    self::buttons(),
                ]),
                Block::make('rich_text')->label('Rich text')->icon('heroicon-o-document-text')->schema([
                    TextInput::make('heading'),
                    CmsRichEditor::make('body')->required(),
                ]),
                Block::make('cards')->label('Cards / highlights')->icon('heroicon-o-squares-2x2')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('heading'),
                        Select::make('columns')->options([2 => 2, 3 => 3, 4 => 4])->default(3)->selectablePlaceholder(false),
                    ]),
                    Textarea::make('intro')->rows(2),
                    Repeater::make('items')->schema([
                        TextInput::make('title')->required(),
                        Textarea::make('text')->rows(2),
                        MediaPicker::make('image_id')->label('Image'),
                        TextInput::make('url')->label('Link'),
                    ])->collapsible()->itemLabel(fn (array $state) => $state['title'] ?? null),
                ]),
                Block::make('stats')->label('Key figures')->icon('heroicon-o-chart-bar')->schema([
                    TextInput::make('heading'),
                    Repeater::make('items')->schema([
                        TextInput::make('value')->required(),
                        TextInput::make('label')->required(),
                    ])->columns(2),
                ]),
                Block::make('post_list')->label('Latest posts')->icon('heroicon-o-newspaper')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('heading'),
                        Select::make('category_id')->label('Category')->searchable()
                            ->options(fn () => Category::with('translations')->get()->mapWithKeys(fn (Category $c) => [$c->id => $c->name])),
                        TextInput::make('limit')->numeric()->default(6)->minValue(1)->maxValue(24),
                    ]),
                    Select::make('style')->options(['grid' => 'Grid', 'list' => 'List', 'featured' => 'Featured + list'])->default('grid'),
                    TextInput::make('more_url')->label('"View all" link'),
                ]),
                Block::make('steps')->label('Steps / process')->icon('heroicon-o-list-bullet')->schema([
                    TextInput::make('heading'),
                    Repeater::make('items')->schema([
                        TextInput::make('title')->required(),
                        Textarea::make('text')->rows(2),
                    ])->collapsible()->itemLabel(fn (array $state) => $state['title'] ?? null),
                ]),
                Block::make('faq')->label('FAQ')->icon('heroicon-o-question-mark-circle')->schema([
                    TextInput::make('heading'),
                    Repeater::make('items')->schema([
                        TextInput::make('question')->required(),
                        CmsRichEditor::make('answer')->required(),
                    ])->collapsible()->itemLabel(fn (array $state) => $state['question'] ?? null),
                ]),
                Block::make('documents')->label('Document downloads')->icon('heroicon-o-arrow-down-tray')->schema([
                    TextInput::make('heading'),
                    Repeater::make('items')->schema([
                        TextInput::make('title')->required(),
                        MediaPicker::make('media_id', imagesOnly: false)->label('File'),
                        TextInput::make('url')->label('…or external URL'),
                    ])->columns(3),
                ]),
                Block::make('cta')->label('Call to action')->icon('heroicon-o-megaphone')->schema([
                    TextInput::make('heading')->required(),
                    Textarea::make('text')->rows(2),
                    MediaPicker::make('image_id')->label('Background image'),
                    self::buttons(),
                ]),
                Block::make('logos')->label('Partners / logos')->icon('heroicon-o-building-library')->schema([
                    TextInput::make('heading'),
                    Repeater::make('items')->schema([
                        TextInput::make('name')->required(),
                        MediaPicker::make('image_id')->label('Logo')->required(),
                        TextInput::make('url'),
                    ])->columns(3)->collapsible(),
                ]),
                Block::make('video')->icon('heroicon-o-play')->schema([
                    TextInput::make('heading'),
                    TextInput::make('url')->label('YouTube URL')->url()->required(),
                    TextInput::make('caption'),
                ]),
            ]);
    }

    private static function buttons(): Repeater
    {
        return Repeater::make('buttons')->schema([
            TextInput::make('label')->required(),
            TextInput::make('url')->required(),
        ])->columns(2)->defaultItems(0)->maxItems(3);
    }
}
