<?php

namespace Tests\Feature\Migration;

use App\Domain\Migration\WordPress\Sources\DatabaseSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Minimal WordPress schema (prefix wptest_) in the test database, populated the way Polylang,
 * Yoast, Elementor and nav menus store data, read through the real DatabaseSource.
 */
class DatabaseSourceTest extends TestCase
{
    private const TABLES = ['posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'options', 'users', 'comments'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        $this->seedWordPress();
    }

    protected function tearDown(): void
    {
        foreach (self::TABLES as $t) {
            Schema::connection('wordpress')->dropIfExists("wptest_{$t}");
        }
        parent::tearDown();
    }

    public function test_posts_with_polylang_yoast_and_elementor(): void
    {
        $posts = collect(DatabaseSource::make()->posts('post'))->keyBy('id');

        $this->assertCount(2, $posts);
        $vi = $posts[100];
        $this->assertSame('vi', $vi['lang']);
        $this->assertSame(['vi' => 100, 'en' => 101], $vi['translations']);
        $this->assertSame('elementor', $vi['content_format']);
        $this->assertSame('heading', $vi['elementor'][0]['elements'][0]['widgetType']);
        $this->assertSame(500, $vi['featured_media']);
        $this->assertSame([10], $vi['categories']);
        $this->assertSame('Mô tả Yoast', $vi['seo']['description']);
        $this->assertTrue($vi['seo']['noindex']);
        $this->assertNull($vi['seo']['title'], 'Yoast default template is not frozen as a custom title');
        $this->assertSame('https://vju.ac.vn/hoi-thao/', $vi['link']);
        $this->assertSame('https://vju.ac.vn/en/seminar/', $posts[101]['link']);
        $this->assertSame('raw', $posts[101]['content_format']);

        $this->assertCount(1, iterator_to_array(DatabaseSource::make()->posts('post', ['locale' => 'en'])));
    }

    public function test_terms_media_users_menus_comments_inventory(): void
    {
        $source = DatabaseSource::make();

        $cat = collect($source->terms('category'))->firstWhere('id', 10);
        $this->assertSame('vi', $cat['lang']);
        $this->assertSame(['vi' => 10, 'en' => 11], $cat['translations']);

        $media = iterator_to_array($source->media());
        $this->assertSame('https://vju.ac.vn/wp-content/uploads/2024/12/anh.jpg', $media[0]['url']);
        $this->assertSame('Ảnh khuôn viên', $media[0]['alt']);

        $this->assertSame('bientap@vju.ac.vn', iterator_to_array($source->users())[0]['email']);

        $menu = iterator_to_array($source->menus())[0];
        $this->assertSame(['primary', 'en'], [$menu['location'], $menu['lang']], 'Polylang per-language menu location');
        $this->assertSame('post_type', $menu['items'][0]['type']);
        $this->assertSame(101, $menu['items'][0]['object_id']);

        $this->assertSame('approved', iterator_to_array($source->comments())[0]['status']);

        $redirect = iterator_to_array($source->redirects())[0];
        $this->assertSame(['academics/post-graduate', 'en/academics/post-graduate', 301, 'plain'], [$redirect['origin'], $redirect['target'], $redirect['status'], $redirect['format']]);

        $inv = $source->inventory();
        $this->assertContains('polylang-pro/polylang.php', $inv['active_plugins']);
        $this->assertSame(1, $inv['published_by_language']['vi']['post']);
        $this->assertSame(1, $inv['elementor_widgets']['heading']);
        $this->assertSame(1, $inv['shortcodes']['caption']);
    }

