<?php

namespace App\Support;

use Illuminate\Support\Str;

class Slug
{
    /** URL segments owned by the application; content may not claim them as a first segment. */
    public const RESERVED = ['admin', 'livewire', 'storage', 'filament', 'build', 'auth', 'health', 'search', 'tag', 'preview', 'comments', 'sitemap.xml', 'robots.txt', 'en', 'ja', 'vi', 'page', 'up'];

    /**
     * WordPress-compatible slug: Vietnamese is transliterated to ASCII like sanitize_title();
     * Japanese keeps its characters (WordPress stored them percent-encoded).
     */
    public static function make(string $text, string $locale): string
    {
        $text = str_replace(['đ', 'Đ'], 'd', \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text);
        $slug = Str::slug($text, '-', $locale === 'ja' ? null : 'vi');

        return mb_substr($slug, 0, 190);
    }
}
