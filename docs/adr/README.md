# Architecture Decision Records

## ADR-001 — Single Laravel application
**Decision:** Admin (Filament), public site (Inertia/React) and migration tooling live in one Laravel app.
**Why:** one deployment, one auth/permission model, domain services shared by admin, public site and importer.
**Consequence:** the public site needs no separate API; Inertia SSR is optional.

## ADR-002 — Translation tables
**Decision:** one `contents` row per logical item, `content_translations` per locale (same for categories/tags/SEO).
**Why:** mirrors Polylang translation groups; no per-language tables or databases.
**Consequence:** a translation exists only if the source has one; `(content_id, locale)` and `(locale, path)` are unique.

## ADR-003 — MySQL-only integration testing
**Decision:** tests run on MySQL 8 (`vju_cms_test`), never SQLite.
**Why:** FULLTEXT `ngram` search, JSON columns, collations and constraint behaviour must match production.
**Consequence:** CI starts a MySQL service; search tests use `DatabaseTruncation` because InnoDB FULLTEXT only sees committed rows.

## ADR-004 — WordPress DB + REST hybrid migration
**Decision:** two interchangeable sources behind `WordPressSource`: the restored database dump (authoritative:
drafts, users, JetEngine meta, Elementor JSON, menus, Polylang relationships) and the public REST API (fast start;
published content, Polylang `lang`/`translations`, Yoast `yoast_head_json`).
**Why:** discovery showed REST exposes most content but not users, menus or custom-field meta, and returns fewer
terms than it reports (41/60 categories, 23/25 tags).
**Consequence:** production migration uses `--source=db`; REST was used for rehearsal and verification.

## ADR-005 — Object storage strategy
**Decision:** all files go through Laravel filesystem disk `MEDIA_DISK`. Local `public` disk in development
(site-relative `/storage/...` URLs); S3-compatible object storage recommended in production (`MEDIA_DISK=s3`).
**Consequence:** body HTML stores the disk URL; switching disks needs a URL rewrite pass (or keep the public disk
behind a CDN). Originals are preserved; derivatives are regenerable.

## ADR-006 — Editor choice
**Decision:** Filament 4 RichEditor (TipTap, MIT) with a media-library tool, tables, code blocks, quotes, details;
structured page sections use Filament Builder "content modules" instead of free HTML.
**Why:** no licensing review needed (TinyMCE/CKEditor commercial features), output is simple semantic HTML.
**Consequence:** Elementor layouts are converted to semantic HTML during import; embeds are stored as links and
rendered as embeds at display time (`ContentRenderer`). All HTML is sanitized server-side (HTMLPurifier `cms` profile).

## ADR-007 — URL / redirect strategy
**Decision:** keep WordPress' URL model exactly: VI at `/`, EN `/en/`, JA `/ja/`; posts `/{slug}/`, pages by parent
path, categories without base (`/news-vn/dao-tao/`), tags `/tag/{slug}/`, CPT bases, trailing slash canonical.
`redirects` holds normalized old paths (decoded, lower-case, no slash/query) → 301/302/410.
**Why:** maximum `MIGRATE_200`, minimum SEO risk.
**Consequence:** slug/parent changes of published items create 301s automatically; chains are collapsed, loops and
home-page redirects are refused; live content wins over any redirect; old `/wp-content/uploads/*` URLs 301 to media.

## ADR-008 — Search engine strategy
**Decision:** MySQL FULLTEXT with the `ngram` parser on title/excerpt/body, plus tag/category name matches.
**Why:** works for Vietnamese and Japanese (no word delimiters) without new infrastructure at ~2,000 items.
**Consequence:** revisit Meilisearch if relevance ranking or typo tolerance becomes a requirement.
