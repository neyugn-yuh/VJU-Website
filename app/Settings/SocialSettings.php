<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SocialSettings extends Settings
{
    public ?string $facebook;

    public ?string $youtube;

    public ?string $linkedin;

    public ?string $instagram;

    public ?string $tiktok;

    public static function group(): string
    {
        return 'social';
    }
}
