<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Forms\MediaPicker;
use App\Settings\SeoSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSeo extends CmsSettingsPage
{
    protected static string $settings = SeoSettings::class;

    protected static ?string $title = 'SEO';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canAny(['settings.manage', 'seo.manage']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            self::localized(fn ($l) => TextInput::make("default_title.$l")->label('Default title')),
            self::localized(fn ($l) => Textarea::make("default_description.$l")->label('Default description')->rows(2)),
            TextInput::make('title_template')->required()->helperText('Placeholders: %title%, %site%'),
            MediaPicker::make('default_og_image_id')->label('Default Open Graph image'),
            Textarea::make('robots_extra')->label('Extra robots.txt lines')->rows(4),
            Toggle::make('discourage_indexing')->label('Discourage search engines (staging only!)')
                ->helperText('Adds noindex to every page and disallows all crawling in robots.txt.'),
        ]);
    }
}
