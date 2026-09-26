<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SiteSettings extends Settings
{
    /** @var array<string, string> locale => name */
    public array $site_name;

    /** @var array<string, string> locale => description */
    public array $site_description;

    public string $default_locale;

    public ?int $logo_media_id;

    public ?int $home_page_id;

    public static function group(): string
    {
        return 'site';
    }
}
