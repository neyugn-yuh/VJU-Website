<?php

namespace App\Filament\Pages\Settings;

use App\Settings\ContactSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageContact extends CmsSettingsPage
{
    protected static string $settings = ContactSettings::class;

    protected static ?string $title = 'Contact';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('phone')->tel()->maxLength(64),
            TextInput::make('email')->email(),
            self::localized(fn ($l) => Textarea::make("address.$l")->label('Address')->rows(2)),
            TextInput::make('map_url')->label('Google Maps URL')->url(),
        ]);
    }
}
