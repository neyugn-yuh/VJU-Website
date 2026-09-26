<?php

namespace Tests\Unit\Migration;

use App\Domain\Migration\WordPress\SeoMapper;
use App\Domain\Migration\WordPress\Transform\ElementorConverter;
use App\Domain\Migration\WordPress\Transform\HtmlTransformer;
use App\Domain\Migration\WordPress\Transform\MenuHtmlExtractor;
use App\Domain\Migration\WordPress\Transform\ShortcodeRegistry;
use App\Domain\Migration\WordPress\Transform\TransformContext;
use App\Domain\Migration\WordPress\WordPressImporter;
use Tests\TestCase;

class TransformTest extends TestCase
{
    private function transform(string $html, string $format = 'rendered', ?TransformContext $ctx = null, ?array $elementor = null): string
    {
        return app(HtmlTransformer::class)->transform($html, $format, $ctx ?? TransformContext::passthrough(), $elementor);
    }

    public function test_elementor_wrappers_are_removed_and_content_kept(): void
    {
        $html = '<div data-elementor-type="wp-post" class="elementor elementor-45437"><section class="elementor-section"><div class="elementor-container"><div class="elementor-widget elementor-widget-heading"><div class="elementor-widget-container"><h1 class="elementor-heading-title">Tiêu đề</h1></div></div>'
            .'<div class="elementor-widget elementor-widget-text-editor"><div class="elementor-widget-container">Đoạn văn <strong>quan trọng</strong></div></div>'
            .'<div class="elementor-widget elementor-widget-spacer"><div class="elementor-spacer"></div></div>'
            .'<div class="elementor-widget elementor-widget-nav-menu"><nav><ul><li><a href="/">Menu</a></li></ul></nav></div>'
            .'<div class="elementor-hidden-desktop"><p>Bản mobile trùng lặp</p></div></div></section></div><style>.x{}</style><script>alert(1)</script>';

        $out = $this->transform($html);

        $this->assertSame('<h2>Tiêu đề</h2><p>Đoạn văn <strong>quan trọng</strong></p>', $out);
    }

    public function test_media_urls_links_and_lazy_images_are_rewritten(): void
    {
        $ctx = new TransformContext(
            mediaUrl: fn (string $url) => str_contains($url, 'missing') ? null : '/storage/media/'.basename($url),
            attachmentUrl: fn () => null,
            legacyHost: 'vju.ac.vn',
        );

        $out = $this->transform(
            '<p><img class="wp-image-1" src="data:image/gif;base64,xx" data-lazy-src="https://vju.ac.vn/wp-content/uploads/2024/12/a-300x200.jpg" srcset="x 300w" alt="Ảnh"></p>'
            .'<p><a href="https://vju.ac.vn/en/news/abc/?ref=1#top" class="btn">Tin</a> <a href="https://vju.ac.vn/wp-content/uploads/2024/01/quy-che.pdf" target="_blank">PDF</a> <a href="https://vnu.edu.vn">VNU</a></p>'
            .'<p><img src="https://vju.ac.vn/wp-content/uploads/missing.jpg" alt=""></p>',
            ctx: $ctx
        );

        $this->assertStringContainsString('<img src="/storage/media/a-300x200.jpg" alt="Ảnh">', $out);
        $this->assertStringContainsString('<a href="/en/news/abc/?ref=1#top">Tin</a>', $out);
        $this->assertStringContainsString('href="/storage/media/quy-che.pdf"', $out);
        $this->assertStringContainsString('<a href="https://vnu.edu.vn">VNU</a>', $out);
        $this->assertStringNotContainsString('srcset', $out);
        $this->assertContains('missing_media: https://vju.ac.vn/wp-content/uploads/missing.jpg', $ctx->warnings);
    }

    public function test_youtube_iframes_become_links_the_renderer_embeds(): void
    {
        $out = $this->transform('<p>Video:</p><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed" title="Lễ tốt nghiệp"></iframe>');

        $this->assertStringContainsString('<p><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">Lễ tốt nghiệp</a></p>', $out);
    }

    public function test_shortcodes(): void
    {
        $ctx = TransformContext::passthrough();
        $out = $this->transform("[caption id=\"a\" width=\"300\"]<img src=\"/x.jpg\" alt=\"\"> Chú thích[/caption]\n\nGiá [Tin tức] vẫn giữ.\n\n[wpforms id=\"12\"]\n\n[my_plugin foo=\"1\"]Nội dung bên trong[/my_plugin]", 'raw', $ctx);

        $this->assertStringContainsString('<figure><img src="https://vju.ac.vn/x.jpg" alt=""><figcaption>Chú thích</figcaption></figure>', $out);
        $this->assertStringContainsString('[Tin tức]', $out, 'bracketed text is not a shortcode');
        $this->assertStringContainsString('Nội dung bên trong', $out, 'unknown enclosing shortcode keeps its content');
        $this->assertStringNotContainsString('wpforms', $out);
        $this->assertContains('shortcode_removed: wpforms', $ctx->warnings);
        $this->assertContains('shortcode_unknown: my_plugin', $ctx->warnings);
    }

    public function test_raw_content_gets_paragraphs(): void
    {
        $this->assertSame('<p>Dòng một<br>dòng hai</p><p>Đoạn hai</p>', str_replace("\n", '', $this->transform("Dòng một\ndòng hai\n\nĐoạn hai", 'raw')));
    }

