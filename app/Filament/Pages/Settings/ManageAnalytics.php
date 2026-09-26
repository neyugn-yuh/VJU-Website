<?php

namespace App\Filament\Pages\Settings;

use App\Settings\AnalyticsSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageAnalytics extends CmsSettingsPage
{
    protected static string $settings = AnalyticsSettings::class;

    protected static ?string $title = 'Analytics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('ga_measurement_id')->label('GA4 measurement ID')->regex('/^G-[A-Z0-9]+$/')->placeholder('G-XXXXXXX'),
            TextInput::make('gtm_container_id')->label('Tag Manager container')->regex('/^GTM-[A-Z0-9]+$/')->placeholder('GTM-XXXXXX'),
        ]);
    }
}
