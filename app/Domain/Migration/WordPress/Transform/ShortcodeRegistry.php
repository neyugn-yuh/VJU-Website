<?php

namespace App\Domain\Migration\WordPress\Transform;

use Closure;

/**
 * shortcode -> transformer -> output. Unknown shortcodes are logged; enclosing ones keep their
 * inner content, self-closing ones are removed with a warning. Bracketed text that does not look
 * like a shortcode (e.g. "[Tin tức]") is never touched.
 */
class ShortcodeRegistry
{
    /** @var array<string, Closure(array, ?string, TransformContext): string> */
    private array $handlers = [];

    /** Plugin shortcodes whose output has no CMS equivalent (forms, dynamic listings). */
    private const REMOVED = ['contact-form-7', 'wpforms', 'jet_engine', 'jet-engine', 'elementor-template', 'monsterinsights_popular_posts_inline', 'wp_statistics', 'wpsp_ticket'];

    public function __construct()
    {
        $link = fn (?string $url, ?string $label = null) => $url
            ? '<p><a href="'.e($url).'">'.e($label ?: $url).'</a></p>'
            : '';

        $this->register('caption', function (array $a, ?string $content) {
            if ($content === null) {
                return '';
            }
            preg_match('/<img[^>]+>/i', $content, $img);
            $text = trim(strip_tags(preg_replace('/<img[^>]+>/i', '', $content)));

            return '<figure>'.($img[0] ?? '').($text !== '' ? '<figcaption>'.e($text).'</figcaption>' : '').'</figure>';
        });
        $this->register('embed', fn (array $a, ?string $content) => $link(trim(strip_tags((string) $content))));
        $this->register('video', fn (array $a) => $link($a['src'] ?? $a['mp4'] ?? $a['youtube'] ?? null));
        $this->register('audio', fn (array $a) => $link($a['src'] ?? $a['mp3'] ?? null));
        $this->register('pdf-embedder', fn (array $a) => $link($a['url'] ?? null, 'PDF'));
        $this->register('pdfjs-viewer', fn (array $a) => $link($a['url'] ?? null, 'PDF'));
        $this->register('gallery', function (array $a, ?string $content, TransformContext $ctx) {
            $html = '';
            foreach (array_filter(array_map('intval', explode(',', $a['ids'] ?? $a['include'] ?? ''))) as $id) {
                $url = ($ctx->attachmentUrl)($id);
                $url ? $html .= '<p><img src="'.e($url).'" alt=""></p>' : $ctx->warn('missing_media', "gallery attachment {$id}");
            }

            return $html;
        });
    }

    public function register(string $name, Closure $handler): void
    {
        $this->handlers[$name] = $handler;
    }

    public function process(string $html, TransformContext $ctx): string
    {
        // Based on WordPress' get_shortcode_regex(): [name attrs], [name attrs /], [name]...[/name]
        $pattern = '/\[(\[?)([a-zA-Z][\w-]*)(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)/s';

        for ($pass = 0; $pass < 5; $pass++) {
            $changed = false;
            $html = preg_replace_callback($pattern, function (array $m) use ($ctx, &$changed) {
                [$full, $open, $name, $attrs] = $m;
                $content = $m[5] ?? null;
                $content = ($content === '' && ! str_contains($full, "[/{$name}]")) ? null : $content;

                if ($open === '[' && ($m[6] ?? '') === ']') {
                    return substr($full, 1, -1); // [[escaped]] shortcode
                }

                if (isset($this->handlers[$name])) {
                    $changed = true;

                    return ($this->handlers[$name])(self::attributes($attrs), $content, $ctx);
                }

                if (in_array($name, self::REMOVED, true)) {
                    $ctx->warn('shortcode_removed', $name);
                    $changed = true;

                    return '';
                }

                // Page-builder wrappers (WPBakery vc_*, Divi et_pb_*): keep what is inside.
                if (preg_match('/^(vc_|et_pb_|fusion_|av_)/', $name)) {
                    $changed = true;

                    return (string) $content;
                }

                if (! self::looksLikeShortcode($name)) {
                    return $full;
                }

                $ctx->warn('shortcode_unknown', $name);
                $changed = true;

                return $content ?? '';
            }, $html);

            if (! $changed) {
                break;
            }
        }

        return $html;
    }

    /** Heuristic: plugin shortcodes are lower-case ASCII and usually contain "_" or "-". */
    private static function looksLikeShortcode(string $name): bool
    {
        return ! preg_match('/^(data|aria)-/', $name) && preg_match('/^[a-z][a-z0-9]*[_-][a-z0-9_-]+$/', $name);
    }

    /** WordPress shortcode_parse_atts() equivalent. */
    public static function attributes(string $text): array
    {
        $atts = [];
        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                if (! empty($m[1])) {
                    $atts[strtolower($m[1])] = $m[2];
                } elseif (! empty($m[3])) {
                    $atts[strtolower($m[3])] = $m[4];
                } elseif (! empty($m[5])) {
                    $atts[strtolower($m[5])] = $m[6];
                } elseif (isset($m[7]) && $m[7] !== '') {
                    $atts[] = $m[7];
                } elseif (isset($m[8]) && $m[8] !== '') {
                    $atts[] = $m[8];
                } elseif (isset($m[9])) {
                    $atts[] = $m[9];
                }
            }
        }

        return $atts;
    }
}
