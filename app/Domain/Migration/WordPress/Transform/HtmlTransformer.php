<?php

namespace App\Domain\Migration\WordPress\Transform;

use App\Domain\Content\HtmlSanitizer;
use App\Domain\Migration\WordPress\WordPressImporter;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * WordPress HTML -> CMS-safe HTML (DOM-based, not regex over the document):
 * shortcodes -> Elementor/page-builder wrappers removed -> media & links rewritten ->
 * headings normalized -> sanitized. Problems are recorded as warnings on the context.
 *
 * Formats: "rendered" (REST, Elementor markup), "raw" (post_content, needs autop),
 * "elementor" (JSON converted first by ElementorConverter).
 */
class HtmlTransformer
{
    private const DROP_TAGS = ['script', 'style', 'noscript', 'link', 'meta', 'svg', 'form', 'input', 'select', 'textarea', 'button', 'template', 'object', 'embed', 'canvas'];

    private const UNWRAP_TAGS = ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'span', 'font', 'center', 'nav'];

    private const BLOCK_TAGS = ['p', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table', 'blockquote', 'pre', 'figure', 'hr', 'details', 'div'];

    /** Elementor widgets that are site chrome or dynamic listings, not article content. */
    private const DROP_WIDGETS = ['nav-menu', 'search-form', 'social-icons', 'share-buttons', 'form', 'posts', 'loop-grid', 'archive-posts', 'sitemap', 'breadcrumbs', 'jet-breadcrumbs', 'login', 'spacer', 'divider', 'menu-anchor'];

    public function __construct(
        private readonly ShortcodeRegistry $shortcodes,
        private readonly ElementorConverter $elementor,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function transform(?string $content, string $format, TransformContext $ctx, ?array $elementorData = null): string
    {
        $html = match ($format) {
            'elementor' => $this->elementor->toHtml($elementorData ?? [], $ctx),
            'raw' => $this->autop((string) $content),
            default => (string) $content,
        };

        // CSS selectors like [data-elementor-type] must not be mistaken for shortcodes.
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = $this->shortcodes->process($html, $ctx);
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (count(libxml_get_errors()) > 50) {
            $ctx->warn('malformed_html', count(libxml_get_errors()).' parser errors');
        }
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);
        $root = $doc->getElementById('__root');

        $this->removeChrome($xpath, $ctx);
        $this->convertElementorWidgets($doc, $xpath, $ctx);
        $this->convertIframes($doc, $xpath, $ctx);
        $this->rewriteImages($xpath, $ctx);
        $this->rewriteLinks($xpath, $ctx);
        $this->normalizeHeadings($doc, $xpath);
        $this->unwrap($root);
        // Page-builder markup leaves indentation runs inside text; collapse them (never inside <pre>).
        foreach (iterator_to_array($xpath->query('//text()[not(ancestor::pre)]')) as $text) {
            $text->nodeValue = preg_replace('/[ \t\r\n]+/', ' ', $text->nodeValue);
        }
        $this->paragraphize($doc, $root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        $clean = (string) $this->sanitizer->clean($out);

        // Drop empty paragraphs left behind by removed widgets.
        return trim(preg_replace('#<p>(?:\s|&nbsp;|\x{00a0}|<br\s*/?>)*</p>#u', '', $clean));
    }

    private function removeChrome(DOMXPath $xpath, TransformContext $ctx): void
    {
        foreach (self::DROP_TAGS as $tag) {
            $this->remove($xpath->query("//{$tag}"));
        }

        // Elementor responsive duplicates: the mobile-only copy would repeat the content.
        $this->remove($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' elementor-hidden-desktop ')]"));
        // Desktop tab strip; the mobile titles inside each tab panel are kept as headings.
        $this->remove($xpath->query("//*[contains(@class, 'elementor-tabs-wrapper')]"));

        foreach (self::DROP_WIDGETS as $widget) {
            $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' elementor-widget-{$widget} ')]");
            if ($nodes->length && ! in_array($widget, ['spacer', 'divider', 'menu-anchor'], true)) {
                $ctx->warn('elementor_widget_removed', $widget);
            }
            $this->remove($nodes);
        }
    }

    private function convertElementorWidgets(DOMDocument $doc, DOMXPath $xpath, TransformContext $ctx): void
    {
        // Video widgets keep their URL in data-settings.
        foreach (iterator_to_array($xpath->query("//*[contains(@class, 'elementor-widget-video')]")) as $node) {
            /** @var DOMElement $node */
            $settings = json_decode(html_entity_decode($node->getAttribute('data-settings')), true) ?: [];
            $url = $settings['youtube_url'] ?? $settings['vimeo_url'] ?? null;
            $url ? $this->replaceWithLink($doc, $node, $url, $url) : $node->parentNode?->removeChild($node);
        }

        // Tab / accordion / toggle titles -> h3 (content panels stay as-is).
        foreach (iterator_to_array($xpath->query("//*[contains(@class, 'elementor-tab-title') or contains(@class, 'elementor-toggle-title') or contains(@class, 'elementor-accordion-title')]")) as $node) {
            $h3 = $doc->createElement('h3', htmlspecialchars(trim($node->textContent)));
            $node->parentNode?->replaceChild($h3, $node);
        }

        // Anything still carrying an unknown widget class is noted for the report.
        foreach ($xpath->query("//*[contains(@class, 'elementor-widget-')]") as $node) {
            if (preg_match('/(?:^|\s)elementor-widget-((?!tablet|mobile|laptop|widescreen|desktop)[a-z0-9-]+)(?:\s|$)/', $node->getAttribute('class'), $m)
                && ! in_array($m[1], ['heading', 'text-editor', 'image', 'image-box', 'icon-list', 'button', 'tabs', 'accordion', 'toggle', 'image-gallery', 'image-carousel', 'container', 'html', 'shortcode', 'google_maps', 'icon-box', 'counter', 'testimonial', 'wp-widget-media_image', 'n-tabs', 'n-accordion', 'video'], true)) {
                $ctx->warn('elementor_widget_unknown', $m[1]);
            }
        }
    }

    private function convertIframes(DOMDocument $doc, DOMXPath $xpath, TransformContext $ctx): void
    {
        foreach (iterator_to_array($xpath->query('//iframe')) as $iframe) {
            /** @var DOMElement $iframe */
            $src = $iframe->getAttribute('src') ?: $iframe->getAttribute('data-src') ?: $iframe->getAttribute('data-lazy-src');

            if (preg_match('~(?:youtube(?:-nocookie)?\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})~', $src, $m)) {
                $this->replaceWithLink($doc, $iframe, "https://www.youtube.com/watch?v={$m[1]}", $iframe->getAttribute('title') ?: "https://www.youtube.com/watch?v={$m[1]}");
            } elseif (str_contains($src, 'google.com/maps')) {
                $this->replaceWithLink($doc, $iframe, $src, 'Google Maps');
            } elseif ($src !== '' && ! str_starts_with($src, 'about:')) {
                $ctx->warn('iframe_converted', parse_url($src, PHP_URL_HOST) ?: $src);
                $this->replaceWithLink($doc, $iframe, $src, $iframe->getAttribute('title') ?: $src);
            } else {
                $iframe->parentNode?->removeChild($iframe);
            }
        }
    }

    private function rewriteImages(DOMXPath $xpath, TransformContext $ctx): void
    {
        foreach (iterator_to_array($xpath->query('//img')) as $img) {
            /** @var DOMElement $img */
            $src = $img->getAttribute('src');
            // Lazy-loading plugins (WP Rocket etc.) put the real URL in data attributes.
            foreach (['data-lazy-src', 'data-src', 'data-orig-file'] as $attr) {
                if ($img->getAttribute($attr) && ($src === '' || str_starts_with($src, 'data:'))) {
                    $src = $img->getAttribute($attr);
                }
            }

            // Empty, inline placeholders and page-builder plugin assets are not content.
            if ($src === '' || str_starts_with($src, 'data:') || str_contains($src, '/wp-content/plugins/')) {
                $img->parentNode?->removeChild($img);

                continue;
            }

            // WP-Optimize lazy video placeholder: the image stands for a YouTube video.
            if (preg_match('#/wpo-youtube-thumbnails/([A-Za-z0-9_-]{11})-#', $src, $m)) {
                $url = "https://www.youtube.com/watch?v={$m[1]}";
                $target = $img->parentNode instanceof DOMElement && strtolower($img->parentNode->tagName) === 'a' ? $img->parentNode : $img;
                $this->replaceWithLink($img->ownerDocument, $target, $url, $img->getAttribute('alt') ?: $url);

                continue;
            }

            $new = $this->isLegacy($src, $ctx) ? ($ctx->mediaUrl)($this->absolute($src, $ctx)) : $src;
            if ($new === null) {
                $ctx->warn('missing_media', $src);
                $new = $src; // keep the reference; wp:validate reports it as a broken image
            } elseif (! $this->isLegacy($src, $ctx)) {
                $ctx->warn('external_image', parse_url($src, PHP_URL_HOST) ?: $src);
            }

            $alt = $img->getAttribute('alt');
            foreach (iterator_to_array($img->attributes) as $attribute) {
                $img->removeAttribute($attribute->name);
            }
            $img->setAttribute('src', $new);
            $img->setAttribute('alt', $alt);
        }
    }

    private function rewriteLinks(DOMXPath $xpath, TransformContext $ctx): void
    {
        foreach (iterator_to_array($xpath->query('//a')) as $a) {
            /** @var DOMElement $a */
            $href = trim($a->getAttribute('href'));
            $target = $a->getAttribute('target');

            if ($href !== '' && $this->isLegacy($href, $ctx)) {
                $absolute = $this->absolute($href, $ctx);
                $path = (string) parse_url($absolute, PHP_URL_PATH);
                if (preg_match(WordPressImporter::UPLOAD_PATHS, $path)) {
                    $href = ($ctx->mediaUrl)($absolute) ?? $href;
                    if ($href === $a->getAttribute('href')) {
                        $ctx->warn('missing_media', $absolute);
                    }
                } else {
                    // Same-site link: keep the legacy path (URLs are preserved or redirected).
                    $query = parse_url($absolute, PHP_URL_QUERY);
                    $fragment = parse_url($absolute, PHP_URL_FRAGMENT);
                    $href = ($path ?: '/').($query ? "?{$query}" : '').($fragment ? "#{$fragment}" : '');
                }
            }

            foreach (iterator_to_array($a->attributes) as $attribute) {
                $a->removeAttribute($attribute->name);
            }
            if ($href !== '') {
                $a->setAttribute('href', $href);
            }
            if ($target === '_blank') {
                $a->setAttribute('target', '_blank');
            }
        }
    }

    private function normalizeHeadings(DOMDocument $doc, DOMXPath $xpath): void
    {
        foreach (iterator_to_array($xpath->query('//h1')) as $h1) {
            $h2 = $doc->createElement('h2');
            while ($h1->firstChild) {
                $h2->appendChild($h1->firstChild);
            }
            $h1->parentNode->replaceChild($h2, $h1);
        }
    }

    /** Replace layout wrappers with their children (depth-first). */
    private function unwrap(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            $this->unwrap($child);

            $tag = strtolower($child->tagName);
            $keepAligned = $tag === 'div' && preg_match('/text-align:\s*(center|right)/', $child->getAttribute('style'));
            if (in_array($tag, self::UNWRAP_TAGS, true) && ! $keepAligned) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
            } else {
                foreach (['class', 'id', 'style', 'data-id', 'data-element_type', 'data-widget_type', 'data-settings'] as $attr) {
                    if ($attr !== 'style' || ! $keepAligned) {
                        $child->removeAttribute($attr);
                    }
                }
            }
        }
    }

    /** Wrap loose inline runs (text left after unwrapping) in paragraphs. */
    private function paragraphize(DOMDocument $doc, DOMElement $root): void
    {
        $run = [];
        $flush = function () use (&$run, $doc, $root) {
            $text = trim(implode('', array_map(fn ($n) => $n->textContent, $run)));
            $hasMedia = array_filter($run, fn ($n) => $n instanceof DOMElement && ($n->tagName === 'img' || $n->getElementsByTagName('img')->length));
            if ($text !== '' || $hasMedia) {
                $p = $doc->createElement('p');
                $root->insertBefore($p, $run[0]);
                foreach ($run as $n) {
                    $p->appendChild($n);
                }
            } else {
                foreach ($run as $n) {
                    $root->removeChild($n);
                }
            }
            $run = [];
        };

        foreach (iterator_to_array($root->childNodes) as $child) {
            $isBlock = $child instanceof DOMElement && in_array(strtolower($child->tagName), self::BLOCK_TAGS, true);
            if ($isBlock) {
                if ($run) {
                    $flush();
                }
            } else {
                $run[] = $child;
            }
        }
        if ($run) {
            $flush();
        }
    }

    /** Minimal wpautop(): blank lines separate paragraphs, single newlines become <br>. */
    public function autop(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return '';
        }

        $blocks = preg_split('/\n\s*\n/', $text);

        return implode("\n", array_map(function ($block) {
            $block = trim($block);
            if (preg_match('/^<(p|h[1-6]|ul|ol|table|blockquote|pre|figure|div|hr|section|iframe|\[)/i', $block)) {
                return $block;
            }

            return '<p>'.preg_replace('/(?<!>)\n/', '<br>', $block).'</p>';
        }, $blocks));
    }

    private function replaceWithLink(DOMDocument $doc, DOMNode $node, string $url, string $label): void
    {
        $p = $doc->createElement('p');
        $a = $doc->createElement('a', htmlspecialchars($label));
        $a->setAttribute('href', $url);
        $p->appendChild($a);
        $node->parentNode?->replaceChild($p, $node);
    }

    private function isLegacy(string $url, TransformContext $ctx): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }
        $host = parse_url(str_starts_with($url, '//') ? "https:{$url}" : $url, PHP_URL_HOST);

        return $host !== null && in_array(strtolower(preg_replace('/^www\./i', '', $host)), $ctx->legacyHosts(), true);
    }

    private function absolute(string $url, TransformContext $ctx): string
    {
        if (str_starts_with($url, '//')) {
            return "https:{$url}";
        }

        return str_starts_with($url, '/') ? "https://{$ctx->legacyHost}{$url}" : $url;
    }

    private function remove(iterable $nodes): void
    {
        foreach (iterator_to_array($nodes) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }
}
