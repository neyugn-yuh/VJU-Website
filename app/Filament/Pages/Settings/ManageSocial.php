<?php

namespace App\Filament\Pages\Settings;

use App\Settings\SocialSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSocial extends CmsSettingsPage
{
    protected static string $settings = SocialSettings::class;

    protected static ?string $title = 'Social networks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    public function form(Schema $schema): Schema
    {
        return $schema->components(collect(['facebook', 'youtube', 'linkedin', 'instagram', 'tiktok'])
            ->map(fn ($n) => TextInput::make($n)->label(ucfirst($n))->url()->maxLength(500))->all());
    }
}
