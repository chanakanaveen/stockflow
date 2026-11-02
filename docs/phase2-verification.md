# Phase 2 — masters and inventory core

Implemented 2026-11-02. This stage adds backend APIs and stock posting; transfers and the inventory dashboard remain separate milestones.

## Implemented behavior

- Categories, products and stores support authorized creation, reading, editing and deactivation. SKU/store codes are normalized and unique. Historical references are retained; no delete endpoints are exposed. A product's base UOM cannot change after its first stock movement.
- Receipt/issue drafts support 1–100 distinct product lines, edit and cancellation. Drafts have no stock effect. Posting derives actor, source IDs, status and timestamps on the server; posted/cancelled documents cannot be edited or posted again.
- Quantities are JSON strings with up to 14 integer and four fractional digits, stored as DECIMAL(18,4) and calculated with BCMath. Positive lines are required; reorder levels may be zero. Numeric JSON values, exponent notation, negatives, excess precision and overflow are rejected.
- Posting commits balances, immutable ledger entries, document status, audit event and successful idempotency response in one transaction. Locks are taken on the document, store, products ordered by ID, then balances ordered by product ID. Missing balance rows are created within the transaction. Negative stock is rejected and all lines roll back together.
- Numbering uses a locked sequence per receipt/issue type and UTC year: RCV/ISS-YYYY-000001. Failed transactions do not consume a committed sequence increment.
- Idempotency is scoped to actor, document action and a case-sensitive printable ASCII key of 1–128 characters. Canonical JSON object key ordering stabilizes the request hash. Matching retries return the stored business response with a fresh request ID; changed payloads or a second key on an already posted document return 409. Object property order is not part of the JSON response contract.
- Failed attempts roll back the idempotency record and may be retried. Completed responses older than seven days can be pruned. Document state and ledger uniqueness continue to prevent reposting after pruning.
- API reads are permission/store scoped. Posting rechecks active products/stores under locks. The posting endpoint is limited to 30 requests/minute per actor. Final MySQL lock conflicts return a sanitized 503 after bounded transaction retries.
- Audit metadata contains selected identifiers/state, not passwords, tokens or raw request bodies. Ledger models reject updates/deletes, and no ledger write API exists. Direct privileged SQL is outside these application-level immutability controls.

## API surface

All paths below are under /api/v1 and use the existing cookie session, CSRF and active-user middleware.

| Resource | Methods |
| --- | --- |
| categories, products | GET collection/detail, POST collection, PATCH/PUT detail |
| stores | GET collection/detail, POST collection, PATCH detail |
| inventory/receipts, inventory/issues | GET collection/detail, POST collection, PATCH detail |
| inventory/receipts/{id}/cancel, inventory/issues/{id}/cancel | POST |
| inventory/receipts/{id}/post, inventory/issues/{id}/post | POST with Idempotency-Key |
| inventory/balances, inventory/ledger | GET |

Example draft body (substitute authorized IDs):

```json
{"store_id":1,"reference":"Opening stock","lines":[{"product_id":1,"quantity":"12.5000"}]}
```

Post with an empty JSON object and a new Idempotency-Key; reuse that key and body when retrying. Extra posting body fields never override the saved document and are included in the request hash. Lists are paginated (default 25, maximum 100). Products support search/category/active filters; categories support search/active filters; documents support store/status; balances and ledger support store/product. Ledger date_from/date_to are UTC calendar dates, inclusive, and either may be supplied alone.

## Migration and operations

The additive migration creates categories, products, balances, documents/lines, ledger, number sequences, audit logs and idempotency records, with foreign keys, uniqueness and quantity/state CHECK constraints. PHP ext-bcmath is now an explicit Composer requirement. No dependency versions changed.

From backend:

```text
php artisan migrate
php artisan inventory:reconcile
php artisan inventory:prune-idempotency
php artisan schedule:run
```

Reconciliation is read-only: it compares balance/ledger totals and checks posted document movement counts, quantities and source references, returning nonzero on drift. It never repairs balances automatically. The prune command is scheduled daily; deployment must run Laravel's scheduler. Do not roll back this migration on populated data without a verified backup and explicit approval; use a reviewed forward repair.

## Verification

Executed results on 2026-11-02:

| Check | Result |
| --- | --- |
| Full backend suite | 42 passed, 227 assertions, 197.26 seconds |
| Independent-process MySQL concurrency | All six scenarios passed: competing issues, missing balance creation, same-key replay, different-key duplicate post, number allocation, reversed product input order |
| Atomicity and validation | Multi-line failure and injected post-ledger exception fully rolled back; decimal boundaries, inactive masters, roles/store scope, state transitions and DB constraints passed |
| Fresh test migrations / additive development migration | Passed |
| PHPStan level 5 | No errors, with model-cast parsing enabled |
| Pint uncached verification | Passed |
| Composer validate --strict | Passed; lock change only adds BCMath platform requirement |
| Composer advisory check during lock refresh | No security vulnerability advisories found |
| Development inventory:reconcile | Zero balance mismatches, document mismatches or invalid sources |
| Publication review | Diff whitespace and local-secret/excluded-path checks passed; final commit/push checks reported after publication |

Initial failures were corrected before acceptance: quote MySQL's reserved last_value identifier, enable Larastan's casts-method inference, accept a ledger end-date filter on its own, and compare replayed JSON objects without requiring property order. No frontend files changed; this stage did not rerun frontend checks.

Tests use stockflow_test with its dedicated MySQL user; the base test class and concurrency workers reject other databases/users and DB_URL overrides. The Integration suite uses committed fixtures, two independent PHP processes/connections, and a start barrier; it does not use an outer test transaction.

Run suites serially against the isolated database:

```text
php artisan test
php artisan test --testsuite=Integration
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --debug --xdebug --no-progress --memory-limit=512M
composer validate --strict
```

On this Windows host set XDEBUG_MODE=off and invoke PHP with -d xdebug.log=, as in the Phase 1 report.

Docker/Compose remain unavailable. Validation uses the previously approved isolated native MySQL 9.1 instance on port 13316; this is not evidence of a Docker/MySQL 8.4 run. Frontend inventory UI and transfer workflows are not part of this stage.
