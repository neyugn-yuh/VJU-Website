<?php

namespace Tests\Feature\Content;

use App\Domain\Content\ContentService;
use App\Domain\Content\ContentStatus;
use App\Models\AuditLog;
use App\Models\Content;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentService $service;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->service = app(ContentService::class);
        $this->editor = $this->userWithRole(User::ROLE_EDITOR);
    }

    public function test_creates_multilingual_content_with_wordpress_style_slugs(): void
    {
        $content = $this->service->save(new Content, [
            'type' => 'post',
            'translations' => [
                'vi' => ['title' => 'Tuyển sinh Đại học năm 2026'],
                'en' => ['title' => 'Undergraduate Admissions 2026'],
                'ja' => ['title' => '日越大学 入学'],
            ],
        ], $this->editor);

        $this->assertSame('tuyen-sinh-dai-hoc-nam-2026', $content->translation('vi')->slug);
        $this->assertSame('undergraduate-admissions-2026', $content->translation('en')->slug);
        $this->assertSame('日越大学-入学', $content->translation('ja')->slug);
        $this->assertSame('/en/undergraduate-admissions-2026/', $content->url('en'));
        $this->assertSame('/ja/'.rawurlencode('日越大学-入学').'/', $content->url('ja'));
    }

    public function test_duplicate_slugs_get_a_suffix_per_locale(): void
    {
        $a = $this->makeContent(['translations' => ['vi' => ['title' => 'Thông báo']]]);
        $b = $this->makeContent(['translations' => ['vi' => ['title' => 'Thông báo'], 'en' => ['title' => 'Thong bao']]]);

        $this->assertSame('thong-bao', $a->translation('vi')->slug);
        $this->assertSame('thong-bao-2', $b->translation('vi')->slug);
        $this->assertSame('thong-bao', $b->translation('en')->slug);
    }

    public function test_reserved_first_segments_are_not_claimable(): void
    {
        $content = $this->makeContent(['type' => 'page', 'translations' => ['vi' => ['title' => 'Admin']]]);

        $this->assertSame('admin-2', $content->translation('vi')->path);
    }

    public function test_page_hierarchy_paths_and_redirects_when_parent_slug_changes(): void
    {
        $parent = $this->makeContent(['type' => 'page', 'translations' => ['vi' => ['title' => 'Tuyển sinh']]]);
        $child = $this->makeContent(['type' => 'page', 'parent_id' => $parent->id, 'translations' => ['vi' => ['title' => 'Học phí']]]);
        $this->assertSame('tuyen-sinh/hoc-phi', $child->translation('vi')->path);

        $this->service->save($parent, ['translations' => ['vi' => ['title' => 'Tuyển sinh', 'slug' => 'tuyen-sinh-2026']]], $this->editor);

        $this->assertSame('tuyen-sinh-2026/hoc-phi', $child->fresh()->translation('vi')->path);
        $this->assertSame('/tuyen-sinh-2026/', Redirect::where('old_url', '/tuyen-sinh')->value('new_url'));
        $this->assertSame('/tuyen-sinh-2026/hoc-phi/', Redirect::where('old_url', '/tuyen-sinh/hoc-phi')->value('new_url'));
    }

    public function test_page_cannot_be_nested_under_itself(): void
    {
        $a = $this->makeContent(['type' => 'page', 'translations' => ['vi' => ['title' => 'A']]]);
        $b = $this->makeContent(['type' => 'page', 'parent_id' => $a->id, 'translations' => ['vi' => ['title' => 'B']]]);

        $this->expectException(ValidationException::class);
        $this->service->save($a, ['parent_id' => $b->id], $this->editor);
    }

    public function test_body_is_sanitized_server_side(): void
    {
        $content = $this->makeContent(['translations' => ['vi' => ['title' => 'XSS', 'body' => '<p onclick="x()">Hi<script>alert(1)</script></p><a href="javascript:alert(1)">x</a><img src="/a.jpg" onerror="x()"><h2>Ok</h2>']]]);
        $body = $content->translation('vi')->body;

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('<h2>Ok</h2>', $body);
    }

    public function test_every_save_creates_a_revision_and_restore_keeps_history(): void
    {
        $content = $this->service->save(new Content, ['type' => 'post', 'translations' => ['vi' => ['title' => 'Phiên bản 1', 'body' => '<p>Một</p>']]], $this->editor);
        $this->service->save($content, ['translations' => ['vi' => ['title' => 'Phiên bản 2', 'slug' => 'phien-ban-1', 'body' => '<p>Hai</p>']]], $this->editor);
        $this->assertSame(2, $content->revisions()->count());

        $first = $content->revisions()->where('version', 1)->first();
        $this->service->restoreRevision($content->fresh(), $first, $this->editor);

        $content = $content->fresh();
        $this->assertSame('Phiên bản 1', $content->translation('vi')->title);
        $this->assertSame('<p>Một</p>', $content->translation('vi')->body);
        $this->assertSame(3, $content->revisions()->count(), 'restore never deletes history');
        $this->assertTrue(AuditLog::where('action', 'restore')->where('subject_id', $content->id)->exists());
    }

    public function test_empty_title_removes_a_translation(): void
    {
        $content = $this->makeContent(['translations' => ['vi' => ['title' => 'Việt'], 'en' => ['title' => 'English']]]);

        $this->service->save($content, ['translations' => ['en' => ['title' => '']]], $this->editor);

        $this->assertNull($content->fresh()->translation('en'));
        $this->assertNotNull($content->fresh()->translation('vi'));
    }

    public function test_scheduling_and_idempotent_publication(): void
    {
        $content = $this->service->save(new Content, [
            'type' => 'post', 'status' => 'scheduled', 'scheduled_at' => now()->addHour(),
            'translations' => ['vi' => ['title' => 'Hẹn giờ']],
        ], $this->editor);
        $this->assertSame(ContentStatus::Scheduled, $content->status);

        $this->assertSame(0, $this->service->publishDue());

        $this->travel(2)->hours();
        $this->assertSame(1, $this->service->publishDue());
        $this->assertSame(0, $this->service->publishDue(), 'second run publishes nothing');
        $this->assertSame(ContentStatus::Published, $content->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'publish')->where('subject_id', $content->id)->count());
    }

    public function test_scheduling_in_the_past_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->save(new Content, ['type' => 'post', 'status' => 'scheduled', 'scheduled_at' => now()->subDay(), 'translations' => ['vi' => ['title' => 'x']]], $this->editor);
    }

    public function test_trash_and_restore_keep_status_consistent(): void
    {
        $content = $this->makeContent();

        $content->delete();
        $this->assertSoftDeleted($content);
        $this->assertSame(ContentStatus::Trash, Content::withTrashed()->find($content->id)->status);

        Content::withTrashed()->find($content->id)->restore();
        $this->assertSame(ContentStatus::Draft, $content->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'trash')->exists());
        $this->assertTrue(AuditLog::where('action', 'restore')->exists());
    }

    public function test_publishing_is_audited(): void
    {
        $content = $this->service->save(new Content, ['type' => 'post', 'translations' => ['vi' => ['title' => 'Draft']]], $this->editor);
        $this->service->transition($content, ContentStatus::Published, $this->editor);
        $this->service->transition($content->fresh(), ContentStatus::Draft, $this->editor);

        $this->assertSame(['create', 'publish', 'unpublish'], AuditLog::where('subject_id', $content->id)->whereIn('action', ['create', 'publish', 'unpublish'])->orderBy('id')->pluck('action')->all());
    }
}
