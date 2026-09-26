<?php

namespace App\Filament\Pages\Settings;

use App\Support\Locales;
use Closure;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use UnitEnum;

abstract class CmsSettingsPage extends SettingsPage
{
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('settings.manage');
    }

    /** Per-locale inputs for array settings like site_name[vi|en|ja]. */
    protected static function localized(Closure $field): Tabs
    {
        return Tabs::make()->columnSpanFull()->tabs(
            collect(Locales::codes())->map(fn (string $l) => Tab::make(Locales::name($l))->schema([$field($l)]))->all()
        );
    }
}
