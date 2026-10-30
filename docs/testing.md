# Verification plan and current evidence

Phase 0 is documentation only. No application tests, migrations, build, lint or static analysis suites exist yet, and none are claimed as passed.

## Planned checks by layer

- Unit: exact decimal parsing, precision/overflow boundaries, state transitions, request hashing and number formatting.
- Auth/API: login/me/logout, invalid credentials, inactive users, CSRF/session expiry, role/store denial, validated responses, pagination and sorting, guarded fields and duplicate masters.
- Posting: receipt/issue multi-line success, insufficient stock, inactive masters, duplicate requests, unique ledger counts, audit redaction, forced mid-transaction failure and complete rollback.
- Transfers: draft edit, submit, cancel, reject, approve, every invalid transition, no creator/submitter approval even as Admin, both-store scope, immutable terminal data and conservation.
- MySQL concurrency: competing issues/transfers, double approval with same and different keys, simultaneous missing-row creation, opposite-direction multi-product transfers and bounded retry behavior. Run separate connections/processes against committed fixtures with a synchronization barrier; do not wrap these tests in a single outer test transaction.
- Reconciliation: union of balance/ledger keys; exact sums including missing balances; one expected movement per receipt/issue line, two opposite movements per transfer line.
- UI: validation, permissions, loading/empty/error states, keyboard navigation, expired sessions and retry key reuse. Real-backend Playwright demo uses two distinct users to approve a transfer and verify ledger/balances.
- Reports/security: scoped totals and zero balances, safe CSV contents, query bounds, unauthorized exports, production error sanitization.

## Commands to introduce with the scaffold

Backend working directory: `backend/`. Plan `php artisan test`, `php artisan test --testsuite=Integration`, `vendor/bin/pint --test` and `vendor/bin/phpstan analyse`. Frontend working directory: `frontend/`. Plan `npm run typecheck`, `npm run lint`, `npm run test -- --run`, `npm run build`, and `npx playwright test` after E2E setup. These are planned interfaces, not commands that currently work in this repository.

Use an explicitly isolated MySQL test database with restricted credentials and a test-only database-name guard before any reset. Never point test migration/reset helpers at a developer or production database. CI provisions its own MySQL service. SQLite may support pure tests but cannot establish locking correctness.

## Discovery commands actually executed

`git status --short --branch`, `git config --get user.name`, `git config --get user.email`, `rg --files`, `php --version`, `php -m`, `composer --version`, `node --version`, `npm --version`, `docker --version`, `docker compose version`, and the full-path WAMP `mysql.exe --version` were inspected. Runtime versions and failures are recorded in [architecture](architecture.md). Docker commands failed because unavailable; service enumeration was denied. No connection to a database was attempted.

GitHub authenticated `/user` lookup confirmed `chanakanaveen` without logging credential contents. Repository-local Git identity was updated using the account's verified ID/no-reply address. Phase 0 checks additionally review document links, whitespace, staged content and commit metadata. Push verification is reported after publication so the committed document does not predict a future push outcome.
