# Deployment runbook (Phase 09)

## Environments

| | local | staging | production |
|---|---|---|---|
| Host | Docker Compose | production-like VM/containers | production |
| DB | MySQL 8 container | MySQL 8 (restored prod-size data) | MySQL 8 (managed, backups on) |
| Media | `public` disk | object storage (staging bucket) | object storage (versioned bucket) |
| Login | password + Google | Google only | Google only |
| Indexing | — | **SEO → Discourage search engines ON** | off |

## Production configuration checklist (`.env`)

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://vju.ac.vn`, `APP_KEY` (generate once, keep secret)
- `CMS_PASSWORD_LOGIN=false` (enforced: the login form is not rendered and `authenticate()` returns 403)
- `GOOGLE_CLIENT_ID/SECRET`, `GOOGLE_REDIRECT_URI=https://vju.ac.vn/auth/google/callback`,
  `GOOGLE_ALLOWED_DOMAINS=vju.ac.vn`, decide `GOOGLE_AUTO_PROVISION` (accounts are created without role either way)
- `SESSION_SECURE_COOKIE=true`, `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`
- `MEDIA_DISK=s3` + `AWS_*` (or keep `public` behind a CDN), `TRUSTED_PROXIES` if behind a load balancer
- `LOG_STACK=daily`; security/migration/queue channels write to `storage/logs/{security,migration,queue}-*.log`

## Processes

- **web**: php-fpm (`docker/production.Dockerfile`) behind nginx (`docker/nginx.conf`). nginx must not strip
  trailing slashes and must pass `/wp-content/*` misses to Laravel (legacy upload redirects).
- **queue**: `php artisan queue:work redis --queue=default,media,migration,analytics --tries=3 --backoff=10 --max-time=3600`
  under Supervisor/systemd (restart always).
- **scheduler**: `php artisan schedule:work` (or cron `* * * * * php artisan schedule:run`). Runs
  `cms:publish-scheduled` (every minute), `cms:flush-views` (every 5 min), `queue:prune-failed` (daily).
- **SSR (optional, recommended)**: `php artisan inertia:start-ssr` as a supervised process; set
  `INERTIA_SSR_ENABLED=true`. Without it the site still works (SEO tags are server-rendered in Blade anyway).

Supervisor example:

```ini
[program:vju-queue]
command=php /var/www/html/artisan queue:work redis --queue=default,media,migration,analytics --tries=3 --backoff=10 --max-time=3600
numprocs=2
autorestart=true
stopwaitsecs=3600
user=www-data

[program:vju-scheduler]
command=php /var/www/html/artisan schedule:work
autorestart=true
user=www-data
```

## Deploy pipeline

```
merge to main → CI green (pint, phpstan, phpunit on MySQL, typecheck, lint, build)
→ build image (docker/production.Dockerfile)
→ backup: mysqldump --single-transaction + object-storage versioning check
→ deploy new release
→ php artisan down --render=errors::503 (only if migrations are not backward compatible)
→ php artisan migrate --force
→ php artisan db:seed --class=RolesAndPermissionsSeeder --force   # idempotent, keeps permission matrix in sync
→ php artisan optimize && php artisan filament:optimize
→ php artisan queue:restart
→ php artisan up
→ curl -fsS https://vju.ac.vn/health  (expects {"status":"ok"})
→ smoke: /, /en/, /ja/, one article, /sitemap.xml, /admin/login
```

## First production bootstrap

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan storage:link              # only with the public disk
php artisan cms:user it-admin@vju.ac.vn --name="IT Admin" --role=Admin
```

Then set the homepage (Settings → Site → Homepage content), logo, contact, social, SEO defaults and analytics IDs.

## Rollback of a release

Re-deploy the previous image. Run `php artisan migrate:rollback --step=N` only if the release's migrations are
reversible and no new data depends on them; otherwise restore the pre-deploy backup (see operations.md).
