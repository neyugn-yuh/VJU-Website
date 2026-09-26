<?php

namespace App\Filament\Forms;

use App\Support\Locales;
use Closure;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;

/** One tab per locale; $fields receives the state prefix ("translations.vi") and the locale. */
class TranslationTabs
{
    public static function make(Closure $fields): Tabs
    {
        return Tabs::make('Translations')->columnSpanFull()->tabs(
            collect(Locales::codes())->map(fn (string $locale) => Tab::make(Locales::name($locale))
                ->badge(fn (Get $get) => filled($get("translations.$locale.name")) ? '✓' : null)
                ->schema($fields("translations.$locale", $locale)))->all()
        );
    }
}
