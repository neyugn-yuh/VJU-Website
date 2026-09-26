<?php

namespace Tests\Feature\Public;

use App\Models\Comment;
use App\Settings\SeoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAndCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_index_and_locale_sitemaps(): void
    {
        $this->makeContent(['translations' => ['vi' => ['title' => 'Bài một'], 'en' => ['title' => 'Post one']]]);
        $this->makeContent(['translations' => ['vi' => ['title' => 'Ẩn', 'seo' => ['robots_index' => false]]]]);
        $this->makeContent(['status' => 'draft', 'translations' => ['vi' => ['title' => 'Nháp']]]);

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('sitemap-posts-vi.xml');

        $xml = $this->get('/sitemap-posts-vi.xml')->assertOk()->getContent();
        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap is valid XML');
        $this->assertStringContainsString('/bai-mot/', $xml);
        $this->assertStringContainsString('hreflang="en"', $xml);
        $this->assertStringNotContainsString('/an/', $xml, 'noindex content is excluded');
        $this->assertStringNotContainsString('/nhap/', $xml, 'drafts are excluded');

        $this->get('/sitemap-bogus-vi.xml')->assertNotFound();
    }

    public function test_robots_txt_and_staging_switch(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: http://localhost/sitemap.xml')->assertSee('Disallow: /admin');

        app(SeoSettings::class)->fill(['discourage_indexing' => true])->save();
        $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
        $this->makeContent(['translations' => ['vi' => ['title' => 'Staging']]]);
        $this->visit('/staging/')->assertSee('noindex,nofollow', false);
    }

    public function test_comment_submission_is_pending_and_sanitized(): void
    {
        $content = $this->makeContent(['is_commentable' => true]);

        $this->from($content->url('vi'))->post("/comments/{$content->id}", [
            'name' => '<b>An</b>', 'email' => 'AN@Example.com', 'body' => 'Xin chào <script>alert(1)</script>',
        ])->assertRedirect($content->url('vi'))->assertSessionHas('success');

        $comment = Comment::first();
        $this->assertSame('pending', $comment->status);
        $this->assertSame('An', $comment->name);
        $this->assertSame('an@example.com', $comment->email);
        $this->assertStringNotContainsString('<script>', $comment->body);
    }

    public function test_comment_honeypot_validation_and_closed_comments(): void
    {
        $open = $this->makeContent(['is_commentable' => true, 'translations' => ['vi' => ['title' => 'Mở']]]);
        $closed = $this->makeContent(['translations' => ['vi' => ['title' => 'Đóng']]]);

        $this->post("/comments/{$open->id}", ['name' => 'Bot', 'email' => 'b@x.com', 'body' => 'spam', 'website' => 'http://spam'])->assertSessionHasErrors('website');
        $this->post("/comments/{$open->id}", ['name' => '', 'email' => 'bad', 'body' => ''])->assertSessionHasErrors(['name', 'email', 'body']);
        $this->post("/comments/{$closed->id}", ['name' => 'A', 'email' => 'a@x.com', 'body' => 'hello'])->assertNotFound();
        $this->assertSame(0, Comment::count());
    }

    public function test_comment_rate_limit(): void
    {
        $content = $this->makeContent(['is_commentable' => true]);
        $payload = ['name' => 'A', 'email' => 'a@x.com', 'body' => 'Bình luận'];

        for ($i = 0; $i < 3; $i++) {
            $this->post("/comments/{$content->id}", $payload)->assertRedirect();
        }
        $this->post("/comments/{$content->id}", $payload)->assertStatus(429);
    }

    public function test_approved_comments_only_are_public(): void
    {
        $content = $this->makeContent(['is_commentable' => true]);
        Comment::create(['content_id' => $content->id, 'name' => 'Duyệt', 'email' => 'a@x.com', 'body' => 'Đã duyệt', 'status' => 'approved']);
        Comment::create(['content_id' => $content->id, 'name' => 'Chờ', 'email' => 'b@x.com', 'body' => 'Chờ duyệt', 'status' => 'pending']);

        $this->visit($content->url('vi'))->assertInertia(fn ($page) => $page->has('comments', 1)->where('comments.0.body', 'Đã duyệt'));
    }
}
