# WordPress migration & cutover runbook (Phases 07 and 09)

## Sources

| Source | Use | Setup |
|---|---|---|
| `--source=db` (authoritative) | staging rehearsals and the final migration | restore the production dump into the `wordpress` connection (`WP_DB_*`, `WP_DB_PREFIX`); copy `wp-content/uploads` and set `WP_UPLOADS_PATH` to read files locally instead of downloading |
| `--source=rest` | early rehearsals, verification | only `WP_BASE_URL`; published content only; no users/menus meta (menus are extracted from the rendered header/footer navs) |

## Before the first run (Phase 00 decisions to confirm)

1. `php artisan wp:inspect --source=db` → `storage/app/private/migration/inspect-db.json`: plugins, counts by
   status/language, JetEngine meta keys per CPT, shortcodes and Elementor widgets in use.
2. Fill `config/cms.php → wordpress.field_map` (JetEngine meta key → CMS field) and `wordpress.menu_locations`
   (theme location → header/topbar/footer). Unmapped meta is still kept under `fields.wp_meta`.
3. Add transformers to `ShortcodeRegistry` for any shortcode in the inventory that must render content.

## Order and idempotency

`--type=all` runs: users → taxonomies → media → pages → posts → structured → comments → menus.
Each record is keyed by its WordPress identity in `wp_migration_map`; unchanged checksums are skipped, changed
records update in place, nothing is duplicated. `--force` re-transforms everything (e.g. after a transformer fix).
Every run is logged in `wp_migration_runs`; recoverable problems (missing image, unknown shortcode, unsupported
file type) are warnings on the record; source failures abort the batch.

## Rehearsal (at least twice on staging)

```bash
php artisan migrate:fresh --seeder=RolesAndPermissionsSeeder --force
time php artisan wp:import --type=all --source=db
php artisan wp:validate --source=db     # must pass: counts, 404s, chains, loops, redirects to 404
php artisan wp:report                   # hand migration-report.csv + url-inventory.csv to VJU reviewers
```

Record: duration per type, media volume and transfer time, DB size, queue throughput (derivatives), warnings.
Rehearsal 2 adds an incremental run and the full cutover simulation below.

## Cutover

| When | Step |
|---|---|
| T-7d | Final UAT sign-off on staging; URL exception list approved |
| T-1d | Content freeze announced; backups verified; DNS TTL lowered to 300s |
| T-0 | WordPress read-only (freeze); fresh dump + uploads sync |
| | `php artisan wp:import --type=all --source=db --since=last-run` |
| | `php artisan wp:validate --source=db` + `php artisan wp:report` |
| | URL smoke test: `node tools/check-urls.mjs --inventory=storage/app/private/migration/url-inventory.csv --base=https://<new-site>` (200, or one 301 → 200) |
| | SEO smoke: `/sitemap.xml`, `/robots.txt`, canonical + hreflang on sample pages; **Discourage indexing OFF** |
| | switch DNS / reverse proxy to the new site |
| | production smoke tests, Google login for each role, create/publish test post then delete |
| T+1h | watch 5xx, 404 spikes (`redirects.hits`, logs), queue, response time |
| T+1d | review Search Console coverage; add redirects for new 404s |
| T+7d | decide archiving WordPress (keep read-only 30–90 days) |

## Rollback

Triggers: widespread 5xx, broken login, data corruption, severe URL failure, media failure, security issue.
Action: point DNS / proxy back to WordPress (still read-only, unchanged). Do not reverse-migrate data.
Content edited in the new CMS after cutover must be re-entered manually in WordPress if rollback happens.

## Discovery facts that shaped the importer

See [../discovery/README.md](../discovery/README.md): Polylang Pro, Yoast SEO Premium, Elementor Pro (+ Jet*, BdThemes
widgets), JetEngine CPTs, category base removed, trailing slashes, `/upload_images/` legacy images already 404 on
the live site.
