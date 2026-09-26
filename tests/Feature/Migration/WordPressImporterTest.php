<?php

namespace Tests\Feature\Migration;

use App\Domain\Content\ContentStatus;
use App\Domain\Migration\WordPress\MigrationReports;
use App\Domain\Migration\WordPress\WordPressImporter;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Content;
use App\Models\ContentTranslation;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Redirect;
use App\Models\User;
use App\Models\WpMigrationMap;
use App\Models\WpMigrationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WordPressImporterTest extends TestCase
{
    use RefreshDatabase;

    private FakeWordPressSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $image = sys_get_temp_dir().'/khuon-vien.jpg';
        imagejpeg(imagecreatetruecolor(800, 600), $image);
        $B = 'https://vju.ac.vn';

        $this->source = new FakeWordPressSource([
            'users' => [['id' => 5, 'name' => 'Phòng Truyền thông', 'email' => 'truyenthong@vju.ac.vn']],
            'terms' => [
                ['id' => 93, 'taxonomy' => 'category', 'name' => 'Tin tức', 'slug' => 'news-vn', 'description' => null, 'parent' => 0, 'lang' => 'vi', 'translations' => ['vi' => 93, 'en' => 24], 'link' => "$B/news-vn/"],
                ['id' => 24, 'taxonomy' => 'category', 'name' => 'News', 'slug' => 'news', 'description' => null, 'parent' => 0, 'lang' => 'en', 'translations' => ['vi' => 93, 'en' => 24], 'link' => "$B/en/news/"],
                ['id' => 404, 'taxonomy' => 'category', 'name' => 'Đào tạo', 'slug' => 'dao-tao', 'description' => null, 'parent' => 93, 'lang' => 'vi', 'translations' => [], 'link' => "$B/news-vn/dao-tao/"],
                ['id' => 454, 'taxonomy' => 'post_tag', 'name' => 'Đại học', 'slug' => 'dai-hoc', 'description' => null, 'parent' => 0, 'lang' => 'vi', 'translations' => [], 'link' => "$B/tag/dai-hoc/"],
            ],
            'media' => [
                ['id' => 700, 'url' => "$B/wp-content/uploads/2024/12/khuon-vien.jpg", 'file' => $image, 'title' => 'Khuôn viên', 'alt' => 'Khuôn viên VJU', 'caption' => null, 'description' => null, 'mime' => 'image/jpeg', 'date_gmt' => '2024-12-01T00:00:00'],
            ],
            'posts' => [
                FakeWordPressSource::post(['id' => 45422, 'lang' => 'vi', 'translations' => ['vi' => 45422, 'en' => 45437], 'slug' => 'hoi-thao-khoa-hoc', 'title' => 'Hội thảo khoa học', 'author' => 5,
                    'featured_media' => 700, 'categories' => [404], 'tags' => [454], 'link' => "$B/hoi-thao-khoa-hoc/",
                    'content' => '<div class="elementor"><p>Nội dung <img src="https://vju.ac.vn/wp-content/uploads/2024/12/khuon-vien-300x200.jpg" alt=""></p></div>',
                    'seo' => ['title' => 'SEO riêng', 'description' => 'Mô tả SEO', 'keywords' => null, 'canonical' => null, 'og_title' => null, 'og_description' => null, 'og_image' => null, 'noindex' => false, 'nofollow' => false]]),
                FakeWordPressSource::post(['id' => 45437, 'lang' => 'en', 'translations' => ['vi' => 45422, 'en' => 45437], 'slug' => 'scientific-seminar', 'title' => 'Scientific seminar', 'author' => 5, 'categories' => [24], 'link' => "$B/en/scientific-seminar/"]),
                FakeWordPressSource::post(['id' => 50, 'status' => 'draft', 'slug' => 'ban-nhap', 'title' => 'Bản nháp', 'link' => "$B/?p=50"]),
                FakeWordPressSource::post(['id' => 51, 'status' => 'future', 'date_gmt' => now()->addDays(3)->utc()->format('Y-m-d\TH:i:s'), 'slug' => 'sap-dang', 'title' => 'Sắp đăng', 'link' => "$B/sap-dang/"]),
                FakeWordPressSource::post(['id' => 52, 'status' => 'trash', 'slug' => 'da-xoa', 'title' => 'Đã xóa', 'link' => "$B/da-xoa/"]),
                // Legacy URL differs from the CMS URL -> 301
                FakeWordPressSource::post(['id' => 53, 'slug' => 'bai-cu', 'title' => 'Bài cũ', 'link' => "$B/2019/05/bai-cu/"]),
                FakeWordPressSource::post(['id' => 10, 'type' => 'page', 'slug' => 'tuyen-sinh', 'title' => 'Tuyển sinh', 'link' => "$B/tuyen-sinh/"]),
                FakeWordPressSource::post(['id' => 11, 'type' => 'page', 'parent' => 10, 'slug' => 'hoc-phi', 'title' => 'Học phí', 'link' => "$B/tuyen-sinh/hoc-phi/"]),
                FakeWordPressSource::post(['id' => 900, 'type' => 'tuition-fees', 'slug' => 'thong-bao-hoc-phi', 'title' => 'Thông báo học phí', 'content' => '', 'link' => "$B/tuition-fees/thong-bao-hoc-phi/", 'meta' => ['file_pdf' => 'https://vju.ac.vn/x.pdf']]),
            ],
            'menus' => [[
                'id' => 3, 'name' => 'Main', 'location' => 'primary', 'lang' => 'vi', 'items' => [
                    ['id' => 1, 'parent' => 0, 'title' => '', 'url' => null, 'type' => 'post_type', 'object' => 'page', 'object_id' => 10, 'target' => null, 'order' => 1],
                    ['id' => 2, 'parent' => 1, 'title' => 'Học phí', 'url' => "$B/tuyen-sinh/hoc-phi/", 'type' => 'custom', 'object' => 'custom', 'object_id' => 0, 'target' => null, 'order' => 1],
                    ['id' => 3, 'parent' => 0, 'title' => 'Tin tức', 'url' => null, 'type' => 'taxonomy', 'object' => 'category', 'object_id' => 93, 'target' => null, 'order' => 2],
                    ['id' => 4, 'parent' => 0, 'title' => 'VNU', 'url' => 'https://vnu.edu.vn', 'type' => 'custom', 'object' => 'custom', 'object_id' => 0, 'target' => '_blank', 'order' => 3],
                ],
            ]],
            'comments' => [['id' => 1, 'post' => 45422, 'parent' => 0, 'author_name' => 'Sinh viên', 'author_email' => 'sv@example.com', 'content' => 'Hay quá', 'date_gmt' => '2025-05-03T00:00:00', 'status' => 'approved']],
        ]);

        config(['cms.wordpress.field_map.tuition-fees' => ['file_pdf' => 'file_id']]);
    }

    private function importAll(bool $dryRun = false): array
    {
        $stats = [];
        foreach (['users', 'taxonomies', 'media', 'pages', 'posts', 'structured', 'comments', 'menus'] as $type) {
            $stats[$type] = app(WordPressImporter::class)->run($this->source, $type, dryRun: $dryRun);
        }

        return $stats;
    }

    public function test_full_import_maps_content_translations_taxonomy_media_seo_menus(): void
    {
        $this->importAll();

        // Polylang group -> one content with two translations
        $post = Content::where('source_id', '45422')->firstOrFail();
        $this->assertSame(['en', 'vi'], $post->translations->pluck('locale')->sort()->values()->all());
        $this->assertSame('/hoi-thao-khoa-hoc/', $post->url('vi'));
        $this->assertSame('/en/scientific-seminar/', $post->url('en'));
        $this->assertSame('Phòng Truyền thông', $post->author->name);
        $this->assertSame('2025-05-01 10:00', $post->published_at->format('Y-m-d H:i'), 'GMT converted to Asia/Ho_Chi_Minh');

        // taxonomy tree, grouped translations, legacy paths preserved
        $news = Category::where('source_id', '24')->orWhere('source_id', '93')->firstOrFail();
        $this->assertSame(['en' => 'news', 'vi' => 'news-vn'], $news->translations->pluck('path', 'locale')->sortKeys()->all());
        $this->assertSame('news-vn/dao-tao', Category::whereHas('translations', fn ($q) => $q->where('slug', 'dao-tao'))->first()->translation('vi')->path);
        $this->assertCount(2, $post->categories);

        // media: featured image + body image rewritten to the CMS copy
        $media = Media::where('source_id', '700')->firstOrFail();
        $this->assertSame($media->id, $post->featured_media_id);
        $this->assertSame('media/2024/12/khuon-vien.jpg', $media->path);
        $this->assertStringContainsString('/storage/media/2024/12/', $post->translation('vi')->body);
        $this->assertStringNotContainsString('wp-content', $post->translation('vi')->body);
        $this->assertSame('/storage/media/2024/12/khuon-vien.jpg', Redirect::where('old_url', '/wp-content/uploads/2024/12/khuon-vien.jpg')->value('new_url'));

        // SEO
        $seo = $post->seo->firstWhere('locale', 'vi');
        $this->assertSame('SEO riêng', $seo->meta_title);
        $this->assertSame('Mô tả SEO', $seo->meta_description);

        // statuses
        $this->assertSame(ContentStatus::Draft, Content::where('source_id', '50')->first()->status);
        $this->assertSame(ContentStatus::Scheduled, Content::where('source_id', '51')->first()->status);
        $this->assertNull(Content::where('source_id', '52')->first(), 'trash is not imported');
        $this->assertSame('skipped', WpMigrationMap::where('source_id', '52')->value('status'));

        // URL changes -> 301, identical URLs -> no redirect
        $this->assertSame('/bai-cu/', Redirect::where('old_url', '/2019/05/bai-cu')->value('new_url'));
        $this->assertNull(Redirect::where('old_url', '/hoi-thao-khoa-hoc')->first());

        // pages hierarchy and structured types
        $this->assertSame('/tuyen-sinh/hoc-phi/', Content::where('source_id', '11')->first()->url('vi'));
        $fee = Content::where('source_id', '900')->first();
        $this->assertSame('/tuition-fees/thong-bao-hoc-phi/', $fee->url('vi'));
        $this->assertSame('https://vju.ac.vn/x.pdf', $fee->fields['file_url']);
        $this->assertSame(['file_pdf' => 'https://vju.ac.vn/x.pdf'], $fee->fields['wp_meta']);

        // users are imported without role and inactive
        $this->assertFalse(User::where('email', 'truyenthong@vju.ac.vn')->first()->is_active);
        $this->assertCount(0, User::where('email', 'truyenthong@vju.ac.vn')->first()->roles);

        // comments
        $this->assertSame(1, Comment::where('content_id', $post->id)->where('status', 'approved')->count());
        $this->assertTrue($post->fresh()->is_commentable);

        // menus: tree, content/category links resolved, external kept
        $menu = Menu::where('location', 'header')->where('locale', 'vi')->firstOrFail();
        $items = $menu->items()->get();
        $this->assertSame('content', $items->firstWhere('parent_id', null)->item_type);
        $this->assertSame('Tuyển sinh', $items->firstWhere('parent_id', null)->label, 'empty WP label falls back to the linked title');
        $this->assertSame('content', $items->firstWhere('label', 'Học phí')->item_type, 'same-site URL resolved to content');
        $this->assertSame('category', $items->firstWhere('label', 'Tin tức')->item_type);
        $this->assertSame('external_url', $items->firstWhere('label', 'VNU')->item_type);
    }

    public function test_second_run_is_idempotent(): void
    {
        $this->importAll();
        $counts = [Content::count(), Media::count(), Category::count(), Redirect::count(), Comment::count(), ContentTranslation::count()];

        $stats = $this->importAll();

        $this->assertSame($counts, [Content::count(), Media::count(), Category::count(), Redirect::count(), Comment::count(), ContentTranslation::count()]);
        $this->assertSame(0, $stats['posts']['imported'] + $stats['posts']['updated']);
        $this->assertGreaterThan(0, $stats['posts']['unchanged']);
    }

    public function test_changed_source_record_updates_in_place(): void
    {
        $this->importAll();
        $this->source->data['posts'][0]['title'] = 'Hội thảo khoa học (cập nhật)';
        $this->source->data['posts'][0]['modified_gmt'] = '2025-06-01T00:00:00';

        $stats = app(WordPressImporter::class)->run($this->source, 'posts');

        $this->assertSame(1, $stats['updated']);
        $this->assertSame('Hội thảo khoa học (cập nhật)', Content::where('source_id', '45422')->first()->translation('vi')->title);
    }

    public function test_dry_run_persists_nothing(): void
    {
        $stats = $this->importAll(dryRun: true);

        $this->assertSame(0, Content::count());
        $this->assertSame(0, Category::count());
        $this->assertSame(0, WpMigrationMap::count());
        $this->assertGreaterThan(0, $stats['posts']['imported']);
        $this->assertTrue(WpMigrationRun::where('dry_run', true)->exists(), 'runs are logged even in dry-run');
    }

    public function test_single_id_and_locale_filters(): void
    {
        app(WordPressImporter::class)->run($this->source, 'taxonomies');
        app(WordPressImporter::class)->run($this->source, 'posts', ['locale' => 'en']);

        $this->assertSame(1, Content::count());
        $this->assertSame(['en'], Content::first()->translations->pluck('locale')->all());
    }

    public function test_recoverable_failures_are_recorded_and_the_batch_continues(): void
    {
        $this->source->data['posts'][] = FakeWordPressSource::post(['id' => 60, 'slug' => 'loi', 'title' => 'Ảnh lỗi', 'featured_media' => 99999, 'link' => 'https://vju.ac.vn/loi/']);

        app(WordPressImporter::class)->run($this->source, 'posts');

        $row = WpMigrationMap::where('source_id', '60')->first();
        $this->assertSame('success', $row->status);
        $this->assertContains('missing_featured_media: 99999', $row->warnings);
    }

    public function test_validation_and_reports(): void
    {
        $this->importAll();
        Storage::fake('local');

        $validation = app(MigrationReports::class)->validate(['counts' => ['post' => ['vi' => 4, 'en' => 1, 'ja' => 0]]]);
        $this->assertTrue($validation['passed'], json_encode($validation['issues']));
        $this->assertSame(0, $validation['urls']['failed']);

        $report = app(MigrationReports::class)->report();
        $this->assertSame(1, $report['by_type']['post_tag']['success']);
        Storage::disk('local')->assertExists(['migration/migration-report.json', 'migration/migration-report.csv', 'migration/url-inventory.csv']);
        $this->assertStringContainsString('MIGRATE_301', Storage::disk('local')->get('migration/url-inventory.csv'));
    }
}
