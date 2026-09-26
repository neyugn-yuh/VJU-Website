# Phase 00 — Discovery findings (current site https://vju.ac.vn)

Collected 2026-09-26 from public sources only (HTML, REST API). Items marked **needs VJU** require WordPress
admin/database access and sign-off before the production migration (plan gate D1–D6).

## Platform

| Item | Finding | Impact |
|---|---|---|
| WordPress | 7.1.2 (generator meta) | — |
| Theme | `vju` (custom) | layout rebuilt in React |
| Page builder | Elementor 3.21 + **Elementor Pro**, Anywhere Elementor Pro, JetElements/JetTabs/JetBlocks/JetMenu, BdThemes Element Pack (`bdt-*` widgets) | content converted to semantic HTML (`HtmlTransformer`, `ElementorConverter`); `bdt-*`, `jet-*` widgets reported as warnings |
| Multilingual | **Polylang Pro** (REST `pll/v1`, `lang` + `translations` fields), Connect Polylang Elementor | translation groups → one content with VI/EN/JA translations |
| SEO | **Yoast SEO Premium** 20.7 / Yoast 22.7 (`yoast_head_json`) | `SeoMapper` (REST head + DB meta keys) |
| Custom post types | JetEngine: `download-documents` (18), `notification` (7), `tuition-fees` (35), `current-opportunitie` (14); taxonomies `location`, `work-type`, `categories-type` on opportunities | CMS types document / notification / tuition_fee / opportunity with legacy URL bases; meta keys **needs VJU** (not exposed by REST) → `config/cms.php wordpress.field_map` |
| Forms | WPForms Lite, Elementor forms, WP Support Ticket | not migrated (no CMS equivalent); removed with warnings — **needs VJU**: replacement (Google Forms / new module) |
| Analytics | MonsterInsights, Site Kit, WP Statistics | GA4/GTM IDs in Settings → Analytics |
| Performance/security | WP Rocket, WP-Optimize Premium, WebP Converter, Wordfence | replaced by nginx caching, derivatives, Laravel security |
| Other REST namespaces | `jet-engine/v2`, `jet-smart-filters-api/v1`, `mcp`, `wp-abilities/v1` | — |

Full plugin list (41 plugins per spec) and their stored data: **needs VJU** (`wp:inspect --source=db` lists `active_plugins`).

## Content counts (REST `X-WP-Total`, published)

| Type | All | VI | EN | JA | Spec |
|---|---|---|---|---|---|
| Posts | 1,866 | 1,153 | 647 | 66 | 1,153 / 647 / 66 ✓ |
| Pages | 374 | 174 | 155 | 45 | 173 / 155 / 45 (VI +1) |
| Media | 5,289 | 3,396 | 1,530 | 363 | — |
| Categories | 60 (REST lists only 41) | 20 | 30 | 10 | — |
| Tags | 25 (REST lists only 23) | 18 | 7 | 0 | — |
| Comments (approved) | 20 | | | | — |

The REST API returns fewer terms than it reports (empty/hidden terms); the DB source is authoritative (ADR-004).

## URL model (preserved 1:1, ADR-007)

| Kind | Example |
|---|---|
| VI home / EN / JA | `/`, `/en/home/` (EN front page), `/ja/日越大学/` |
| Post | `/{slug}/`, `/en/{slug}/` |
| Page (hierarchical) | `/dam-bao-chat-luong/thuc-hien-cong-khai-doi-voi-csgd-dai-hoc/co-so-vat-chat/` |
| Category (no base, hierarchical) | `/news-vn/dao-tao/`, `/en/news/academics/`, `/events-vn/` |
| Tag | `/tag/{slug}/` |
| CPT | `/tuition-fees/{slug}/`, `/en/current-opportunitie/{slug}/` |
| Uploads | `/wp-content/uploads/YYYY/MM/file` → 301 to the new media URL |

Japanese slugs are percent-encoded in WordPress; the CMS stores them decoded and encodes on output.

## Menus

Header: two horizontal Elementor nav widgets (Tuyển sinh / Đào tạo / … ~120 links); footer/mega-menu columns:
vertical nav widgets. JetMenu mega-menu content is not reproducible from REST — **needs VJU** DB rehearsal.

## Rehearsal results (REST source, 2026-09-26, clean database)

| Check | Result |
|---|---|
| Posts VI/EN/JA | 1,153 / 647 / 66 = source ✓ |
| Pages VI/EN/JA | 174 / 155 / 45 = source ✓ |
| Structured (documents, notifications, tuition fees, opportunities) | 74 = source ✓ |
| Comments | 20 ✓ |
| Menus | header + footer × VI/EN/JA (6) |
| Media | ~3,400 files pulled (attachments + files referenced in content), ~5.4 GB with derivatives |
| Legacy URLs | 2,333 identical (200) + 45 changed (single 301), **0 failures** |
| Redirects | ~3,500 (uploads → media, changed slugs, harvested WordPress redirects) |
| Duration | ~50 min (dominated by media downloads); re-runs with `--force` ~5 min |

Findings that changed the importer: decomposed-Unicode (NFD) Vietnamese slugs, accent-insensitive collation,
inconsistent Polylang groups (two EN pages → one VI page), images on the former domain `vju.vnu.edu.vn`,
EN menus nested deeper than 4 levels, and **existing Yoast Premium redirects** (e.g. `/academics/post-graduate/` →
`/en/academics/post-graduate/`) that must be migrated (`--type=redirects` / `wp:harvest-redirects`).

Remaining, not fixable by migration (report to VJU): ~190 links in old posts that are already broken on the live
site (`/upload_images/…`, old paths), 14 images already missing, 21 posts with an empty body in WordPress too,
author archives `/author/{user}/` (live on WordPress, no CMS equivalent — decide 301 to home or 410).

## Known content issues found during rehearsal

- Old posts (2019) reference `/upload_images/images/...` — these already return 404 on the live site; reported as
  `missing_media` / `legacy_image`, not fixable by migration.
- Some pages embed SVG images (not in the upload whitelist for XSS reasons) — reported, decide per case.
- Elementor placeholders (`/wp-content/plugins/elementor/assets/images/placeholder.png`) are dropped.
- The WP front pages (`/trang-chu/` VI, `/en/home/` EN) are Elementor landing pages; the new homepage is built from
  content modules (Settings → Site → Homepage content) instead of converting their layout.

## Deliverables status

| | Status |
|---|---|
| D1 URL inventory | generated by `php artisan wp:report` → `url-inventory.csv` (action MIGRATE_200 / MIGRATE_301 / REVIEW) |
| D2 Content inventory | `wp:inspect` (REST now; DB with custom fields, shortcodes, widgets once the dump is provided) |
| D3 Plugin audit | table above; per-plugin data audit **needs VJU** |
| D4 Multilingual mapping | Polylang groups → translation records (verified in tests and rehearsal) |
| D5 Component inventory | header, top bar, nav, language switcher, home sections, article, archive, search, footer, admission pages → React components + content modules |
| D6 Content model | generic post/page, structured types (4 CPTs), landing pages (content modules), external links (menus) |
