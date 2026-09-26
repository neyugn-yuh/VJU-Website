<?php

namespace App\Domain\Content;

use Mews\Purifier\Facades\Purifier;

/** Server-side sanitation of rich text. Everything stored in content bodies passes through here. */
class HtmlSanitizer
{
    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return trim((string) Purifier::clean($html, 'cms'));
    }
}