    private function createSchema(): void
    {
        $s = Schema::connection('wordpress');
        foreach (self::TABLES as $t) {
            $s->dropIfExists("wptest_{$t}");
        }
        $s->create('wptest_posts', function (Blueprint $t) {
            $t->unsignedBigInteger('ID')->primary();
            $t->unsignedBigInteger('post_author')->default(0);
            $t->dateTime('post_date_gmt')->nullable();
            $t->dateTime('post_modified_gmt')->nullable();
            $t->longText('post_content')->nullable();
            $t->text('post_title')->nullable();
            $t->text('post_excerpt')->nullable();
            $t->string('post_status', 20);
            $t->string('comment_status', 20)->default('open');
            $t->string('post_name', 200)->default('');
            $t->unsignedBigInteger('post_parent')->default(0);
            $t->string('guid')->default('');
            $t->integer('menu_order')->default(0);
            $t->string('post_type', 20);
            $t->string('post_mime_type', 100)->default('');
        });
        $s->create('wptest_postmeta', function (Blueprint $t) {
            $t->id('meta_id');
            $t->unsignedBigInteger('post_id');
            $t->string('meta_key')->nullable();
            $t->longText('meta_value')->nullable();
        });
        $s->create('wptest_terms', function (Blueprint $t) {
            $t->unsignedBigInteger('term_id')->primary();
            $t->string('name', 200);
            $t->string('slug', 200);
        });
        $s->create('wptest_term_taxonomy', function (Blueprint $t) {
            $t->unsignedBigInteger('term_taxonomy_id')->primary();
            $t->unsignedBigInteger('term_id');
            $t->string('taxonomy', 32);
            $t->longText('description')->nullable();
            $t->unsignedBigInteger('parent')->default(0);
            $t->bigInteger('count')->default(0);
        });
        $s->create('wptest_term_relationships', function (Blueprint $t) {
            $t->unsignedBigInteger('object_id');
            $t->unsignedBigInteger('term_taxonomy_id');
        });
        $s->create('wptest_options', function (Blueprint $t) {
            $t->id('option_id');
            $t->string('option_name');
            $t->longText('option_value');
        });
        $s->create('wptest_users', function (Blueprint $t) {
            $t->unsignedBigInteger('ID')->primary();
            $t->string('user_login');
            $t->string('user_email');
            $t->string('display_name');
            $t->dateTime('user_registered')->nullable();
        });
        $s->create('wptest_comments', function (Blueprint $t) {
            $t->unsignedBigInteger('comment_ID')->primary();
            $t->unsignedBigInteger('comment_post_ID');
            $t->unsignedBigInteger('comment_parent')->default(0);
            $t->string('comment_author');
            $t->string('comment_author_email');
            $t->text('comment_content');
            $t->dateTime('comment_date_gmt');
            $t->string('comment_approved', 20);
            $t->string('comment_type', 20)->default('comment');
        });
    }

