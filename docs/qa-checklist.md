# QA & acceptance checklist (Phase 08)

## Automated (PHPUnit on MySQL — `php artisan test`)

| Area | Tests |
|---|---|
| OIDC & accounts | `Feature/Auth/GoogleOidcTest` — user creation without role, e-mail linking, suspended rejected, foreign domain rejected, identity conflict, state/nonce failure, login audited |
| RBAC | `Feature/Auth/AuthorizationTest` — Admin all, Editor publishes, Author cannot edit others / publishes own, Contributor cannot publish and loses edit after publish, author reassignment blocked, 403s, own-content listing, force delete Admin-only |
| CMS core | `Feature/Content/ContentServiceTest` — VI/EN/JA slugs, de-dup, reserved paths, hierarchy + automatic 301s, cycle guard, XSS sanitation, revisions + restore, translation removal, scheduling idempotency, trash/restore, publish audit |
| Workflow / URLs | `Unit/ContentWorkflowTest` — status edges, redirect normalization, slugs |
| Media | `Feature/Media/MediaServiceTest` — metadata, derivatives, MIME sniffing (PHP disguised as JPG), SVG sanitized (scripts, handlers, external refs, entities), de-dup, no overwrite, PDF |
| Menus | `Feature/Admin/MenuServiceTest` — tree, unpublished hidden, validation |
| Admin UI | `Feature/Admin/AdminPagesTest` — every screen renders, create/edit through the form, contributor blocked, autosave separate from record |
| Public site | `Feature/Public/PublicSiteTest` — home ×3 locales, server-side SEO (title, description, canonical, hreflang, OG, JSON-LD), missing translation 404, drafts hidden, preview permission, trailing slash 301, redirects/410, live content beats redirect, category/tag/pagination, structured archives, health, security headers |
| Search | `Feature/Public/SearchTest` — FULLTEXT ngram VI and JA, per locale |
| SEO & comments | `Feature/Public/SeoAndCommentsTest` — sitemap index/XML/hreflang/noindex exclusion, robots + staging switch, comment moderation, sanitation, honeypot, rate limit |
| Migration | `Unit/Migration/TransformTest` (Elementor, media/link rewrite, iframes, shortcodes, autop, Elementor JSON, Yoast, URL variants, menu extraction); `Feature/Migration/WordPressImporterTest` (full import, idempotent re-run, in-place update, dry-run, filters, recoverable failures, validation & reports); `Feature/Migration/DatabaseSourceTest` (Polylang, Yoast meta, Elementor data, attachments, users, menus, comments, inventory) |

Static: `pint --test`, `phpstan` (level 5, baseline for Eloquent magic), `tsc --noEmit`, `eslint`, `vite build` (client + SSR).

## Migration reconciliation (wp:validate)

- [ ] WP VI/EN/JA posts = CMS VI/EN/JA posts; same for pages and structured types
- [ ] categories, tags, media counts from the DB inventory
- [ ] every legacy URL: 200, or exactly one 301 to a 200 (`urls.failed = 0`)
- [ ] no redirect loops/chains/redirects to 404
- [ ] review `broken_image`, `legacy_image`, `broken_link`, `empty_body`, `duplicate_slug`, orphaned media
- [ ] review warnings in `migration-report.csv` (`shortcode_unknown`, `elementor_widget_unknown`, `missing_media`)

## Manual / browser (E2E journeys)

1. Google login (each role) 2. Create post 3. Upload image 4. Save draft / autosave 5. Submit for review
6. Editor publishes 7. Schedule post (published within a minute) 8. Restore revision 9. Change language
10. Search (VI, EN, JA) 11. Open migrated article, check images/links/embeds

## Accessibility

Automated audit (axe / Lighthouse) on home, article, listing, admissions page, search; then keyboard-only
navigation (skip link, dropdowns with Escape, mobile drawer focus trap), visible focus, headings order, form labels,
contrast, reduced motion (hero autoplay stops).

## Responsive

320 / 375 / 768 / 1024 / 1440 px: header, mega navigation, tables (horizontal scroll), hero, cards, footer.

## Performance

Measure TTFB, LCP, CLS, JS/CSS weight, image weight, DB queries (Laravel Debugbar/Telescope on staging) on home,
article, listing, admissions, research, search. Targets: LCP < 2.5 s, CLS < 0.1 on 4G mid-range mobile.

## Security

Authorization bypass (direct Livewire/HTTP calls as lower roles), XSS in body/comments/menu labels, upload abuse,
CSRF, rate limits (comments, OIDC routes, Filament login), session cookies (`Secure`, `HttpOnly`, `SameSite`),
OIDC callback validation, `/admin` exposure, secrets in logs/audit payloads.

## UAT package for VJU

Editor guide (`docs/admin-guide.md`), test accounts per role, the scenarios above, migration report, URL exception
list (`url-inventory.csv` rows with REVIEW), known limitations (forms, JetMenu mega-menu content, legacy
`/upload_images/` files already 404 on the old server).
