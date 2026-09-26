# VJU CMS

Self-owned CMS and public website for VNU Vietnam Japan University, replacing the WordPress site
at https://vju.ac.vn while keeping its content, VI/EN/JA structure, URLs/SEO, media and editorial workflow.

| Layer | Stack |
|---|---|
| Backend | Laravel 12, PHP 8.3, MySQL 8, Redis (cache, queue, sessions) |
| Admin | Filament 4 (`/admin`), Google OIDC sign-in, Spatie Permission (Admin/Editor/Author/Contributor), Spatie Settings |
| Public site | Inertia 2 + React 19 + TypeScript + Tailwind CSS 4 (optional Inertia SSR) |
| Migration | `wp:inspect` / `wp:import` / `wp:validate` / `wp:report` (WordPress REST API or database dump) |
| Tests | PHPUnit on a dedicated MySQL database (`vju_cms_test`) — never SQLite |

## Quick start (Docker)

Requirements: Docker Desktop, Node 22+.

```bash
cp .env.example .env                  # then set APP_KEY below
docker compose build
docker compose up -d                  # app :8000, mysql :3307, redis, mailpit :8025, queue, scheduler
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed   # roles/permissions (+ demo data when APP_ENV=local)
docker compose exec app php artisan storage:link
npm ci && npm run build               # or `npm run dev` for HMR
```

- Public site: http://localhost:8000 — admin: http://localhost:8000/admin
- Local demo accounts (APP_ENV=local, `CMS_PASSWORD_LOGIN=true`): `admin@vju.test`, `editor@vju.test`,
  `author@vju.test`, `contributor@vju.test` — password `password`.
- First real admin: `php artisan cms:user someone@vju.ac.vn --role=Admin` then sign in with Google.

## Everyday commands

```bash
docker compose exec app php artisan test                 # PHPUnit (MySQL vju_cms_test)
docker compose exec app vendor/bin/pint                  # code style
docker compose exec app vendor/bin/phpstan analyse       # static analysis (baseline: phpstan-baseline.neon)
npm run typecheck && npm run lint && npm run build        # frontend checks
docker compose exec app php artisan cms:publish-scheduled # (scheduler runs it every minute)
```

## WordPress migration

```bash
php artisan wp:inspect                         # inventory from the public REST API (or --source=db)
php artisan wp:import --type=all --dry-run     # rehearse without writing anything
php artisan wp:import --type=all               # users, taxonomies, media, pages, posts, structured, comments, menus
php artisan wp:import --type=posts --since=last-run   # incremental
php artisan wp:validate                        # counts, every legacy URL = 200 or one 301, content checks
php artisan wp:report                          # migration-report.json/csv + url-inventory.csv
```

Options: `--source=rest|db`, `--locale=vi`, `--id=123`, `--since="2026-08-01 00:00:00"|last-run`, `--limit=100`, `--force`.
Details: [docs/runbooks/migration-cutover.md](docs/runbooks/migration-cutover.md).

## Documentation

- [docs/discovery/README.md](docs/discovery/README.md) — Phase 00 findings on the current site (plugins, counts, URL model)
- [docs/architecture.md](docs/architecture.md) — code map, domain services, request flow, data model
- [docs/adr/README.md](docs/adr/README.md) — architecture decision records ADR-001…008
- [docs/runbooks/deployment.md](docs/runbooks/deployment.md) — environments, deploy pipeline, queue/scheduler, SSR
- [docs/runbooks/migration-cutover.md](docs/runbooks/migration-cutover.md) — rehearsals, content freeze, DNS cutover, rollback
- [docs/runbooks/operations.md](docs/runbooks/operations.md) — monitoring, logs, backups, restore drill, maintenance
- [docs/admin-guide.md](docs/admin-guide.md) — hướng dẫn biên tập viên (Vietnamese editor guide)
- [docs/qa-checklist.md](docs/qa-checklist.md) — Phase 08 QA/UAT checklist and what is automated
