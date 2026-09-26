# Architecture

Single Laravel application (ADR-001). WordPress is only a migration source.

```
HTTP ─ nginx ─ Laravel 12
                ├─ /admin            Filament 4 panel (Livewire)
                ├─ /auth/google/*    Google OIDC (state + nonce + ID-token validation)
                ├─ /health /robots.txt /sitemap*.xml /preview/{id} /comments/{id}
                └─ fallback          PublicController → Inertia/React pages
MySQL 8 (data) · Redis (cache, sessions, queues: default, media, migration, analytics, view counters)
Queue worker (derivatives, …) · Scheduler (scheduled publishing, view aggregation)
```

## Code map

| Path | Responsibility |
|---|---|
| `app/Domain/Content` | `ContentService` (the only write path: auth → workflow → sanitize → paths → SEO → taxonomy → revision → audit → cache), `ContentWorkflow` (status machine), `TaxonomyService`, `HtmlSanitizer`, `ContentRenderer` (render-time embeds), `ViewCounter` |
| `app/Domain/Media` | `MediaService` (MIME sniffing, whitelist, SHA-256 de-dup, non-overwriting paths), `GenerateMediaDerivatives` job (thumb/web/WebP/AVIF) |
| `app/Domain/SEO` | `SeoBuilder` (title/description/canonical/OG/Twitter/hreflang/JSON-LD), `RedirectResolver` (normalize, no chains/loops) |
| `app/Domain/Menu` | `MenuService` (nested tree sync with validation, cached public resolution) |
| `app/Domain/User` | `Permissions` matrix, `GoogleOidcProvider`, `UserProvisioner` |
| `app/Domain/Audit` | `Audit::record()`, `Auditable` trait (secrets scrubbed) |
| `app/Domain/Migration/WordPress` | Sources (REST, DB), transformers (HTML, Elementor JSON, shortcodes, menus), `WordPressImporter`, `MigrationMap`, `MigrationReports` |
| `app/Filament` | Admin resources, forms (`CmsRichEditor`, `MediaPicker`, `PageBlocks`), settings pages, dashboard widgets |
| `app/Http` | `PublicController` (URL resolver), `SeoController`, `HealthController`, `CommentController`, presenters, middleware |
| `app/Policies` | `ContentPolicy` (capability + ownership), permission policies for other resources |
| `resources/js` | Inertia pages, layouts, components, content-module blocks (`Components/Blocks`) |

## Security boundary

Every admin write: authentication (Filament + active account + role) → permission (Spatie) → policy (ownership)
→ validation → domain service (workflow re-checked server-side) → audit log. Hiding a button is never the control:
tests call the services and pages directly as each role (`tests/Feature/Auth/AuthorizationTest.php`).

## Data model

`contents` (type, status, author, dates, parent, featured media, template, `fields` JSON for structured types)
→ `content_translations` (locale, title, slug, **path**, excerpt, body, `blocks` JSON) · `seo_meta` (per locale)
· `content_category` / `content_tag` · `content_revisions` (full snapshots) · `content_drafts` (autosave per user)
· `content_views_daily`. `categories` + `category_translations` (hierarchical paths), `tags` + `tag_translations`,
`media`, `menus` + `menu_items`, `comments`, `redirects`, `audit_logs`, `wp_migration_map`, `wp_migration_runs`,
Spatie `roles/permissions`, `settings`.

Every imported row keeps `source_system` / `source_id`; the WordPress identity is never used as the public URL.

## URL resolution (`PublicController`)

```
request → legacy ?p= / ?page_id= links → locale prefix (/, /en/, /ja/) → home | search
        → content path → category path → tag/{slug} → type archive (/tuition-fees/ …)
        → redirects table (301/302/410) → 404
```

Live content always beats a stale redirect. Canonical URLs end with `/` like WordPress, so migrated URLs stay
identical (200) and only changed ones get a 301. A missing translation is a 404 unless
`CMS_TRANSLATION_FALLBACK=fallback` is approved.

## Content types

`post`, `page` (hierarchical, templates, content modules) and the JetEngine types found on the current site:
`document` (`/download-documents/`), `notification` (`/notification/`), `tuition_fee` (`/tuition-fees/`),
`opportunity` (`/current-opportunitie/`). Page content modules: hero, rich text, cards, stats, latest posts,
steps, FAQ, documents, CTA, logos, video (`app/Filament/Forms/PageBlocks.php` ↔ `resources/js/Components/Blocks`).

## Caching

`PublicCache` uses a version key: any content/menu/taxonomy/settings change bumps it, invalidating menus, site
settings and sitemaps at once on any cache driver.

## Queues

`default`, `media` (derivatives), `migration`, `analytics`. Jobs are idempotent (derivatives overwrite, scheduled
publishing claims rows atomically).
