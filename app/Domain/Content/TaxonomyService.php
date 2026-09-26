<?php

namespace App\Domain\Content;

use App\Domain\SEO\RedirectResolver;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\ContentTranslation;
use App\Models\Tag;
use App\Models\TagTranslation;
use App\Support\Locales;
use App\Support\PublicCache;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Category/tag writes. Category URLs are hierarchical without a base, like the current site
 * ("/news-vn/dao-tao/"), so paths are derived from the parent chain and kept unique per locale.
 *
 * Translations payload: [locale => ['name' => ..., 'slug' => ..., 'description' => ...]]
 */
class TaxonomyService
{
    public function saveCategory(Category $category, ?int $parentId, array $translations, bool $redirects = true): Category
    {
        return DB::transaction(function () use ($category, $parentId, $translations, $redirects) {
            $this->guardCycle($category, $parentId);

            $category->parent_id = $parentId;
            $category->slug = $category->slug ?: Slug::make(collect($translations)->pluck('name')->filter()->first() ?? 'category', Locales::default());
            $category->save();

            $existing = $category->translations()->get()->keyBy('locale');

            foreach ($translations as $locale => $input) {
                if (! Locales::isSupported($locale)) {
                    continue;
                }
                $translation = $existing->get($locale);

                if (blank($input['name'] ?? null)) {
                    $translation?->delete();

                    continue;
                }

                $slug = Slug::make(filled($input['slug'] ?? null) ? $input['slug'] : $input['name'], $locale) ?: (string) $category->id;
                $parentPath = $parentId ? CategoryTranslation::where('category_id', $parentId)->where('locale', $locale)->value('path') : null;
                $path = $this->uniquePath($category, $locale, $parentPath ? $parentPath.'/' : '', $slug);

                $translation ??= new CategoryTranslation(['locale' => $locale]);
                $oldPath = $translation->exists ? $translation->path : null;
                $translation->fill(['name' => trim($input['name']), 'slug' => basename($path), 'path' => $path, 'description' => $input['description'] ?? null]);
                $category->translations()->save($translation);
                if ($oldPath !== $path) {
                    RedirectResolver::clear(Locales::path($locale, $path));
                }

                if ($oldPath !== null && $oldPath !== $path) {
                    $this->rebase($locale, $oldPath, $path, $redirects);
                }
            }

            PublicCache::flush();

            return $category;
        });
    }

    public function saveTag(Tag $tag, array $translations): Tag
    {
        return DB::transaction(function () use ($tag, $translations) {
            $tag->slug = $tag->slug ?: Slug::make(collect($translations)->pluck('name')->filter()->first() ?? 'tag', Locales::default());
            $tag->save();

            foreach ($translations as $locale => $input) {
                if (! Locales::isSupported($locale)) {
                    continue;
                }
                if (blank($input['name'] ?? null)) {
                    $tag->translations()->where('locale', $locale)->delete();

                    continue;
                }

                $base = Slug::make(filled($input['slug'] ?? null) ? $input['slug'] : $input['name'], $locale) ?: (string) $tag->id;
                $slug = $base;
                for ($i = 2; TagTranslation::where('locale', $locale)->where('slug', $slug)->where('tag_id', '!=', $tag->id)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }

                $tag->translations()->updateOrCreate(['locale' => $locale], ['name' => trim($input['name']), 'slug' => $slug]);
            }

            PublicCache::flush();

            return $tag;
        });
    }

    private function guardCycle(Category $category, ?int $parentId): void
    {
        if ($parentId && $category->exists && in_array($parentId, $category->descendantIds(), true)) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be nested under itself or its descendants.']);
        }
    }

    private function uniquePath(Category $category, string $locale, string $base, string $slug): string
    {
        for ($i = 1; ; $i++) {
            $candidate = $base.($i === 1 ? $slug : "{$slug}-{$i}");
            $taken = ($base === '' && in_array($candidate, Slug::RESERVED, true))
                || CategoryTranslation::where('locale', $locale)->where('path', $candidate)->where('category_id', '!=', $category->id)->exists()
                || ContentTranslation::where('locale', $locale)->where('path', $candidate)->exists();
            if (! $taken) {
                return $candidate;
            }
        }
    }

    private function rebase(string $locale, string $oldPath, string $newPath, bool $redirects): void
    {
        if ($redirects) {
            RedirectResolver::record(Locales::path($locale, $oldPath), Locales::path($locale, $newPath));
        }

        CategoryTranslation::where('locale', $locale)
            ->where('path', 'like', addcslashes($oldPath, '%_\\').'/%')
            ->each(function (CategoryTranslation $child) use ($locale, $oldPath, $newPath, $redirects) {
                $old = $child->path;
                $child->update(['path' => $newPath.substr($old, strlen($oldPath))]);
                if ($redirects) {
                    RedirectResolver::record(Locales::path($locale, $old), Locales::path($locale, $child->path));
                }
            });
    }
}