    public function test_elementor_json_conversion(): void
    {
        $data = [['elType' => 'section', 'elements' => [['elType' => 'column', 'elements' => [
            ['elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'Chương trình', 'header_size' => 'h1']],
            ['elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => ['editor' => '<p>Giới thiệu</p>']],
            ['elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => ['icon_list' => [['text' => 'Mục 1'], ['text' => 'Mục 2', 'link' => ['url' => 'https://vju.ac.vn/lien-he/']]]]],
            ['elType' => 'widget', 'widgetType' => 'video', 'settings' => ['youtube_url' => 'https://youtu.be/dQw4w9WgXcQ']],
            ['elType' => 'widget', 'widgetType' => 'spacer', 'settings' => []],
            ['elType' => 'widget', 'widgetType' => 'bdt-flip-box', 'settings' => ['title' => 'Không mất nội dung']],
        ]]]]];
        $ctx = TransformContext::passthrough();

        $out = $this->transform('', 'elementor', $ctx, $data);

        $this->assertStringContainsString('<h2>Chương trình</h2><p>Giới thiệu</p><ul><li>Mục 1</li><li><a href="/lien-he/">Mục 2</a></li></ul>', $out);
        $this->assertStringContainsString('https://youtu.be/dQw4w9WgXcQ', $out);
        $this->assertStringContainsString('Không mất nội dung', $out);
        $this->assertContains('elementor_widget_unknown: bdt-flip-box', $ctx->warnings);
    }

    public function test_yoast_mapping_drops_defaults_and_keeps_custom_values(): void
    {
        $default = SeoMapper::fromYoastHead(['title' => 'Bài A - VNU Vietnam Japan University', 'og_site_name' => 'VNU Vietnam Japan University', 'canonical' => 'https://vju.ac.vn/bai-a/', 'robots' => ['index' => 'index', 'follow' => 'follow'], 'og_title' => 'Bài A - VNU Vietnam Japan University'], 'Bài A', 'https://vju.ac.vn/bai-a/');
        $this->assertNull($default['title']);
        $this->assertNull($default['canonical']);
        $this->assertFalse($default['noindex']);

        $custom = SeoMapper::fromYoastHead(['title' => 'Tiêu đề SEO riêng - VNU Vietnam Japan University', 'og_site_name' => 'VNU Vietnam Japan University', 'description' => 'Mô tả', 'canonical' => 'https://vju.ac.vn/khac/', 'robots' => ['index' => 'noindex', 'follow' => 'nofollow']], 'Bài A', 'https://vju.ac.vn/bai-a/');
        $this->assertSame('Tiêu đề SEO riêng', $custom['title']);
        $this->assertSame('Mô tả', $custom['description']);
        $this->assertSame('https://vju.ac.vn/khac/', $custom['canonical']);
        $this->assertTrue($custom['noindex'] && $custom['nofollow']);

        $meta = SeoMapper::fromPostMeta(['_yoast_wpseo_title' => '%%title%% %%sep%% %%sitename%%', '_yoast_wpseo_metadesc' => 'Desc', '_yoast_wpseo_meta-robots-noindex' => '1', '_yoast_wpseo_focuskw' => 'học bổng'], 'Bài A', 'VJU', null);
        $this->assertNull($meta['title']);
        $this->assertSame('Desc', $meta['description']);
        $this->assertTrue($meta['noindex']);
        $this->assertSame('học bổng', $meta['keywords']);
    }

    public function test_upload_url_variants_identify_the_original(): void
    {
        $this->assertContains('/wp-content/uploads/2024/12/dji_0045.jpg', WordPressImporter::urlVariants('https://vju.ac.vn/wp-content/uploads/2024/12/DJI_0045-scaled.jpg'));
        $this->assertContains('/wp-content/uploads/2024/12/photo.jpg', WordPressImporter::urlVariants('https://vju.ac.vn/wp-content/uploads/2024/12/photo-1024x768.jpg'));
    }

    public function test_shortcode_attribute_parsing(): void
    {
        $this->assertSame(['id' => '12', 'title' => 'a b', 'x' => 'y', 0 => 'flag'], ShortcodeRegistry::attributes(' id="12" title=\'a b\' x=y flag'));
    }

    public function test_menu_extraction_from_elementor_nav(): void
    {
        $html = '<nav class="elementor-nav-menu--main elementor-nav-menu--layout-horizontal"><ul><li><a href="https://vju.ac.vn/tuyen-sinh/">Tuyển sinh</a><ul class="sub-menu"><li><a href="https://vju.ac.vn/tuyen-sinh/dai-hoc/">Đại học</a></li></ul></li><li><a href="#">Khác</a></li></ul></nav>'
            .'<nav class="elementor-nav-menu--main elementor-nav-menu--layout-vertical"><ul><li><a href="https://vju.ac.vn/lien-he/">Liên hệ</a></li></ul></nav>';

        $menus = (new MenuHtmlExtractor)->extract($html, 'https://vju.ac.vn');

        $this->assertCount(3, $menus['header']);
        $this->assertSame($menus['header'][0]['id'], $menus['header'][1]['parent']);
        $this->assertSame('Liên hệ', $menus['footer'][0]['title']);
    }

    /** Silence unused import warnings for converter when running this class alone. */
    public function test_converter_ignores_non_arrays(): void
    {
        $this->assertSame('', (new ElementorConverter)->toHtml(['x', null], TransformContext::passthrough()));
    }
}
