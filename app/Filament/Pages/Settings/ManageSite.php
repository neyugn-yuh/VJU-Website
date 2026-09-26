<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Content\ContentType;
use App\Filament\Forms\MediaPicker;
use App\Models\ContentTranslation;
use App\Settings\SiteSettings;
use App\Support\Locales;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSite extends CmsSettingsPage
{
    protected static string $settings = SiteSettings::class;

    protected static ?string $title = 'Site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')->schema([
                self::localized(fn ($l) => TextInput::make("site_name.$l")->label('Site name')->required($l === Locales::default())),
                self::localized(fn ($l) => Textarea::make("site_description.$l")->label('Description')->rows(2)),
                MediaPicker::make('logo_media_id')->label('Logo'),
                Select::make('default_locale')->options(Locales::options())->required(),
            ]),
            Section::make('Homepage')->schema([
                Select::make('home_page_id')->label('Homepage content')->searchable()
                    ->helperText('A page whose content modules render the homepage in each language.')
                    ->options(fn () => ContentTranslation::whereHas('content', fn ($q) => $q->where('type', ContentType::Page->value))
                        ->where('locale', Locales::default())->orderBy('title')->limit(500)->pluck('title', 'content_id')),
            ]),
        ]);
    }
}