    private function seedWordPress(): void
    {
        $db = DB::connection('wordpress');
        $db->table('wptest_options')->insert([
            ['option_name' => 'blogname', 'option_value' => 'VNU Vietnam Japan University'],
            ['option_name' => 'siteurl', 'option_value' => 'https://vju.ac.vn'],
            ['option_name' => 'stylesheet', 'option_value' => 'vju'],
            ['option_name' => 'active_plugins', 'option_value' => serialize(['polylang-pro/polylang.php', 'wordpress-seo/wp-seo.php'])],
            ['option_name' => 'polylang', 'option_value' => serialize(['nav_menus' => ['vju' => ['primary' => ['en' => 30]]]])],
            ['option_name' => 'wpseo-premium-redirects-base', 'option_value' => serialize([['origin' => 'academics/post-graduate', 'url' => 'en/academics/post-graduate', 'type' => 301, 'format' => 'plain']])],
        ]);
        $db->table('wptest_users')->insert(['ID' => 7, 'user_login' => 'bientap', 'user_email' => 'BienTap@vju.ac.vn', 'display_name' => 'Biên tập']);

        // terms: languages, translation groups, categories, menu
        $db->table('wptest_terms')->insert([
            ['term_id' => 1, 'name' => 'Tiếng Việt', 'slug' => 'vi'], ['term_id' => 2, 'name' => 'English', 'slug' => 'en'],
            ['term_id' => 3, 'name' => 'pll_vi', 'slug' => 'pll_vi'], ['term_id' => 4, 'name' => 'pll_en', 'slug' => 'pll_en'],
            ['term_id' => 5, 'name' => 'pll_group', 'slug' => 'pll_group'], ['term_id' => 6, 'name' => 'pll_tgroup', 'slug' => 'pll_tgroup'],
            ['term_id' => 10, 'name' => 'Tin tức', 'slug' => 'news-vn'], ['term_id' => 11, 'name' => 'News', 'slug' => 'news'],
            ['term_id' => 30, 'name' => 'Main EN', 'slug' => 'main-en'],
        ]);
        $db->table('wptest_term_taxonomy')->insert([
            ['term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'language', 'description' => ''],
            ['term_taxonomy_id' => 2, 'term_id' => 2, 'taxonomy' => 'language', 'description' => ''],
            ['term_taxonomy_id' => 3, 'term_id' => 3, 'taxonomy' => 'term_language', 'description' => ''],
            ['term_taxonomy_id' => 4, 'term_id' => 4, 'taxonomy' => 'term_language', 'description' => ''],
            ['term_taxonomy_id' => 5, 'term_id' => 5, 'taxonomy' => 'post_translations', 'description' => serialize(['vi' => 100, 'en' => 101])],
            ['term_taxonomy_id' => 6, 'term_id' => 6, 'taxonomy' => 'term_translations', 'description' => serialize(['vi' => 10, 'en' => 11])],
            ['term_taxonomy_id' => 10, 'term_id' => 10, 'taxonomy' => 'category', 'description' => ''],
            ['term_taxonomy_id' => 11, 'term_id' => 11, 'taxonomy' => 'category', 'description' => ''],
            ['term_taxonomy_id' => 30, 'term_id' => 30, 'taxonomy' => 'nav_menu', 'description' => ''],
        ]);

        foreach ([
            ['ID' => 100, 'post_author' => 7, 'post_date_gmt' => '2025-01-01 00:00:00', 'post_modified_gmt' => '2025-01-02 00:00:00', 'post_content' => '[caption]x[/caption] fallback', 'post_title' => 'Hội thảo', 'post_excerpt' => '', 'post_status' => 'publish', 'post_name' => 'hoi-thao', 'post_type' => 'post'],
            ['ID' => 101, 'post_author' => 7, 'post_date_gmt' => '2025-01-01 00:00:00', 'post_modified_gmt' => '2025-01-02 00:00:00', 'post_content' => "Line one\n\nLine two", 'post_title' => 'Seminar', 'post_excerpt' => '', 'post_status' => 'draft', 'post_name' => 'seminar', 'post_type' => 'post'],
            ['ID' => 500, 'post_author' => 7, 'post_date_gmt' => '2024-12-01 00:00:00', 'post_modified_gmt' => '2024-12-01 00:00:00', 'post_content' => '', 'post_title' => 'Ảnh', 'post_excerpt' => '', 'post_status' => 'inherit', 'post_name' => 'anh', 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg'],
            ['ID' => 600, 'post_author' => 7, 'post_date_gmt' => '2025-01-01 00:00:00', 'post_modified_gmt' => '2025-01-01 00:00:00', 'post_content' => '', 'post_title' => '', 'post_excerpt' => '', 'post_status' => 'publish', 'post_name' => '600', 'post_type' => 'nav_menu_item'],
        ] as $row) {
            $db->table('wptest_posts')->insert($row);
        }
        $db->table('wptest_term_relationships')->insert([
            ['object_id' => 100, 'term_taxonomy_id' => 1], ['object_id' => 101, 'term_taxonomy_id' => 2],
            ['object_id' => 100, 'term_taxonomy_id' => 5], ['object_id' => 101, 'term_taxonomy_id' => 5],
            ['object_id' => 100, 'term_taxonomy_id' => 10], ['object_id' => 101, 'term_taxonomy_id' => 11],
            ['object_id' => 10, 'term_taxonomy_id' => 3], ['object_id' => 11, 'term_taxonomy_id' => 4],
            ['object_id' => 10, 'term_taxonomy_id' => 6], ['object_id' => 11, 'term_taxonomy_id' => 6],
            ['object_id' => 600, 'term_taxonomy_id' => 30],
        ]);
        $db->table('wptest_postmeta')->insert([
            ['post_id' => 100, 'meta_key' => '_elementor_edit_mode', 'meta_value' => 'builder'],
            ['post_id' => 100, 'meta_key' => '_elementor_data', 'meta_value' => json_encode([['elType' => 'section', 'elements' => [['elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'Hội thảo']]]]])],
            ['post_id' => 100, 'meta_key' => '_thumbnail_id', 'meta_value' => '500'],
            ['post_id' => 100, 'meta_key' => '_yoast_wpseo_title', 'meta_value' => '%%title%% %%sep%% %%sitename%%'],
            ['post_id' => 100, 'meta_key' => '_yoast_wpseo_metadesc', 'meta_value' => 'Mô tả Yoast'],
            ['post_id' => 100, 'meta_key' => '_yoast_wpseo_meta-robots-noindex', 'meta_value' => '1'],
            ['post_id' => 500, 'meta_key' => '_wp_attached_file', 'meta_value' => '2024/12/anh.jpg'],
            ['post_id' => 500, 'meta_key' => '_wp_attachment_image_alt', 'meta_value' => 'Ảnh khuôn viên'],
            ['post_id' => 600, 'meta_key' => '_menu_item_type', 'meta_value' => 'post_type'],
            ['post_id' => 600, 'meta_key' => '_menu_item_object', 'meta_value' => 'post'],
            ['post_id' => 600, 'meta_key' => '_menu_item_object_id', 'meta_value' => '101'],
            ['post_id' => 600, 'meta_key' => '_menu_item_menu_item_parent', 'meta_value' => '0'],
        ]);
        $db->table('wptest_comments')->insert(['comment_ID' => 1, 'comment_post_ID' => 100, 'comment_author' => 'SV', 'comment_author_email' => 'sv@x.com', 'comment_content' => 'Hay', 'comment_date_gmt' => '2025-01-03 00:00:00', 'comment_approved' => '1']);
    }
}
