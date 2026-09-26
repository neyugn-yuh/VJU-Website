<?php

namespace Tests\Feature\Admin;

use App\Domain\Content\TaxonomyService;
use App\Domain\Menu\MenuService;
use App\Filament\Resources\Contents\Pages\CreateContent;
use App\Filament\Resources\Contents\Pages\EditContent;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Content;
use App\Models\ContentDraft;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);
    }

    public function test_every_admin_screen_renders_for_admin(): void
    {
        $content = $this->makeContent(['type' => 'page', 'translations' => ['vi' => ['title' => 'Trang A', 'blocks' => [['type' => 'faq', 'data' => ['heading' => 'FAQ', 'items' => [['question' => 'Q?', 'answer' => '<p>A</p>']]]]]]]]);
        $category = app(TaxonomyService::class)->saveCategory(new Category, null, ['vi' => ['name' => 'Tin tức']]);
        $menu = Menu::create(['name' => 'Header', 'location' => 'header', 'locale' => 'vi']);
        app(MenuService::class)->sync($menu, [['label' => 'A', 'item_type' => 'category', 'category_id' => $category->id]]);
        Comment::create(['content_id' => $content->id, 'name' => 'A', 'email' => 'a@x.com', 'body' => 'Hi', 'status' => 'pending']);

        $this->actingAs($this->admin);

        foreach ([
            '/admin', '/admin/contents?tab=post', '/admin/contents?tab=page', '/admin/contents?tab=tuition_fee',
            '/admin/contents/create?type=post', '/admin/contents/create?type=page', '/admin/contents/create?type=opportunity',
            "/admin/contents/{$content->id}/edit",
            '/admin/categories', '/admin/tags', '/admin/media', '/admin/media?display=list', '/admin/media/upload',
            '/admin/menus', "/admin/menus/{$menu->id}/edit", '/admin/comments', '/admin/redirects',
            '/admin/users', '/admin/users/create', '/admin/audit-logs',
            '/admin/manage-site', '/admin/manage-contact', '/admin/manage-social', '/admin/manage-seo', '/admin/manage-analytics',
        ] as $url) {
            $this->assertSame(200, $this->get($url)->status(), "GET {$url}");
        }
    }

    public function test_create_and_edit_content_through_the_admin_form(): void
    {
        $this->actingAs($this->admin);

        Livewire::withQueryParams(['type' => 'post'])->test(CreateContent::class)
            ->fillForm([
                'status' => 'published',
                'translations' => [
                    'vi' => ['title' => 'Bài từ admin', 'body' => '<p>Nội dung</p>'],
                    'en' => ['title' => 'From admin'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $content = Content::firstOrFail();
        $this->assertSame('bai-tu-admin', $content->translation('vi')->slug);
        $this->assertTrue($content->isPublished());

        Livewire::test(EditContent::class, ['record' => $content->id])
            ->assertFormSet(['translations.vi.title' => 'Bài từ admin'])
            ->fillForm(['translations.vi.title' => 'Bài đã sửa'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Bài đã sửa', $content->fresh()->translation('vi')->title);
        $this->assertSame(2, $content->revisions()->count());
    }

    public function test_contributor_cannot_publish_through_the_form(): void
    {
        $contributor = $this->userWithRole(User::ROLE_CONTRIBUTOR);
        $this->actingAs($contributor);

        Livewire::withQueryParams(['type' => 'post'])->test(CreateContent::class)
            ->fillForm(['status' => 'published', 'translations' => ['vi' => ['title' => 'Cố xuất bản']]])
            ->call('create')
            ->assertHasFormErrors(['status']);

        $this->assertSame(0, Content::count());
    }

    public function test_autosave_stores_a_draft_separately(): void
    {
        $content = $this->makeContent(['translations' => ['vi' => ['title' => 'Gốc']]]);
        $this->actingAs($this->admin);

        Livewire::test(EditContent::class, ['record' => $content->id])
            ->fillForm(['translations.vi.title' => 'Đang gõ dở'])
            ->call('autosave');

        $this->assertSame('Gốc', $content->fresh()->translation('vi')->title, 'record is untouched');
        $draft = ContentDraft::where('content_id', $content->id)->where('user_id', $this->admin->id)->firstOrFail();
        $this->assertSame('Đang gõ dở', $draft->payload['translations']['vi']['title']);
    }

    public function test_editor_cannot_reach_settings_or_users(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_EDITOR));

        $this->get('/admin/manage-site')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/manage-seo')->assertOk(); // seo.manage
    }
}
