<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AnalyticsSettings extends Settings
{
    public ?string $ga_measurement_id;

    public ?string $gtm_container_id;

    public static function group(): string
    {
        return 'analytics';
    }
}
