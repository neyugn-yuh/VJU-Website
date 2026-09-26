<?php

namespace Tests\Feature\Public;

use App\Domain\Content\TaxonomyService;
use App\Models\Category;
use App\Models\Redirect;
use App\Models\Tag;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_split(): void
    {
        $this->assertSame(['vi', 'tin-tuc/abc'], Locales::split('/tin-tuc/abc/'));
        $this->assertSame(['en', 'news'], Locales::split('/en/news/'));
        $this->assertSame(['ja', ''], Locales::split('/ja'));
        $this->assertSame(['vi', 'english-page'], Locales::split('/english-page/'));
        $this->assertSame('/en/a/b/', Locales::path('en', 'a/b'));
        $this->assertSame('/', Locales::path('vi'));
    }

    public function test_homepages_for_every_locale(): void
    {
        foreach (['/', '/en/', '/ja/'] as $url) {
            $this->visit($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Home'));
        }
    }

    public function test_article_renders_with_server_side_seo(): void
    {
        $content = $this->makeContent(['translations' => [
            'vi' => ['title' => 'Lễ khai giảng', 'excerpt' => 'Mô tả ngắn', 'body' => '<p>Nội dung</p><p><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">https://www.youtube.com/watch?v=dQw4w9WgXcQ</a></p>',
                'seo' => ['meta_description' => 'Mô tả SEO', 'robots_index' => true, 'robots_follow' => true]],
            'en' => ['title' => 'Opening ceremony'],
        ]]);

        $this->visit('/le-khai-giang/')
            ->assertOk()
            ->assertSee('<title inertia>Lễ khai giảng - Trường Đại học Việt Nhật - ĐHQGHN</title>', false)
            ->assertSee('<meta name="description" content="Mô tả SEO" inertia="description">', false)
            ->assertSee('<link rel="canonical" href="http://localhost/le-khai-giang/">', false)
            ->assertSee('hreflang="en" href="http://localhost/en/opening-ceremony/"', false)
            ->assertSee('hreflang="x-default"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('application/ld+json', false)
            ->assertInertia(fn (Assert $page) => $page->component('Article')
                ->where('content.title', 'Lễ khai giảng')
                ->where('content.body', fn ($body) => str_contains($body, 'youtube-nocookie.com/embed/dQw4w9WgXcQ'))
                ->has('alternates', 2));

        $this->assertNotNull($content);
    }

    public function test_missing_translation_is_404_not_another_language(): void
    {
        $this->makeContent(['translations' => ['vi' => ['title' => 'Chỉ tiếng Việt']]]);

        $this->visit('/chi-tieng-viet/')->assertOk();
        $this->visit('/en/chi-tieng-viet/')->assertNotFound();
    }

    public function test_drafts_are_not_public_and_preview_needs_permission(): void
    {
        $this->seedRoles();
        $draft = $this->makeContent(['status' => 'draft', 'translations' => ['vi' => ['title' => 'Bản nháp bí mật']]]);

        $this->visit('/ban-nhap-bi-mat/')->assertNotFound();
        $this->get("/preview/{$draft->id}")->assertRedirect('/admin/login');
        $this->actingAs($this->userWithRole('Contributor'))->get("/preview/{$draft->id}")->assertForbidden();
        $this->actingAs($this->userWithRole('Editor'))->get("/preview/{$draft->id}")->assertOk()
            ->assertSee('noindex,nofollow', false);
    }

    public function test_trailing_slash_canonicalization(): void
    {
        $this->makeContent(['translations' => ['vi' => ['title' => 'Giới thiệu']]]);

        $this->get('/gioi-thieu')->assertStatus(301)->assertHeader('Location', 'http://localhost/gioi-thieu/');
        $this->get('/search?q=x')->assertStatus(301)->assertHeader('Location', 'http://localhost/search/?q=x');
        $this->get('/khong-ton-tai')->assertNotFound();
    }

    public function test_legacy_redirects_and_retired_urls(): void
    {
        $this->makeContent(['translations' => ['vi' => ['title' => 'Trang mới']]]);
        Redirect::create(['old_url' => '/trang-cu/', 'new_url' => '/trang-moi/', 'status_code' => 301]);
        Redirect::create(['old_url' => '/da-xoa/', 'status_code' => 410]);

        $this->visit('/trang-cu/')->assertStatus(301)->assertHeader('Location', 'http://localhost/trang-moi/');
        $this->get('/TRANG-CU?utm=x')->assertHeader('Location', 'http://localhost/trang-moi/');
        $this->visit('/da-xoa/')->assertStatus(410);
        $this->assertSame(2, Redirect::where('old_url', '/trang-cu')->value('hits'));
    }

    public function test_live_content_beats_a_stale_redirect(): void
    {
        $this->makeContent(['translations' => ['vi' => ['title' => 'Song song']]]);
        Redirect::create(['old_url' => '/song-song/', 'new_url' => '/khac/', 'status_code' => 301]);

        $this->visit('/song-song/')->assertOk();
    }

    public function test_category_and_tag_archives_with_pagination(): void
    {
        $taxonomy = app(TaxonomyService::class);
        $news = $taxonomy->saveCategory(new Category, null, ['vi' => ['name' => 'Tin tức', 'slug' => 'news-vn']]);
        $child = $taxonomy->saveCategory(new Category, $news->id, ['vi' => ['name' => 'Đào tạo']]);
        $tag = $taxonomy->saveTag(new Tag, ['vi' => ['name' => 'Học bổng']]);

        for ($i = 1; $i <= 14; $i++) {
            $this->makeContent(['categories' => [$child->id], 'tags' => [$tag->id], 'published_at' => now()->subDays($i), 'translations' => ['vi' => ['title' => "Tin {$i}"]]]);
        }

        $this->visit('/news-vn/dao-tao/')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Listing')->has('items', 12)->where('pagination.last', 2));
        $this->visit('/news-vn/')->assertOk()->assertInertia(fn (Assert $p) => $p->where('pagination.total', 14)->has('subcategories', 1));
        $this->visit('/news-vn/page/2/')->assertOk()->assertInertia(fn (Assert $p) => $p->has('items', 2));
        $this->visit('/news-vn/page/9/')->assertNotFound();
        $this->visit('/tag/hoc-bong/')->assertOk()->assertInertia(fn (Assert $p) => $p->where('title', 'Học bổng'));
    }

    public function test_wordpress_search_query_is_supported(): void
    {
        $this->get('/?s=abc')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Search')->where('q', 'abc'));
    }

    public function test_structured_type_archive(): void
    {
        $this->makeContent(['type' => 'tuition_fee', 'translations' => ['vi' => ['title' => 'Thông báo thu học phí']]]);

        $this->visit('/tuition-fees/thong-bao-thu-hoc-phi/')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Article'));
        $this->visit('/tuition-fees/')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Listing')->has('items', 1));
    }

    public function test_health_endpoint(): void
    {
        $this->getJson('/health')->assertOk()->assertJsonPath('status', 'ok')->assertJsonMissingPath('checks.env');
    }

    public function test_security_headers(): void
    {
        $this->get('/')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
