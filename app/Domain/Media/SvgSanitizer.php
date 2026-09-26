<?php

namespace App\Domain\Media;

use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;

/**
 * SVG is XML that browsers execute when opened directly. Before storing, remove everything
 * active: scripts, foreignObject, event handlers, javascript:/data: links and external references.
 * (nginx additionally serves /storage/*.svg with "Content-Security-Policy: script-src 'none'".)
 */
class SvgSanitizer
{
    private const FORBIDDEN_ELEMENTS = ['script', 'foreignObject', 'iframe', 'embed', 'object', 'handler', 'listener', 'set', 'animate'];

    public function sanitize(string $svg): string
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $svg)) {
            throw ValidationException::withMessages(['file' => 'SVG with DOCTYPE/ENTITY declarations is not allowed.']);
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || strtolower($doc->documentElement->localName ?? '') !== 'svg') {
            throw ValidationException::withMessages(['file' => 'Invalid SVG file.']);
        }

        $xpath = new DOMXPath($doc);
        foreach (self::FORBIDDEN_ELEMENTS as $name) {
            foreach (iterator_to_array($xpath->query("//*[local-name()='{$name}']")) as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        foreach (iterator_to_array($xpath->query('//*')) as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = strtolower(preg_replace('/\s+/', '', $attribute->nodeValue));
                // Links may only point inside the document or embed a raster image (common in exported logos).
                $unsafeLink = in_array($attribute->localName, ['href', 'src'], true)
                    && ! str_starts_with($value, '#')
                    && ! preg_match('#^data:image/(png|jpe?g|gif|webp);#', $value);

                if (str_starts_with($name, 'on')
                    || $unsafeLink
                    || str_contains($value, 'javascript:')
                    || ($name === 'style' && (str_contains($value, 'url(') || str_contains($value, 'expression(')))) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }

        return $doc->saveXML($doc->documentElement);
    }
}
