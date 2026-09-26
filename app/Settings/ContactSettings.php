<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ContactSettings extends Settings
{
    public ?string $phone;

    public ?string $email;

    /** @var array<string, string> locale => address */
    public array $address;

    public ?string $map_url;

    public static function group(): string
    {
        return 'contact';
    }
}
