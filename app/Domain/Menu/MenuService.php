<?php

namespace App\Domain\Menu;

use App\Models\Category;
use App\Models\Content;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\PublicCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menus are edited as a nested tree (children arrays), so cycles cannot be expressed.
 * Node: {label, item_type, content_id?, category_id?, url?, target?, icon?, children[]}
 */
class MenuService
{
    public const MAX_DEPTH = 4;

    public function sync(Menu $menu, array $tree): void
    {
        DB::transaction(function () use ($menu, $tree) {
            $this->validate($tree, 1);
            $menu->items()->delete();
            $this->insert($menu, $tree, null);
        });

        PublicCache::flush();
    }

    /** Tree for the admin form. */
    public function tree(Menu $menu): array
    {
        $items = $menu->items()->get()->groupBy('parent_id');

        $build = function (?int $parentId) use (&$build, $items) {
            return ($items[$parentId ?? ''] ?? collect())->map(fn (MenuItem $i) => [
                'label' => $i->label,
                'item_type' => $i->item_type,
                'content_id' => $i->content_id,
                'category_id' => $i->category_id,
                'url' => $i->url,
                'target' => $i->target,
                'icon' => $i->icon,
                'children' => $build($i->id),
            ])->values()->all();
        };

        return $build(null);
    }

    /**
     * Resolved tree for the public site. Items pointing at unpublished or untranslated content are dropped.
     *
     * @return list<array{label: string, url: string, target: ?string, icon: ?string, children: array}>
     */
    public function forLocation(string $location, string $locale): array
    {
        return PublicCache::remember("menu:$location:$locale", 3600, function () use ($location, $locale) {
            $menu = Menu::where('location', $location)->where('locale', $locale)->first();
            if (! $menu) {
                return [];
            }

            $items = $menu->items()->with(['content.translations', 'category.translations'])->get();
            $grouped = $items->groupBy(fn (MenuItem $i) => $i->parent_id ?? 0);

            $build = function (int $parentId) use (&$build, $grouped, $locale) {
                $nodes = [];
                foreach ($grouped[$parentId] ?? [] as $item) {
                    $url = $this->resolveUrl($item, $locale);
                    if ($url === null) {
                        continue;
                    }
                    $nodes[] = [
                        'label' => $item->label,
                        'url' => $url,
                        'target' => $item->target,
                        'icon' => $item->icon,
                        'children' => $build($item->id),
                    ];
                }

                return $nodes;
            };

            return $build(0);
        });
    }

    private function resolveUrl(MenuItem $item, string $locale): ?string
    {
        return match ($item->item_type) {
            'content' => $item->content?->isPublished() ? $item->content->url($locale) : null,
            'category' => $item->category?->translation($locale)?->url(),
            default => $item->url ?: '#',
        };
    }

    private function validate(array $nodes, int $depth): void
    {
        if ($nodes && $depth > self::MAX_DEPTH) {
            throw ValidationException::withMessages(['items' => 'Menus can be nested at most '.self::MAX_DEPTH.' levels deep.']);
        }

        foreach ($nodes as $node) {
            $label = trim((string) ($node['label'] ?? ''));
            $type = $node['item_type'] ?? null;

            $error = match (true) {
                $label === '' => 'Every menu item needs a label.',
                ! array_key_exists($type, MenuItem::TYPES) => "Invalid item type for \"{$label}\".",
                $type === 'content' && ! Content::whereKey($node['content_id'] ?? 0)->exists() => "\"{$label}\" links to content that does not exist.",
                $type === 'category' && ! Category::whereKey($node['category_id'] ?? 0)->exists() => "\"{$label}\" links to a category that does not exist.",
                $type === 'external_url' && ! filter_var($node['url'] ?? '', FILTER_VALIDATE_URL) => "\"{$label}\" needs a valid absolute URL.",
                $type === 'external_url' && ! preg_match('#^https?://#i', $node['url'] ?? '') => "\"{$label}\" must use http(s).",
                $type === 'custom' && ! (str_starts_with($node['url'] ?? '', '/') || str_starts_with($node['url'] ?? '', '#')) => "\"{$label}\" custom path must start with / or #.",
                default => null,
            };

            if ($error) {
                throw ValidationException::withMessages(['items' => $error]);
            }

            $this->validate($node['children'] ?? [], $depth + 1);
        }
    }

    private function insert(Menu $menu, array $nodes, ?int $parentId): void
    {
        foreach (array_values($nodes) as $order => $node) {
            $type = $node['item_type'];
            $item = $menu->items()->create([
                'parent_id' => $parentId,
                'label' => trim($node['label']),
                'item_type' => $type,
                'content_id' => $type === 'content' ? $node['content_id'] : null,
                'category_id' => $type === 'category' ? $node['category_id'] : null,
                'url' => in_array($type, ['external_url', 'custom'], true) ? $node['url'] : null,
                'target' => ($node['target'] ?? null) ?: null,
                'icon' => ($node['icon'] ?? null) ?: null,
                'sort_order' => $order,
            ]);

            $this->insert($menu, $node['children'] ?? [], $item->id);
        }
    }
}
