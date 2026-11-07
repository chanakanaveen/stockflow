# Deployment and recovery runbook

This is a preparation guide, not evidence of a production deployment. No hosting target or operational owner has been selected. The repository Compose file runs development services and must not be exposed as a production stack. Full Compose boot and backup restoration remain unverified.

## Prepare a release

Use an immutable reviewed Git commit and its lockfiles. Run [verification](testing.md), reassess dependency advisories, and validate the exact runtime on staging. CI has exercised PHP 8.3, Node 24 and MySQL 8.4. Use a supported patched runtime in deployment; local patch versions are historical evidence, not a security baseline.

Build frontend assets with `npm ci` and `npm run build` inside frontend. Install backend dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`. Serve `frontend/dist` as static files with SPA fallback to index.html; route `/api`, `/sanctum` and `/up` to Laravel's `backend/public/index.php` through PHP-FPM. Keep browser/API on the same HTTPS origin. Never serve the repository root or `.env`, and never use Vite or `artisan serve` as the production web server.

Provide configuration through the hosting secret mechanism, not Git:

- `APP_ENV=production`, `APP_DEBUG=false`, canonical HTTPS `APP_URL`.
- A unique generated `APP_KEY`, retained across upgrades and backed up securely. Do not regenerate an existing environment's key casually.
- `SESSION_SECURE_COOKIE=true`, encrypted database sessions and the appropriate same-site/domain configuration. The development example explicitly sets secure cookies false, so override it for HTTPS.
- `DEMO_ENABLED=false`, empty demo password; never copy demonstration accounts/data into production.
- Dedicated MySQL credentials restricted to this application's schema; separate migration/admin access from routine runtime access. Restrict database network access.
- Only `storage` and `bootstrap/cache` need application write access. Configure logs, retention and request-ID correlation without recording credentials or cookies.

Default production seeding creates roles/permissions only. Initial administrator provisioning needs an operator-owned, reviewed process; do not enable demo seeding to bootstrap a production admin.

## Release sequence (staging first)

1. Record the current and proposed commit IDs. Take a consistent database backup and preserve the current configuration and APP_KEY securely. Prove the backup can be restored into an isolated database before relying on it.
2. Quiesce writes with maintenance mode (`php artisan down`) and drain in-flight requests at the web tier. Keep the previous application artifact available.
3. Review pending migrations. Run `php artisan migrate --force` with the approved migration identity, then `php artisan db:seed --force` with demo disabled to establish roles/permissions when needed. Never run `migrate:fresh` on live data.
4. Run `php artisan config:cache`, `php artisan route:cache` and `php artisan view:cache` with production configuration. Reload PHP-FPM using the hosting platform's procedure.
5. Run `php artisan inventory:reconcile` using the intended production connection. Any nonzero mismatch blocks reopening writes; preserve evidence and investigate.
6. Run `php artisan up`, then verify HTTPS, `/up`, login/logout/CSRF, role/store restrictions, report/export permissions and a separately approved synthetic stock flow. `/up` alone is not business-integrity verification.
7. Record artifact ID, migration output, checks and operator. Monitor errors, request latency, lock contention, failed posting and backup jobs.

Commands here are a proposed operational sequence and have not been executed against production. The environment-specific proxy configuration, TLS, backups, monitoring and least-privilege grants must be verified before release.

## Recovery and reconciliation

Run `php artisan inventory:reconcile` read-only. It checks balance/ledger agreement, missing balances and document/transfer movement integrity. Preserve its output with the affected commit and request IDs. If discrepancies appear, stop affected posting, preserve logs and a consistent database snapshot, and investigate before repair. Do not silently rewrite balances or the historical ledger.

Back up with an operator-managed MySQL tool using a protected option file or secret store, not passwords on command lines. Encrypt backups off-host and define retention, recovery-point and recovery-time objectives. A restore drill must restore into a separate database, use the corresponding APP_KEY/configuration, verify counts and reconciliation, and test login and posting under isolation. No successful restore drill is claimed by this repository.

For code-only rollback, use the previous artifact only after verifying compatibility with the current schema. Do not blindly roll back populated migrations. Restoring a backup can lose accepted writes and requires explicit operator authorization and reconciliation. Prefer a reviewed forward fix when schema/data compatibility is uncertain.
