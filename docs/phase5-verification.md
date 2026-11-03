# Phase 5 — Reporting and hardening

## Implemented

- Dashboard low-stock attention count, current stock summary, low-stock report, per-store/per-product movement aggregates, CSV download, and read-only audit history.
- Stock projections include every active product in each accessible active store, with absent balances treated as zero. Low stock means quantity is strictly below reorder level.
- Movement reports aggregate the ledger using MySQL DECIMAL arithmetic. Incoming, outgoing and net quantities stay decimal strings. Products and units are never combined into an ambiguous grand total.
- Ledger/report date bounds use UTC: start of the first date through, but excluding, the day after the last date. Current stock reports are current-state views, not historical snapshots.
- CSV requires reports.export plus reports.view, both dates, no more than 366 inclusive days and no more than 10,000 rows. Export rate is five requests/minute per actor. A bounded list of ledger IDs fixes membership before streaming in batches of 500; later postings are excluded. Labels reflect current master names.
- CSV quotes separators and quotes through fputcsv, disables its proprietary escape character, and prefixes formula/control-leading user text with an apostrophe. This includes leading whitespace, tabs/newlines, ASCII formula prefixes and full-width variants. Numeric quantities come only from trusted DECIMAL columns. CSV consumers can alter escaping on save/reopen; import untrusted text as text.
- Admin/Auditor audit access is server-enforced. Non-admins see accessible store/document events and transfer events only when both stores are accessible. Global master/identity events are Admin-only. Audit payloads retain the existing explicit safe-field allowlists.
- Responses add frame-denial and same-origin referrer headers. Secure session cookies default on in production, with local HTTP remaining supported. Nginx also supplies these headers; its Docker runtime remains unverified locally.
- Completed [OpenAPI contract](openapi.json), including all registered API methods and CSRF initialization. Run `node scripts/verify-openapi.mjs` to check route coverage, internal references and operation ID uniqueness.
- Updated the user-added GitHub workflow for backend/frontend directories, PHP 8.3, Node 24, disposable MySQL 8.4, least-privilege workflow permissions and pinned action commits. It runs backend checks, frontend checks, browser acceptance, reconciliation and contract consistency. CI credentials are disposable service-only values, not application credentials.

## Verification

The complete native MySQL backend run passed 62 tests / 476 assertions in 487.00 seconds, including existing stock/transfer concurrency scenarios. The final bounded-export rerun passed all four report/audit tests (52 assertions, 153.80 seconds). PHPStan level 5, Pint, Composer validation, frontend typecheck and lint passed. The first UI run passed nine schema tests but its Windows app-test worker timed out before loading; the focused retry passed all nine app tests (96.24 seconds), for 18 passing tests across the runs. Production build passed: 477.56 kB JavaScript (146.61 kB gzip), 25.87 kB CSS (6.92 kB gzip). Live API frame/referrer/nosniff headers and unauthenticated response passed. All three Chromium scenarios passed (1.4 minutes), covering transfer posting, admin/auditor permissions, reports, CSV content/filtering and audit history. Post-browser reconciliation found zero mismatches in all four categories. The focused report/CSV/audit visual recheck passed (25.2 seconds) after the footer-spacing fix; final desktop and mobile screenshots were inspected. Final lint and production build passed. GitHub CI passed on Linux with MySQL 8.4; see the final run below.

Composer audit and production npm audit report zero advisories. The development npm tree retains 12 moderate advisories rooted in minimatch GHSA-g3hv-75wg-m249; the offered fix changes major tool versions. No unverified forced dependency override or downgrade was applied. These tools process the trusted repository during lint/build, not production HTTP input.

No migration or production deployment is included. No speculative indexes are added. Query timing evidence and the final index decision will be recorded below. Docker/Compose and local MySQL 8.4 execution remain unverified; native MySQL 9.1 is the approved local alternative.

## Reproduction

Use [setup](setup.md) with isolated stockflow_test credentials. Run backend tests before browser tests, never concurrently against that test database.

```text
cd backend
php artisan test
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
composer validate --strict
composer audit
cd ../frontend
npm ci
npm run typecheck
npm run lint
npm run test -- --run
npm run build
npm audit --omit=dev
npm run test:e2e
cd ../backend
php artisan inventory:reconcile --env=testing
```

On this Windows/Xdebug host, the documented single-process PHPStan invocation in the Phase 1 report avoids worker startup issues. After browser fixture preparation, set APP_ENV=testing and run `php tests/Support/measure-reports.php` from backend. It refuses other database/user names, performs only reads, records one warm-up plus five samples and captures EXPLAIN JSON. It does not claim production throughput or a before/after improvement.

Security reference: [OWASP CSV injection](https://community.owasp.org/attacks/CSV_Injection). Phase 6 release/demo documentation remains a separate milestone.

## Query and index evidence

Measured on isolated native MySQL 9.1.0 with 3 stores, 24 products, 72 balances and 74 ledger entries. One warm-up query preceded five timed executions; these medians include local PHP/PDO round-trip time, not HTTP rendering or concurrent load.

| Query | Returned rows | Warm median | Plan evidence |
| --- | --- | --- | --- |
| Low-stock projection | 24 | 1.462 ms | Three-store and 24-product scans; eq_ref lookup on stock_balances_store_id_product_id_unique; small temporary sort |
| Filtered movement aggregate | 1 | 0.747 ms | Range scan on ledger_store_product_posted_index; two ledger rows examined |
| Filtered ledger | 2 | 1.257 ms | Same composite range index; backward index scan, no filesort |

No added index is justified by this small-fixture evidence. Missing-balance projection inherently examines store/product combinations; larger deployment cardinalities require representative load measurements. No production throughput, scaling guarantee or before/after speedup is claimed. The read-only measurement script and ignored .local/phase5-query-measurements.json contain reproducible SQL plan evidence.

## GitHub verification follow-up

The first [Linux/MySQL 8.4 run](https://github.com/chanakanaveen/stockflow/actions/runs/41613143329) passed backend checks (62 tests) and frontend checks (18 tests), then exposed a timing race in the pre-existing balance browser assertion: it read the 24 unfiltered store rows before the product filter settled. The test now waits for the expected three balance rows and five ledger rows before checking values. The corrected rerun passed; no application behavior changed for this correction.

Final [GitHub Actions run 41613736051](https://github.com/chanakanaveen/stockflow/actions/runs/41613736051) passed for commit 8cc696451b057c0983b69620ab242c7288ef678a: backend verification on MySQL 8.4, frontend verification, all three browser scenarios, reconciliation and API contract consistency. The Phase 5 feature commit is f3698dacc3d0b7e49c12432b50e637eabc3a5f6b; 8cc6964 records the browser timing correction. No failing required checks remain. Full application Docker Compose execution remains unverified; CI validates the MySQL 8.4 service, not the complete Compose stack.
