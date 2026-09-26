<?php

namespace Tests\Feature\Admin;

use App\Domain\Menu\MenuService;
use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MenuServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_tree_roundtrip_and_public_resolution(): void
    {
        $published = $this->makeContent(['type' => 'page', 'translations' => ['vi' => ['title' => 'Giới thiệu'], 'en' => ['title' => 'About']]]);
        $draft = $this->makeContent(['type' => 'page', 'status' => 'draft', 'translations' => ['vi' => ['title' => 'Nháp']]]);
        $menu = Menu::create(['name' => 'Header', 'location' => 'header', 'locale' => 'vi']);

        $tree = [
            ['label' => 'Giới thiệu', 'item_type' => 'content', 'content_id' => $published->id, 'children' => [
                ['label' => 'Nháp', 'item_type' => 'content', 'content_id' => $draft->id],
                ['label' => 'VNU', 'item_type' => 'external_url', 'url' => 'https://vnu.edu.vn', 'target' => '_blank'],
            ]],
            ['label' => 'Liên hệ', 'item_type' => 'custom', 'url' => '/lien-he/'],
        ];
        app(MenuService::class)->sync($menu, $tree);

        $this->assertSame('VNU', app(MenuService::class)->tree($menu)[0]['children'][1]['label']);

        $public = app(MenuService::class)->forLocation('header', 'vi');
        $this->assertSame('/gioi-thieu/', $public[0]['url']);
        $this->assertCount(1, $public[0]['children'], 'items pointing at unpublished content are hidden');
        $this->assertSame('_blank', $public[0]['children'][0]['target']);
    }

    public function test_invalid_items_are_rejected(): void
    {
        $menu = Menu::create(['name' => 'Header', 'location' => 'header', 'locale' => 'vi']);

        foreach ([
            [['label' => 'X', 'item_type' => 'content', 'content_id' => 999999]],
            [['label' => 'X', 'item_type' => 'external_url', 'url' => 'javascript:alert(1)']],
            [['label' => '', 'item_type' => 'custom', 'url' => '/']],
            [['label' => 'X', 'item_type' => 'custom', 'url' => 'no-slash']],
        ] as $tree) {
            try {
                app(MenuService::class)->sync($menu, $tree);
                $this->fail('Expected validation error for '.json_encode($tree));
            } catch (ValidationException) {
                $this->assertSame(0, $menu->items()->count());
            }
        }
    }
}
