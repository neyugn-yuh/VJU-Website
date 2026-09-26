<?php

namespace Tests\Feature\Auth;

use App\Domain\Content\ContentService;
use App\Domain\Content\ContentStatus;
use App\Domain\Content\ContentWorkflow;
use App\Models\Content;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admin_can_manage_any_content(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $content = $this->makeContent([], $this->userWithRole(User::ROLE_AUTHOR));

        foreach (['view', 'update', 'publish', 'delete', 'restore'] as $ability) {
            $this->assertTrue($admin->can($ability, $content), $ability);
        }
    }

    public function test_editor_can_publish_pending_content_of_others(): void
    {
        $editor = $this->userWithRole(User::ROLE_EDITOR);
        $content = $this->makeContent(['status' => 'pending_review'], $this->userWithRole(User::ROLE_CONTRIBUTOR));

        app(ContentService::class)->transition($content, ContentStatus::Published, $editor);

        $this->assertSame(ContentStatus::Published, $content->fresh()->status);
        $this->assertNotNull($content->fresh()->published_at);
    }

    public function test_author_cannot_edit_another_authors_post(): void
    {
        $author = $this->userWithRole(User::ROLE_AUTHOR);
        $content = $this->makeContent([], $this->userWithRole(User::ROLE_AUTHOR));

        $this->assertFalse($author->can('update', $content));
        $this->expectException(AuthorizationException::class);
        app(ContentService::class)->save($content, ['translations' => ['vi' => ['title' => 'Hacked']]], $author);
    }

    public function test_author_can_publish_own_post(): void
    {
        $author = $this->userWithRole(User::ROLE_AUTHOR);

        $content = app(ContentService::class)->save(new Content, [
            'type' => 'post', 'status' => 'published',
            'translations' => ['vi' => ['title' => 'Bài của tôi']],
        ], $author);

        $this->assertSame($author->id, $content->author_id);
        $this->assertSame(ContentStatus::Published, $content->status);
    }

    public function test_contributor_cannot_publish(): void
    {
        $contributor = $this->userWithRole(User::ROLE_CONTRIBUTOR);

        $this->expectException(ValidationException::class);
        app(ContentService::class)->save(new Content, [
            'type' => 'post', 'status' => 'published',
            'translations' => ['vi' => ['title' => 'Không được xuất bản']],
        ], $contributor);
    }

    public function test_contributor_submits_for_review_and_loses_edit_after_publish(): void
    {
        $contributor = $this->userWithRole(User::ROLE_CONTRIBUTOR);
        $service = app(ContentService::class);

        $content = $service->save(new Content, ['type' => 'post', 'status' => 'draft', 'translations' => ['vi' => ['title' => 'Bản nháp']]], $contributor);
        $service->transition($content, ContentStatus::PendingReview, $contributor);
        $this->assertSame(ContentStatus::PendingReview, $content->fresh()->status);

        $allowed = app(ContentWorkflow::class)->allowedTargets($contributor, $content->fresh());
        $this->assertArrayNotHasKey('published', $allowed);
        $this->assertArrayNotHasKey('scheduled', $allowed);

        $service->transition($content->fresh(), ContentStatus::Published, $this->userWithRole(User::ROLE_EDITOR));
        $this->assertFalse($contributor->can('update', $content->fresh()));
    }

    public function test_author_id_in_payload_is_ignored_for_non_editors(): void
    {
        $author = $this->userWithRole(User::ROLE_AUTHOR);
        $victim = $this->userWithRole(User::ROLE_AUTHOR);

        $content = app(ContentService::class)->save(new Content, [
            'type' => 'post', 'author_id' => $victim->id, 'translations' => ['vi' => ['title' => 'X']],
        ], $author);

        $this->assertSame($author->id, $content->author_id);
    }

    public function test_users_without_role_get_403_in_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_author_cannot_open_user_management_or_settings(): void
    {
        $author = $this->userWithRole(User::ROLE_AUTHOR);

        $this->actingAs($author)->get('/admin/users')->assertForbidden();
        $this->actingAs($author)->get('/admin/manage-site')->assertForbidden();
        $this->actingAs($author)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_author_listing_only_shows_own_content(): void
    {
        $author = $this->userWithRole(User::ROLE_AUTHOR);
        $this->makeContent(['translations' => ['vi' => ['title' => 'Bài của người khác']]], $this->userWithRole(User::ROLE_AUTHOR));
        $this->makeContent(['translations' => ['vi' => ['title' => 'Bài của chính tôi']]], $author);

        $this->actingAs($author)->get('/admin/contents?tab=post')
            ->assertOk()
            ->assertSee('Bài của chính tôi')
            ->assertDontSee('Bài của người khác');
    }

    public function test_editor_cannot_force_delete(): void
    {
        $editor = $this->userWithRole(User::ROLE_EDITOR);
        $content = $this->makeContent();
        $content->delete();

        $this->assertFalse($editor->can('forceDelete', $content->fresh()));
        $this->assertTrue($this->userWithRole(User::ROLE_ADMIN)->can('forceDelete', $content->fresh()));
    }
}
