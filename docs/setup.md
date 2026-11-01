# Phase 1 local setup

Phase 1 includes cookie login/logout, active-account checks, seeded roles, store assignments, scoped store reads, an admin-only user list, and a React login/workspace screen. Inventory posting and master editing are not implemented yet.

## Native PHP / Node / MySQL

Use PHP 8.3+ with PDO MySQL, BCMath, mbstring, XML, curl and zip; Composer 2; Node 24; and an isolated MySQL instance. Install dependencies from lockfiles:

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Configure your database host, port, database, username and password in the ignored backend/.env. Use a dedicated development database named stockflow and a restricted database user with privileges only on that database. APP_URL should be http://127.0.0.1:5173 for Vite development.

For fictional demo accounts, set DEMO_ENABLED=true and choose a unique DEMO_PASSWORD of at least 12 characters in backend/.env. No default password is committed. Run:

```powershell
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=18000
```

In a second terminal:

```powershell
cd frontend
npm ci
npm run dev
```

Open http://127.0.0.1:5173. Use the same hostname consistently so session cookies work. Vite proxies /api and /sanctum to port 18000, keeping browser requests same-origin. Do not expose these development servers publicly.

| Account | Role | Store access |
| --- | --- | --- |
| admin@stockflow.test | System Admin | All stores |
| manager@stockflow.test | Inventory Manager | All three demo stores |
| keeper@stockflow.test | Store Keeper | Main Warehouse only |
| auditor@stockflow.test | Auditor | All three demo stores |

The password is your locally configured DEMO_PASSWORD. Re-running demo seeding does not reset existing passwords. Demo seeding refuses production even if explicitly enabled. Without opt-in, the default seeder creates roles and permissions only.

## Isolated tests

Create a second database named stockflow_test and a user named stockflow_test with privileges only on that database. Copy backend/.env.testing.example to backend/.env.testing, configure its connection and a separate APP_KEY, and run the following inside backend:

```powershell
php artisan test
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```

Feature tests may reset the test schema. TestCase refuses to boot against any database/user other than stockflow_test on the mysql connection. Never grant this user privileges on development or production databases. An SQLite run is not the acceptance check.

Inside frontend:

```powershell
npm run typecheck
npm run lint
npm run test -- --run
npm run build
```

## Docker Compose (supplied; not executed on the current host)

Copy root .env.example to .env. Set APP_KEY to a Laravel base64 key and set distinct DB_PASSWORD, MYSQL_ROOT_PASSWORD and MYSQL_TEST_PASSWORD values; the test password must be alphanumeric. Generate secrets locally rather than using fixed samples. Set DEMO_ENABLED/DEMO_PASSWORD if desired.

```powershell
docker compose config --quiet
docker compose up --build -d
docker compose exec --user www-data backend php artisan migrate --seed
docker compose exec --user www-data backend test-backend
```

Open http://localhost:8080 (or APP_PORT). Compose runs MySQL 8.4, PHP-FPM, a Vite development server and Nginx. Only the web port is published, bound to localhost. Database volumes persist across restarts. Initialization scripts run only for a new database volume. This is a local-development configuration, not a production deployment.

Do not remove database volumes or roll back populated migrations casually. No destructive reset is necessary to restart the application.

## Current workspace instance

The Phase 1 validation instance uses a new MySQL 9.1 process with its data under ignored .local/mysql-data, bound to 127.0.0.1:13316. It is separate from WAMP's existing data. Generated development/test credentials and the local demo password are in ignored backend/.env and backend/.env.testing; administrative connection details are in ignored .local/mysql-root.cnf.

This native validation alternative was explicitly approved by the user because Docker was unavailable. Passing native tests does not establish that the Compose image builds or MySQL 8.4 execution succeeds. Record those checks separately when Docker is available.

## HTTP smoke test

With the local API running on port 18000 and demo accounts seeded, run from the repository root:

```powershell
node --env-file=backend/.env scripts/smoke-auth.mjs
```

To check the frontend proxy too, set SMOKE_BASE_URL=http://127.0.0.1:5173 for that command. The script signs out both demo sessions after its checks. See [verification results](phase1-verification.md). On Windows with the supplied WAMP/Xdebug runtime, use the documented single-process PHPStan invocation if parallel workers fail.
