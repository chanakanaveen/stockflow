# Phase 3 — transfers and approvals

Implemented 2026-11-02. This stage adds transfer APIs, policy controls, atomic stock movement and verification. The React inventory dashboard remains Phase 4 work.

## Behavior and permissions

Transfers move through draft -> submitted -> approved_posted or rejected. Drafts may instead be cancelled. Only drafts can be edited, and rejected transfers require a new draft. Submission does not reserve or move stock. Approval checks live balances and posts both sides in one transaction.

Every action and list requires access to both stores. Admin can access all stores, but cannot bypass the creator/submitter approval prohibition. Inventory Managers can approve another user's submission; Store Keepers can create, edit, submit and cancel drafts but cannot approve or reject. Auditors can only read. Rejection requires a trimmed, nonblank reason of at most 1000 characters. The no-self rule applies to approval, as specified in the design.

Drafts require distinct active stores and 1–100 unique active products with positive decimal-string quantities. Store/product activity is rechecked under shared locks at submission and approval. Transfers receive a locked TRF-UTCyear-counter number. Submitted lines, actors and terminal status cannot be edited through the API.

Approval takes the idempotency record and transfer lock, then shared store locks by ID, shared product locks by ID, and balance locks ordered by (store_id, product_id). Receipt/issue validation now uses the same shared master-lock helper. Missing balance rows are inserted inside the transaction. All source sufficiency and destination overflow checks happen before balance mutations. Each line appends exactly one negative source and positive destination movement; status, approver/timestamps, audit and replay record commit together. Any exception rolls back every effect.

Approval requires a printable ASCII Idempotency-Key of at most 128 characters. Reuse the same key and JSON body on retry. Keys are scoped to actor/action/transfer; object property order does not change the payload hash. Matching successful retries return the stored resource and a fresh request ID. Changed payloads return 409; a different key or an expired replay record cannot repost a terminal transfer. Failed attempts are not cached. Approval shares the 30/minute per-actor posting throttle and three-attempt deadlock handling. No notifications or network requests run within transactions.

## API

Prefix: /api/v1. Use the existing cookie session and CSRF header. The [OpenAPI transfer contract](openapi-transfers.json) describes the implemented routes.

| Route | Operation |
| --- | --- |
| GET /transfers | Scoped paginated list; status/from_store_id/to_store_id/date_from/date_to filters |
| GET /transfers?approval_inbox=1 | Submitted transfers the current approver did not create or submit; requires approval permission |
| POST /transfers | Create draft; returns 201 |
| GET /transfers/{id} | Read scoped resource |
| PATCH /transfers/{id} | Edit draft stores/lines |
| POST /transfers/{id}/submit | Record submitter and time; no stock effect |
| POST /transfers/{id}/cancel | Cancel draft |
| POST /transfers/{id}/reject | Reject submitted transfer with rejection_reason |
| POST /transfers/{id}/approve | Atomically approve/post with Idempotency-Key |

Pagination defaults to 25, maximum 100; newest ID first. Date filters are inclusive UTC creation dates and can be supplied independently. Inbox eligibility and both-store scope apply before pagination/counts.

Example draft (use accessible IDs):

```json
{"from_store_id":1,"to_store_id":2,"lines":[{"product_id":1,"quantity":"20.0000"}]}
```

Submit as the creator or another authorized actor. Sign in as an eligible different approver, then approve with an empty JSON object and an Idempotency-Key. Responses include four-place string quantities, ISO-8601 UTC timestamps and server-derived actor IDs. There is no delete endpoint.

## Migration, reconciliation and recovery

The additive migration creates stock_transfers and stock_transfer_lines with restricted foreign keys, unique numbers/product lines and CHECK constraints for status, distinct stores, positive lines and no self-approval. Existing quantities and ledger records are not rewritten.

```text
php artisan migrate
php artisan inventory:reconcile
php artisan test
php artisan test --testsuite=Integration
```

Reconciliation now also verifies that every approved transfer line has exactly one out and one in, zero net quantity, correct stores/products/source IDs and exact line quantities. It detects transfer ledger entries for missing or non-posted sources. It remains read-only. No automatic repair or populated-database rollback is authorized; use a reviewed forward repair with a verified backup.

## Verification

Executed on 2026-11-02:

| Check | Result |
| --- | --- |
| Full backend suite | 56 passed, 406 assertions, 421.44 seconds |
| Transfer feature coverage | Acceptance example, self-approval including Admin, distinct submitter, scope/replay denial, inbox, state transitions, roles, validation, inactivity, DB constraints, insufficient stock, overflow, fault rollback, expiry and reconciliation passed |
| New transfer concurrency coverage | Eight scenarios passed: competing transfers, same-key double approval, two approvers/keys, opposite directions/multiple products, missing destination row, transfer versus issue, approve versus reject, concurrent numbering |
| Previous inventory concurrency coverage | All six scenarios passed |
| Migrations | Additive development migration and fresh isolated test migrations passed |
| PHPStan level 5 | No errors |
| Pint uncached verification | Passed |
| Composer validate --strict | Passed; dependencies unchanged |
| Development reconciliation | Zero balance, document, transfer and invalid-source mismatches |
| OpenAPI checks | JSON parsed; 92 internal references resolved; all eight operations match registered routes |
| Publication review | Markdown links, diff whitespace, excluded-path and known-local-secret checks passed; final commit/push checks reported after publication |

Static analysis initially could not infer migration columns declared through loops; explicit declarations fixed inference without changing the resulting database schema. No runtime feature-test failures occurred. Tests use the guarded stockflow_test database and dedicated account. Concurrency tests use committed fixtures, independent PHP processes/MySQL connections and a shared start barrier. Never run database suites simultaneously against that database.

Run static checks from backend with PHP vendor/bin/pint --test, composer validate --strict and PHP vendor/bin/phpstan analyse --debug --xdebug --no-progress --memory-limit=512M. On this Windows host set XDEBUG_MODE=off and invoke PHP with -d xdebug.log= as in the Phase 1 report. The OpenAPI check covered internal references and route agreement; a full external OpenAPI validator was not run.

The approved native MySQL 9.1 alternative is used on port 13316. Docker/Compose and MySQL 8.4 remain unverified on this host. No frontend files change in this stage.
