# Operations runbook (Phase 10)

## Monitoring

| Signal | Source | Alert |
|---|---|---|
| Availability | `GET /health` (app, database, cache, storage, queue) every minute | non-200 twice |
| HTTP 5xx / 4xx spikes | nginx access log / APM | 5xx > 1% for 5 min; 404 > 3× baseline |
| Queue failures & latency | `failed_jobs`, `php artisan queue:monitor redis:default,redis:media --max=100` | any failed job; backlog > 100 |
| Scheduler | `cms:publish-scheduled` output; scheduled items older than 5 min still `scheduled` | yes |
| DB | connections, slow queries, disk | > 80% |
| Storage | object storage size / disk usage | > 80% |
| Login failures | `storage/logs/security-*.log` (`login_failed`), `audit_logs` | burst from one IP |

## Logs

`laravel-*.log` (application), `security-*.log` (logins, OIDC failures), `migration-*.log` (importer),
`queue-*.log`, plus the `audit_logs` table (who did what, before/after; secrets and tokens are scrubbed).
Never log passwords, OIDC tokens, session secrets or API keys.

## Backups

- Database: daily `mysqldump --single-transaction --routines --triggers vju_cms | gzip`, retention agreed with VJU
  (suggested 30 daily + 12 monthly), stored off-site.
- Media: object storage versioning + lifecycle rule; independent copy weekly if required.
- Config: `.env` in the secret manager (not in backups).

## Restore drill (quarterly)

```
backup → restore into an isolated environment → php artisan migrate --force → GET /health
→ open 10 random articles in each locale, one page with modules, the media library, the audit log
```

A backup that has never been restored is not verified.

## Maintenance (monthly)

- `composer outdated` / `composer audit`, `npm outdated` / `npm audit`; patch PHP, OS, container base images
- review admin users and roles (Admin → Users), suspended accounts, Google OAuth client credentials
- review `redirects` with high hits and 404 logs; add redirects where needed
- check `failed_jobs`, prune old audit logs if the retention policy allows

## Incident procedure

1. Acknowledge, open an incident note (time, symptoms).
2. Check `/health`, error logs, recent deploys, queue.
3. Mitigate: roll back the release (deployment.md) or `php artisan down` with the maintenance page.
4. Fix, verify, `php artisan up`.
5. Post-incident review within 5 working days.

## Retained migration tooling

The WordPress importer stays in the repository for emergency re-import, historical reconciliation, or migrating a
WordPress archive: `wp:import` is idempotent and never duplicates records.
