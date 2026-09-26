<?php

namespace App\Http\Controllers;

use App\Domain\Content\ContentStatus;
use App\Domain\Content\ContentType;
use App\Domain\Content\ViewCounter;
use App\Domain\SEO\RedirectResolver;
use App\Domain\SEO\SeoBuilder;
use App\Http\Presenters\ContentPresenter;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Comment;
use App\Models\Content;
use App\Models\ContentTranslation;
use App\Models\TagTranslation;
use App\Models\WpMigrationMap;
use App\Settings\SiteSettings;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public URL resolver: legacy query links -> locale -> home / content / category / tag /
 * type archive -> redirects table -> 404. Live content always beats a stale redirect.
 * Canonical URLs keep WordPress' trailing slash.
 */
class PublicController extends Controller
{
    public function __construct(
        private readonly ContentPresenter $presenter,
        private readonly SeoBuilder $seo,
    ) {}

    public function __invoke(Request $request): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            abort(405);
        }

        $rawPath = RedirectResolver::nfc(rawurldecode($request->getPathInfo()));

        if ($legacy = $this->legacyQueryRedirect($request)) {
            return $legacy;
        }

        [$locale, $path] = Locales::split($rawPath);
        app()->setLocale($locale);

        $page = 1;
        if (preg_match('#^(.*?)/?page/(\d+)$#', $path, $m)) {
            [$path, $page] = [trim($m[1], '/'), max(1, (int) $m[2])];
        }

        $response = match (true) {
            $path === '' && $page === 1 && ! $request->has('s') => $this->home($locale),
            $path === 'search' || $request->filled('s') => $this->search($request, $locale),
            default => $this->resolve($locale, $path, $page),
        };

        // Live content always wins; legacy redirects apply only to URLs that no longer resolve.
        if ($response === null && $redirect = RedirectResolver::find($rawPath)) {
            $redirect->increment('hits', 1, ['last_hit_at' => now()]);
            abort_if($redirect->status_code === 410, 410);

            return $this->redirectTo($redirect->new_url, $redirect->status_code);
        }

        // Canonical form ends with "/" (like WordPress): redirect once, only for URLs that resolve.
        if ($response !== null && $rawPath !== '/' && ! str_ends_with($rawPath, '/') && $request->isMethod('GET')) {
            $query = $request->getQueryString();

            return $this->redirectTo($request->getPathInfo().'/'.($query ? "?$query" : ''), 301);
        }

        return $response ?? $this->notFound($locale);
    }

    /** Preview for editors (drafts, scheduled...). Never indexed. */
    public function preview(Request $request, Content $content): Response
    {
        Gate::authorize('view', $content);
        $locale = $request->query('lang', $content->translations()->value('locale') ?? Locales::default());
        abort_unless(Locales::isSupported($locale) && $content->translation($locale), 404);
        app()->setLocale($locale);

        return $this->contentResponse($content, $locale, preview: true);
    }

    private function home(string $locale): Response
    {
        $homeId = app(SiteSettings::class)->home_page_id;
        $home = $homeId ? Content::published()->with('translations')->find($homeId) : null;
        $translation = $home?->translation($locale);

        return Inertia::render('Home', [
            'blocks' => $translation ? $this->presenter->blocks($translation->blocks ?? [], $locale) : [],
            'latest' => $translation ? [] : $this->presenter->cards($this->presenter->latest($locale, 9), $locale),
            'alternates' => collect(Locales::codes())->map(fn ($l) => ['locale' => $l, 'url' => Locales::path($l)])->all(),
            'seo' => $this->seo->forPage($locale, null, null, Locales::path($locale),
                collect(Locales::codes())->map(fn ($l) => ['locale' => $l, 'url' => Locales::path($l)])->all()),
        ])->toResponse(request());
    }

    private function resolve(string $locale, string $path, int $page): ?Response
    {
        if ($page === 1 && $translation = ContentTranslation::where('locale', $locale)->where('path', $path)->first()) {
            $content = Content::with(['translations', 'seo.ogImage'])->find($translation->content_id);
            if ($content?->isPublished()) {
                return $this->contentResponse($content, $locale);
            }
        }

        if ($category = CategoryTranslation::where('locale', $locale)->where('path', $path)->first()) {
            return $this->categoryArchive($category, $locale, $page);
        }

        if (str_starts_with($path, 'tag/') && $tag = TagTranslation::where('locale', $locale)->where('slug', substr($path, 4))->first()) {
            return $this->listing(
                $locale, $page, $tag->name, null, $tag->url(),
                Content::whereHas('tags', fn ($q) => $q->where('tags.id', $tag->tag_id)),
                [['label' => $tag->name, 'url' => $tag->url()]],
            );
        }

        if ($type = ContentType::fromArchiveBase($path)) {
            $url = Locales::path($locale, $path);

            return $this->listing($locale, $page, __('public.types.'.$type->value), null, $url,
                Content::ofType($type), [['label' => __('public.types.'.$type->value), 'url' => $url]], $type);
        }

        return $this->translationFallback($locale, $path);
    }

    /** Optional policy: missing translation -> default-locale content (only if VJU approves, see config). */
    private function translationFallback(string $locale, string $path): ?Response
    {
        if ($locale === Locales::default() || config('cms.translation_fallback') !== 'fallback') {
            return null;
        }

        $translation = ContentTranslation::where('locale', Locales::default())->where('path', $path)->first();

        return $translation ? $this->redirectTo($translation->url(), 302) : null;
    }

    private function contentResponse(Content $content, string $locale, bool $preview = false): Response
    {
        if (! $preview && ! $this->isBot()) {
            app(ViewCounter::class)->record($content->id);
        }

        $data = $this->presenter->full($content, $locale);
        $alternates = $this->presenter->alternates($content);
        $seo = $this->seo->forContent($content, $locale, $alternates);
        if ($preview || $content->status !== ContentStatus::Published) {
            $seo['robots'] = 'noindex,nofollow';
        }

        $related = $content->type->hasTaxonomy() && ($categoryIds = $content->categories->pluck('id')->all())
            ? Content::published()->ofType($content->type)->whereKeyNot($content->id)
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
                ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
                ->with(ContentPresenter::CARD_RELATIONS)->latest('published_at')->limit(4)->get()
            : collect();

        return Inertia::render($content->type === ContentType::Page ? 'Page' : 'Article', [
            'content' => $data,
            'breadcrumbs' => $this->breadcrumbs($content, $locale),
            'related' => $this->presenter->cards($related, $locale),
            'comments' => $content->is_commentable
                ? Comment::where('content_id', $content->id)->where('status', 'approved')->oldest()->limit(200)
                    ->get(['id', 'name', 'body', 'created_at'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'body' => $c->body, 'date' => $c->created_at->toIso8601String()])
                : [],
            'alternates' => $alternates,
            'preview' => $preview,
            'seo' => $seo,
        ])->toResponse(request());
    }

    private function categoryArchive(CategoryTranslation $translation, string $locale, int $page): Response
    {
        $category = Category::with(['children.translations', 'translations'])->find($translation->category_id);
        $crumbs = Category::with('translations')->findMany($category->ancestorIds())
            ->sortBy(fn ($c) => array_search($c->id, $category->ancestorIds(), true))
            ->map(fn ($c) => ($t = $c->translation($locale)) ? ['label' => $t->name, 'url' => $t->url()] : null)
            ->filter()->push(['label' => $translation->name, 'url' => $translation->url()])->values()->all();

        return $this->listing(
            $locale, $page, $translation->name, $translation->description, $translation->url(),
            Content::whereHas('categories', fn ($q) => $q->whereIn('categories.id', $category->descendantIds())),
            $crumbs, null,
            $category->children->map(fn ($c) => ($t = $c->translation($locale)) ? ['name' => $t->name, 'url' => $t->url()] : null)->filter()->values()->all(),
            $category->translations->map(fn ($t) => ['locale' => $t->locale, 'url' => $t->url()])->all(),
        );
    }

    private function listing(string $locale, int $page, string $title, ?string $description, string $url, $query, array $crumbs, ?ContentType $type = null, array $subcategories = [], array $alternates = []): Response
    {
        $paginator = $query->published()
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with(ContentPresenter::CARD_RELATIONS)
            ->latest('published_at')
            ->paginate(config('cms.per_page'), ['*'], 'page', $page);

        abort_if($page > 1 && $paginator->isEmpty(), 404);

        return Inertia::render('Listing', [
            'title' => $title,
            'description' => $description,
            'type' => $type?->value,
            'items' => $this->presenter->cards($paginator->items(), $locale),
            'pagination' => $this->pagination($paginator, $url),
            'subcategories' => $subcategories,
            'breadcrumbs' => $crumbs,
            'alternates' => $alternates,
            'seo' => $this->seo->forPage($locale, $title.($page > 1 ? " – {$page}" : ''), $description,
                $page > 1 ? rtrim($url, '/')."/page/{$page}/" : $url, $alternates),
        ])->toResponse(request());
    }

    private function search(Request $request, string $locale): Response
    {
        $q = trim(mb_substr((string) ($request->query('q') ?? $request->query('s') ?? ''), 0, 100));
        $page = max(1, (int) $request->query('page', 1));
        $items = [];
        $pagination = null;

        if (mb_strlen($q) >= 2) {
            $phrase = '"'.str_replace(['"', '\\'], ' ', $q).'"';
            $paginator = Content::published()
                ->where(fn ($query) => $query
                    ->whereHas('translations', fn ($t) => $t->where('locale', $locale)->whereFullText(['title', 'excerpt', 'body'], $phrase, ['mode' => 'boolean']))
                    ->orWhereHas('tags.translations', fn ($t) => $t->where('locale', $locale)->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('categories.translations', fn ($t) => $t->where('locale', $locale)->where('name', 'like', "%{$q}%")))
                ->whereHas('translations', fn ($t) => $t->where('locale', $locale))
                ->with(ContentPresenter::CARD_RELATIONS)
                ->latest('published_at')
                ->paginate(config('cms.per_page'), ['*'], 'page', $page)
                ->withQueryString();
            $items = $this->presenter->cards($paginator->items(), $locale);
            $pagination = $this->pagination($paginator, Locales::path($locale, 'search').'?q='.rawurlencode($q));
        }

        return Inertia::render('Search', [
            'q' => $q,
            'items' => $items,
            'pagination' => $pagination,
            'alternates' => collect(Locales::codes())->map(fn ($l) => ['locale' => $l, 'url' => Locales::path($l, 'search')])->all(),
            'seo' => $this->seo->forPage($locale, __('public.search').($q ? ": {$q}" : ''), null, Locales::path($locale, 'search'), [], index: false),
        ])->toResponse(request());
    }

    private function pagination(LengthAwarePaginator $p, string $baseUrl): array
    {
        $isQuery = str_contains($baseUrl, '?');
        $url = fn (int $n) => $n === 1 ? $baseUrl : ($isQuery ? "{$baseUrl}&page={$n}" : rtrim($baseUrl, '/')."/page/{$n}/");

        return [
            'current' => $p->currentPage(),
            'last' => $p->lastPage(),
            'total' => $p->total(),
            'pages' => collect(range(max(1, $p->currentPage() - 2), min($p->lastPage(), $p->currentPage() + 2)))->map(fn ($n) => ['n' => $n, 'url' => $url($n)])->all(),
            'prev' => $p->currentPage() > 1 ? $url($p->currentPage() - 1) : null,
            'next' => $p->hasMorePages() ? $url($p->currentPage() + 1) : null,
        ];
    }

    private function breadcrumbs(Content $content, string $locale): array
    {
        $crumbs = [];

        if ($content->type === ContentType::Page) {
            $parent = $content->parent;
            $guard = 0;
            while ($parent && $guard++ < 10) {
                $parent->loadMissing('translations');
                if ($t = $parent->translation($locale)) {
                    array_unshift($crumbs, ['label' => $t->title, 'url' => $t->url()]);
                }
                $parent = $parent->parent;
            }
        } elseif ($content->type->hasArchive()) {
            $crumbs[] = ['label' => __('public.types.'.$content->type->value), 'url' => Locales::path($locale, $content->type->urlBase())];
        } elseif ($category = $content->categories->first()) {
            if ($t = $category->translation($locale)) {
                $crumbs[] = ['label' => $t->name, 'url' => $t->url()];
            }
        }

        $crumbs[] = ['label' => $content->translation($locale)->title, 'url' => null];

        return $crumbs;
    }

    /** WordPress query links: ?p=123, ?page_id=123 (shortlinks shared on social media). */
    private function legacyQueryRedirect(Request $request): ?Response
    {
        $id = $request->query('p') ?? $request->query('page_id');
        if (! $id || ! ctype_digit((string) $id)) {
            return null;
        }

        $target = WpMigrationMap::where('source_id', (string) $id)->whereIn('source_type', ['post', 'page', 'download-documents', 'notification', 'tuition-fees', 'current-opportunitie'])
            ->where('status', 'success')->value('target_url');

        return $target ? $this->redirectTo($target, 301) : null;
    }

    private function notFound(string $locale): Response
    {
        return Inertia::render('Error', [
            'status' => 404,
            'latest' => $this->presenter->cards($this->presenter->latest($locale, 3), $locale),
            'alternates' => [],
            'seo' => $this->seo->forPage($locale, __('public.not_found'), null, request()->getPathInfo(), [], index: false),
        ])->toResponse(request())->setStatusCode(404);
    }

    /** Laravel's redirect() strips trailing slashes from relative paths; canonical URLs need them. */
    private function redirectTo(string $url, int $status): Response
    {
        return redirect()->away(absolute_url($url), $status);
    }

    private function isBot(): bool
    {
        return (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python|headless/i', (string) request()->userAgent());
    }
}
