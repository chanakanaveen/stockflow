# Phase 1 verification — 2026-11-01

## Outcome

Foundation and identity implemented. The user approved native isolated MySQL validation in place of Docker execution, with Compose supplied. No inventory posting or transfer logic is included.

## Implemented

- Laravel 13 backend, React/TypeScript frontend and lockfiles.
- Sanctum cookie sessions with CSRF, login/session regeneration/logout, active-account enforcement, JSON errors and request IDs.
- Four roles with granular Spatie permissions; store assignments; scoped GET /stores and GET /stores/{id}; admin-only GET /users.
- Opt-in fictional demo accounts that refuse production seeding.
- MySQL migrations for framework identity/session/cache/jobs, store access and permissions.
- PHP-FPM/MySQL 8.4/Vite/Nginx Compose configuration for localhost development.
- Login/workspace UI, API client, backend feature tests, frontend component tests and a real HTTP smoke script.

## Executed checks

| Check | Result |
| --- | --- |
| Development migration and corrected idempotent seed | Passed on isolated MySQL 9.1; five migrations applied |
| Backend PHPUnit | 16 tests passed, 60 assertions; real MySQL stockflow_test |
| Frontend TypeScript | Passed |
| ESLint | Passed |
| Vitest | 3 tests passed |
| Vite production build | Passed |
| Pint | Formatting applied; subsequent --test passed |
| PHPStan/Larastan level 5 | Passed, no errors in single-process mode |
| Composer validate --strict | Passed |
| Real HTTP auth smoke, API port 18000 | Passed |
| Real HTTP auth smoke through Vite proxy, port 5173 | Passed |
| Compose YAML parsing and build-path checks | Passed; four services |
| Docker image build / Compose boot / MySQL 8.4 execution | Not run: Docker unavailable; native alternative approved |
| Browser visual inspection | Not completed: browser automation timed out |

Commands used (run from backend unless otherwise noted):

```powershell
$env:XDEBUG_MODE='off'
php -d xdebug.log= artisan test --no-ansi
php -d xdebug.log= vendor/bin/pint --test --no-ansi
php -d xdebug.log= vendor/bin/phpstan analyse --debug --xdebug --no-progress --memory-limit=512M
composer validate --strict --no-ansi
```

Frontend: npm run typecheck; npm run lint; npm run test -- --run; npm run build.

Repository root: node --env-file=backend/.env scripts/smoke-auth.mjs. The script uses only explicitly enabled fictional local demo credentials, restricts its host to localhost, and does not print secrets. The dedicated backend port is 18000 because 8000 belongs to a separate application; that application was not modified.

Earlier failures were resolved: permission-cache invalidation after seeding, JSON responses without an Accept header, the production-seeder test's console prompt, the TypeScript 7 / typescript-eslint compatibility mismatch, Larastan inference of an arrow-function migration, and the smoke test's old session-cookie naming assumption. PHPStan's Windows parallel worker crashed; the documented single-process invocation completed successfully.

## Exact resolved versions

| Component | Version |
| --- | --- |
| PHP / Composer / Node / npm observed at setup | 8.3.14 / 2.9.7 / 24.12.0 / 11.6.2 |
| Native MySQL validation | 9.1.0 |
| Laravel / Sanctum / Spatie Permission | 13.39.0 / 4.3.3 / 8.3.2 |
| PHPUnit / Pint / Larastan | 12.5.42 / 1.32.1 / 3.13.2 |
| React / Vite / TypeScript | 19.3.1 / 8.3.5 / 6.0.3 |
| Vitest / ESLint | 5.0.5 / 9.39.5 |

## Data, security and rollback

Only the new isolated development and test databases were migrated. Their accounts have separate database grants. Tests refuse to boot with a different database/user or a DB_URL override. Credentials and local MySQL files are ignored. There are no ledger/balance mutations in this phase.

No deployment, existing WAMP database reset, Git history rewrite or next-phase implementation occurred. Revert application changes through a new reviewed commit; do not execute migration down or remove populated volumes without explicit approval and a backup. Native validation does not establish Docker build success or future inventory concurrency correctness.
