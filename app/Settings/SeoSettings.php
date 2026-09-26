<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SeoSettings extends Settings
{
    /** @var array<string, string> locale => default title */
    public array $default_title;

    /** @var array<string, string> locale => default description */
    public array $default_description;

    public ?int $default_og_image_id;

    /** "%title% | %site%" */
    public string $title_template;

    /** Extra lines appended to the generated robots.txt. */
    public ?string $robots_extra;

    /** Emergency switch: noindex the entire site (staging). */
    public bool $discourage_indexing;

    public static function group(): string
    {
        return 'seo';
    }
}
