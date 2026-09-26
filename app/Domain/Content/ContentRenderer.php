<?php

namespace App\Domain\Content;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Render-time enhancements of stored (already sanitized) HTML:
 * standalone YouTube links -> privacy-friendly embeds, lazy images, scrollable tables.
 * Stored HTML stays editor-friendly; embeds never live in the database.
 */
class ContentRenderer
{
    public function render(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        foreach (iterator_to_array($xpath->query('//p[count(*)=1 and count(a)=1]')) as $p) {
            /** @var DOMElement $p */
            $a = $p->getElementsByTagName('a')->item(0);
            if (trim($p->textContent) !== trim($a->textContent)) {
                continue;
            }
            if ($id = self::youtubeId($a->getAttribute('href'))) {
                $wrapper = $doc->createElement('div');
                $wrapper->setAttribute('class', 'embed-video');
                $iframe = $doc->createElement('iframe');
                foreach ([
                    'src' => "https://www.youtube-nocookie.com/embed/{$id}",
                    'title' => trim($a->textContent) ?: 'YouTube video',
                    'loading' => 'lazy',
                    'allow' => 'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
                    'allowfullscreen' => 'allowfullscreen',
                    'referrerpolicy' => 'strict-origin-when-cross-origin',
                ] as $k => $v) {
                    $iframe->setAttribute($k, $v);
                }
                $wrapper->appendChild($iframe);
                $p->parentNode->replaceChild($wrapper, $p);
            }
        }

        foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
            $img->setAttribute('loading', 'lazy');
            $img->setAttribute('decoding', 'async');
        }

        foreach (iterator_to_array($doc->getElementsByTagName('table')) as $table) {
            if ($table->parentNode instanceof DOMElement && $table->parentNode->getAttribute('class') === 'table-wrap') {
                continue;
            }
            $wrap = $doc->createElement('div');
            $wrap->setAttribute('class', 'table-wrap');
            $table->parentNode->replaceChild($wrap, $table);
            $wrap->appendChild($table);
        }

        $root = $doc->getElementById('__root');
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    public static function youtubeId(string $url): ?string
    {
        if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
