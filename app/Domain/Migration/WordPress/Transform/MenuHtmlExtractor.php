<?php

namespace App\Domain\Migration\WordPress\Transform;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * REST fallback for menus (the REST API does not expose them): reads Elementor nav widgets from
 * the rendered homepage. Horizontal navs -> "header", vertical navs -> "footer".
 * The DB source reads the real nav_menu data and is preferred when available.
 */
class MenuHtmlExtractor
{
    /** @return array<string, list<array>> location => flat items {id, parent, title, url, type, target, order} */
    public function extract(string $html, string $baseUrl): array
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        $result = ['header' => [], 'footer' => []];
        $nextId = 1;

        foreach ($xpath->query("//nav[contains(@class, 'elementor-nav-menu--main')]") as $nav) {
            /** @var DOMElement $nav */
            $location = str_contains($nav->getAttribute('class'), 'layout-vertical') ? 'footer' : 'header';
            $ul = $xpath->query('./ul', $nav)->item(0);
            if ($ul) {
                $this->walk($xpath, $ul, 0, $result[$location], $nextId, $baseUrl);
            }
        }

        return array_filter($result);
    }

    private function walk(DOMXPath $xpath, DOMElement $ul, int $parent, array &$items, int &$nextId, string $baseUrl): void
    {
        $order = 0;
        foreach ($xpath->query('./li', $ul) as $li) {
            $a = $xpath->query('./a', $li)->item(0);
            if (! $a instanceof DOMElement) {
                continue;
            }

            $id = $nextId++;
            $href = $a->getAttribute('href');
            $items[] = [
                'id' => $id,
                'parent' => $parent,
                'title' => trim(preg_replace('/\s+/u', ' ', $a->textContent)),
                'url' => $href === '#' || $href === '' ? '#' : $href,
                'type' => 'custom',
                'object' => null,
                'object_id' => 0,
                'target' => $a->getAttribute('target') ?: null,
                'order' => $order++,
            ];

            if ($sub = $xpath->query('./ul', $li)->item(0)) {
                $this->walk($xpath, $sub, $id, $items, $nextId, $baseUrl);
            }
        }
    }
}
