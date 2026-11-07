# Phase 6 verification — 2026-11-07

Scope: portfolio and release documentation, actual screenshots and additional capture steps in the existing browser tests. No application/schema/dependency changes. No deployment, platform portfolio posting or recorded video is claimed.

## Actual checks

| Check | Result |
| --- | --- |
| Clean local clone of 5d9e476 into ignored `.local/phase6-checkout` | Passed; fresh dependency directories, no copied vendor/node_modules |
| `composer install --no-interaction --prefer-dist --no-progress` in clone/backend | Passed; 115 locked packages, package discovery completed. Git emitted a local sandbox-owner warning; no global safe-directory setting changed |
| `npm ci` in clone/frontend | Passed; 274 packages installed, 281 audited |
| `.env.example` configuration and `php artisan key:generate` | Passed with generated local-only demo password and isolated stockflow_test credentials; secrets not printed |
| `php artisan migrate --seed` in clone/backend | Passed on the existing isolated test schema (nothing pending); ordinary demo accounts seeded. Clean-schema migration is separately exercised by browser fixture preparation |
| Clone `artisan serve` on 18000 and `npm run dev` on 5173 | Started; real HTTP authentication through the frontend proxy passed |
| `SMOKE_BASE_URL=http://127.0.0.1:5173 node --env-file=backend/.env scripts/smoke-auth.mjs` in clone | Passed: CSRF, session rotation, login/me/logout, redaction, roles, store scope and invalid bearer-token denial |
| `npm run build` in clone/frontend | Passed: 477.56 kB JS / 146.61 kB gzip; 25.87 kB CSS / 6.92 kB gzip |
| `npm audit --omit=dev` in clone/frontend | Passed: zero vulnerabilities |
| `npm audit --json` in clone/frontend | Seven moderate development advisories, zero high/critical. Nonzero audit exit is documented, not counted as a passing clean audit |
| `npm run lint` and `npm run typecheck` in working frontend | Passed |
| `npm run test:e2e` in working frontend | Final complete rerun: 3 passed in 1.4 minutes; native isolated MySQL 9.1, Chromium, PHP/Vite test servers |
| `php artisan inventory:reconcile --env=testing` | Four mismatch categories zero |
| `node scripts/verify-openapi.mjs` at repository root | 50 registered operations match; references and operation IDs valid |
| `node scripts/verify-docs.mjs`, diff whitespace and staged paths | Local links and balanced fences checked before publication; explicit staged-path review excludes ignored runtime/configuration files |
| Known local-secret/private-key/token pattern scan | Working files and 284 historical blobs reviewed; no matches. Pattern checks are not a formal guarantee that every possible secret is absent |
| Visual review | Eight actual Chromium screenshots reviewed for correct workflow, readable desktop/mobile layout and credential-free fictional content |

The first browser attempt passed transfer and permission scenarios but timed out on the export button after the From date became blank. Logs showed repeated page reloads while the clean checkout was being installed in parallel. The final run, after installation finished, passed the unchanged export assertions. This is evidence of a successful rerun, not proof of a specific root cause. An initial OpenAPI invocation used the backend directory and could not find the root script; rerunning from the documented repository root passed.

## Demonstrated dataset

The browser runner resets only the guarded test schema, creates 3 stores/24 products/5 users and posts three opening receipts through domain services. Main/Downtown SF-001 starts at 100/30, then the separate approver posts 20 for final 80/50. The ledger includes paired -20/+20 entries; a 999-unit issue is rejected. Low stock, scoped reports, CSV download, audit history and mobile access are exercised.

Gallery images come from actual runs on this date. Overview, products, draft/submitted/posted transfer, ledger and mobile captures were reviewed from the first run's passing scenarios; the report capture comes from the successful complete rerun. No generated mockups or client data are included.

## Evidence boundaries

Backend code and frontend application code did not change. The full 62-test / 476-assertion backend suite and 18 UI tests are Phase 5 evidence, not rerun counts for this phase. [GitHub run 41613736051](https://github.com/chanakanaveen/stockflow/actions/runs/41613736051) verifies that code on Linux/MySQL 8.4. The fresh local clone exercises the unchanged application; working-tree browser capture additions were exercised separately.

Current advisory findings involve minimatch and TypeScript ESLint development dependencies. No dependency versions were changed; registry results differ from the earlier 12-advisory report. Full Compose boot, a production deployment, production load measurement, initial-admin provisioning and backup restoration remain unverified. See [limitations](roadmap.md) and [deployment](deployment.md).

GitHub authentication was checked as chanakanaveen; origin is the requested repository. Publication hash, push outcome and remote agreement are reported after the checkpoint commit. Temporary app/test services are stopped after verification to preserve the user's requested stopped state.
