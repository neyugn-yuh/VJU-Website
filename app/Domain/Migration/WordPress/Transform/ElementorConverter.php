<?php

namespace App\Domain\Migration\WordPress\Transform;

/**
 * Elementor "_elementor_data" JSON (DB source) -> semantic HTML. Layout (sections, columns,
 * containers, spacing) is dropped; content widgets become plain HTML the CMS editor understands.
 */
class ElementorConverter
{
    /** Layout-only or dynamic widgets without content worth keeping. */
    private const IGNORED = ['spacer', 'divider', 'nav-menu', 'search-form', 'social-icons', 'share-buttons', 'sitemap', 'theme-site-logo', 'theme-post-title', 'theme-post-content', 'theme-post-featured-image', 'post-info', 'breadcrumbs', 'form', 'login', 'posts', 'loop-grid', 'loop-carousel', 'archive-posts', 'jet-listing-grid', 'jet-smart-filters-select', 'menu-anchor', 'progress', 'countdown', 'lottie'];

    public function toHtml(array $elements, TransformContext $ctx): string
    {
        $html = '';
        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }
            $html .= ($element['elType'] ?? null) === 'widget'
                ? $this->widget($element['widgetType'] ?? 'unknown', $element['settings'] ?? [], $ctx)
                : $this->toHtml($element['elements'] ?? [], $ctx);
        }

        return $html;
    }

    private function widget(string $type, array $s, TransformContext $ctx): string
    {
        $text = fn (?string $v) => e(trim(strip_tags((string) $v)));
        $url = fn ($link) => is_array($link) ? ($link['url'] ?? null) : (is_string($link) ? $link : null);
        $img = function ($image, ?string $alt = null) use ($ctx) {
            $src = is_array($image) ? ($image['url'] ?? null) : null;
            if (! $src && is_array($image) && ! empty($image['id'])) {
                $src = ($ctx->attachmentUrl)((int) $image['id']);
            }

            return $src ? '<img src="'.e($src).'" alt="'.e((string) ($alt ?? $image['alt'] ?? '')).'">' : '';
        };

        return match ($type) {
            'heading', 'animated-headline' => ($t = $text($s['title'] ?? $s['before_text'] ?? '')) !== ''
                ? '<'.$this->heading($s['header_size'] ?? 'h2').'>'.$t.'</'.$this->heading($s['header_size'] ?? 'h2').'>' : '',
            'text-editor' => (string) ($s['editor'] ?? ''),
            'html' => (string) ($s['html'] ?? ''),
            'shortcode' => (string) ($s['shortcode'] ?? ''),
            'image' => ($i = $img($s['image'] ?? null)) !== ''
                ? '<figure>'.(($u = $url($s['link'] ?? null)) ? '<a href="'.e($u).'">'.$i.'</a>' : $i)
                    .(! empty($s['caption']) ? '<figcaption>'.$text($s['caption']).'</figcaption>' : '').'</figure>' : '',
            'video' => ($u = $s['youtube_url'] ?? $s['vimeo_url'] ?? $url($s['hosted_url'] ?? null) ?? null) ? '<p><a href="'.e($u).'">'.e($u).'</a></p>' : '',
            'button' => ($u = $url($s['link'] ?? null)) ? '<p><a href="'.e($u).'">'.$text($s['text'] ?? $u).'</a></p>' : '',
            'image-box' => $img($s['image'] ?? null)
                .(! empty($s['title_text']) ? '<h3>'.(($u = $url($s['link'] ?? null)) ? '<a href="'.e($u).'">'.$text($s['title_text']).'</a>' : $text($s['title_text'])).'</h3>' : '')
                .(! empty($s['description_text']) ? '<p>'.$text($s['description_text']).'</p>' : ''),
            'icon-box' => (! empty($s['title_text']) ? '<h3>'.$text($s['title_text']).'</h3>' : '').(! empty($s['description_text']) ? '<p>'.$text($s['description_text']).'</p>' : ''),
            'icon-list' => '<ul>'.implode('', array_map(fn ($item) => '<li>'.(($u = $url($item['link'] ?? null)) ? '<a href="'.e($u).'">'.$text($item['text'] ?? '').'</a>' : $text($item['text'] ?? '')).'</li>', $s['icon_list'] ?? [])).'</ul>',
            'tabs', 'accordion', 'toggle' => implode('', array_map(fn ($tab) => '<h3>'.$text($tab['tab_title'] ?? '').'</h3>'.($tab['tab_content'] ?? ''), $s['tabs'] ?? [])),
            'image-gallery', 'gallery' => implode('', array_map(fn ($g) => '<p>'.$img($g).'</p>', $s['wp_gallery'] ?? $s['gallery'] ?? [])),
            'image-carousel', 'media-carousel' => implode('', array_map(fn ($g) => '<p>'.$img($g['image'] ?? $g).'</p>', $s['carousel'] ?? $s['slides'] ?? [])),
            'testimonial' => '<blockquote><p>'.$text($s['testimonial_content'] ?? '').'</p><p>'.$text(($s['testimonial_name'] ?? '').' '.($s['testimonial_job'] ?? '')).'</p></blockquote>',
            'counter' => '<p><strong>'.$text((string) ($s['ending_number'] ?? '')).($s['suffix'] ?? '').'</strong> '.$text($s['title'] ?? '').'</p>',
            'google_maps' => ! empty($s['address']) ? '<p><a href="https://www.google.com/maps/search/?api=1&query='.rawurlencode($s['address']).'">'.$text($s['address']).'</a></p>' : '',
            'call-to-action' => (! empty($s['title']) ? '<h3>'.$text($s['title']).'</h3>' : '').(! empty($s['description']) ? '<p>'.$text($s['description']).'</p>' : '')
                .(($u = $url($s['link'] ?? null)) ? '<p><a href="'.e($u).'">'.$text($s['button'] ?? $u).'</a></p>' : ''),
            default => $this->unknown($type, $s, $ctx),
        };
    }

    private function unknown(string $type, array $s, TransformContext $ctx): string
    {
        if (in_array($type, self::IGNORED, true)) {
            return '';
        }

        // Keep any obvious text so content is never silently lost; the report lists the widget.
        $ctx->warn('elementor_widget_unknown', $type);
        $out = '';
        foreach (['title', 'title_text', 'text', 'description', 'description_text', 'editor', 'content'] as $key) {
            if (! empty($s[$key]) && is_string($s[$key])) {
                $out .= str_contains($s[$key], '<') ? $s[$key] : '<p>'.e($s[$key]).'</p>';
            }
        }

        return $out;
    }

    /** The article title is the page's h1, so content headings start at h2. */
    private function heading(string $size): string
    {
        return in_array($size, ['h2', 'h3', 'h4', 'h5', 'h6'], true) ? $size : 'h2';
    }
}
