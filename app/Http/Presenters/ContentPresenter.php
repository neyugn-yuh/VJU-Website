<?php

namespace App\Http\Presenters;

use App\Domain\Content\ContentRenderer;
use App\Domain\Content\ContentType;
use App\Models\Category;
use App\Models\Content;
use App\Models\Media;
use App\Support\Locales;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Shapes content for the React pages (resources/js/Types/index.ts mirrors these arrays). */
class ContentPresenter
{
    public function __construct(private readonly ContentRenderer $renderer) {}

    /** Eager loads needed by card(). */
    public const CARD_RELATIONS = [
        'translations:id,content_id,locale,title,slug,path,excerpt',
        'featuredMedia',
        'categories.translations:id,category_id,locale,name,slug,path',
    ];

    public function card(Content $content, string $locale, ?Collection $fieldMedia = null): array
    {
        $t = $content->translation($locale);
        $category = $content->categories->first();

        return [
            'id' => $content->id,
            'type' => $content->type->value,
            'title' => $t?->title,
            'url' => $t?->url(),
            'excerpt' => Str::limit(trim(html_entity_decode(strip_tags((string) $t?->excerpt))), 220),
            'date' => $content->published_at?->toIso8601String(),
            'image' => $content->featuredMedia?->toPublicArray(),
            'category' => $category && ($ct = $category->translation($locale)) ? ['name' => $ct->name, 'url' => $ct->url()] : null,
            'fields' => $this->fields($content, $fieldMedia),
        ];
    }

    /** @param  iterable<Content>  $contents */
    public function cards(iterable $contents, string $locale): array
    {
        $contents = collect($contents);
        $fileIds = $contents
            ->map(fn (Content $content) => (int) data_get($content->fields, 'file_id'))
            ->filter()
            ->unique()
            ->values();
        $fieldMedia = $fileIds->isEmpty()
            ? collect()
            : Media::whereIn('id', $fileIds)->get()->keyBy('id');

        return $contents->map(fn (Content $c) => $this->card($c, $locale, $fieldMedia))->values()->all();
    }

    public function full(Content $content, string $locale): array
    {
        $content->loadMissing([
            'author:id,name',
            'featuredMedia',
            'categories.translations:id,category_id,locale,name,slug,path',
            'tags.translations:id,tag_id,locale,name,slug',
            'parent.translations:id,content_id,locale,title,slug,path',
        ]);

        // The body and blocks are large. Load them only for the active locale;
        // alternate URLs are fetched as a small projection in alternates().
        $currentTranslation = $content->relationLoaded('translations')
            ? $content->translations->firstWhere('locale', $locale)
            : null;
        if (! $currentTranslation
            || ! array_key_exists('body', $currentTranslation->getAttributes())
            || ! array_key_exists('blocks', $currentTranslation->getAttributes())) {
            $content->setRelation('translations', $content->translations()
                ->where('locale', $locale)
                ->get(['id', 'content_id', 'locale', 'title', 'slug', 'path', 'excerpt', 'body', 'blocks']));
        }
        $t = $content->translation($locale);

        return [
            ...$this->card($content, $locale),
            'excerpt' => $t->excerpt ? trim(html_entity_decode(strip_tags($t->excerpt), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : null,
            'body' => $this->renderer->render($t->body),
            'blocks' => $this->blocks($t->blocks ?? [], $locale),
            'template' => $content->template ?: 'default',
            'updated' => $content->updated_at?->toIso8601String(),
            'author' => $content->author?->name,
            'categories' => $content->categories->map(fn (Category $c) => ($ct = $c->translation($locale)) ? ['name' => $ct->name, 'url' => $ct->url()] : null)->filter()->values(),
            'tags' => $content->tags->map(fn ($tag) => ($tt = $tag->translation($locale)) ? ['name' => $tt->name, 'url' => $tt->url()] : null)->filter()->values(),
            'commentable' => $content->is_commentable,
        ];
    }

    /** Resolves media ids and dynamic lists inside page content modules. */
    public function blocks(array $blocks, string $locale): array
    {
        if (! $blocks) {
            return [];
        }

        $mediaIds = [];
        array_walk_recursive($blocks, function ($value, $key) use (&$mediaIds) {
            if (in_array($key, ['image_id', 'media_id'], true) && $value) {
                $mediaIds[] = (int) $value;
            }
        });
        $media = Media::whereIn('id', array_unique($mediaIds))->get()->keyBy('id');

        $resolve = function (array $data) use (&$resolve, $media) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $resolve($value);
                } elseif ($key === 'image_id') {
                    $data['image'] = $media->get((int) $value)?->toPublicArray();
                } elseif ($key === 'media_id') {
                    $data['file'] = ($m = $media->get((int) $value)) ? ['url' => $m->url(), 'mime' => $m->mime_type, 'size' => $m->humanSize()] : null;
                } elseif (in_array($key, ['body', 'answer'], true) && is_string($value)) {
                    $data[$key] = $this->renderer->render($value);
                }
            }

            return $data;
        };

        return collect($blocks)->map(function (array $block) use ($resolve, $locale) {
            $data = $resolve($block['data'] ?? []);

            if ($block['type'] === 'post_list') {
                $data['items'] = $this->cards($this->latest($locale, (int) ($data['limit'] ?? 6), $data['category_id'] ?? null), $locale);
                $data['more_url'] ??= isset($data['category_id']) ? Category::find($data['category_id'])?->translations()->where('locale', $locale)->first()?->url() : null;
            }
            if ($block['type'] === 'video') {
                $data['youtube_id'] = ContentRenderer::youtubeId((string) ($data['url'] ?? ''));
            }

            return ['type' => $block['type'], 'data' => $data];
        })->values()->all();
    }

    /** @return Collection<int, Content> */
    public function latest(string $locale, int $limit, ?int $categoryId = null): Collection
    {
        return Content::published()->ofType(ContentType::Post)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->when($categoryId, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('categories.id', Category::find($categoryId)?->descendantIds() ?? [$categoryId])))
            ->with(self::CARD_RELATIONS)
            ->orderByDesc('is_featured')->latest('published_at')
            ->limit(min($limit, 24))->get();
    }

    /** Structured-type fields with the attached file resolved. */
    private function fields(Content $content, ?Collection $fieldMedia = null): ?array
    {
        if (! $content->fields) {
            return null;
        }

        $fields = $content->fields;
        $file = ! empty($fields['file_id'])
            ? ($fieldMedia?->get((int) $fields['file_id']) ?? Media::find($fields['file_id']))
            : null;
        if ($file) {
            $fields['file'] = ['url' => $file->url(), 'mime' => $file->mime_type, 'size' => $file->humanSize(), 'name' => $file->filename];
        } elseif (! empty($fields['file_url'])) {
            $fields['file'] = ['url' => $fields['file_url'], 'mime' => null, 'size' => null, 'name' => basename(parse_url($fields['file_url'], PHP_URL_PATH) ?: '')];
        }

        return $fields;
    }

    /** hreflang alternates for a content item: only locales that really exist. */
    public function alternates(Content $content): array
    {
        return $content->translations()
            ->get(['id', 'content_id', 'locale', 'path'])
            ->sortBy(fn ($t) => array_search($t->locale, Locales::codes(), true))
            ->map(fn ($t) => ['locale' => $t->locale, 'url' => $t->url()])->values()->all();
    }
}
