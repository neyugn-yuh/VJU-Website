<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\ContentStats;
use App\Filament\Widgets\DashboardHero;
use App\Filament\Widgets\PostsByAuthorChart;
use App\Filament\Widgets\PostsByCategoryChart;
use App\Filament\Widgets\PostsByMonthChart;
use App\Filament\Widgets\RecentActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);
    }

    public function test_dashboard_page_loads_with_linear_theme_link(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin');
        $response->assertOk();
        $response->assertSee('/css/filament/linear-theme.css', false);
    }

    public function test_dashboard_widgets_render_successfully(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(DashboardHero::class)
            ->assertSuccessful()
            ->assertSee('VJU Academic CMS Engine')
            ->assertSee('Tác vụ nhanh')
            ->assertSee('Bài viết mới');

        Livewire::test(ContentStats::class)
            ->assertSuccessful()
            ->assertSee('Bài viết (Posts)')
            ->assertSee('Trang tĩnh (Pages)')
            ->assertSee('Đã xuất bản')
            ->assertSee('Bản nháp (Drafts)')
            ->assertSee('Chờ phê duyệt')
            ->assertSee('Tệp đa phương tiện')
            ->assertSee('Lượt xem (30 ngày)');

        Livewire::test(PostsByMonthChart::class)
            ->assertSuccessful()
            ->assertSee('Bài viết xuất bản theo tháng');

        Livewire::test(PostsByCategoryChart::class)
            ->assertSuccessful()
            ->assertSee('Chuyên mục bài viết hàng đầu');

        Livewire::test(PostsByAuthorChart::class)
            ->assertSuccessful()
            ->assertSee('Tác giả có nhiều bài viết');

        Livewire::test(RecentActivity::class)
            ->assertSuccessful()
            ->assertSee('Nhật ký hoạt động gần đây (Audit Log)');
    }
}
