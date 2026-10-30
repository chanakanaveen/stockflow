# Implementation milestones and rollout

Every stage updates status and documentation, passes its required checks, receives its checkpoint commit, and is pushed. Work stops at the end of the authorized stage. Phase 0 documents are a design proposal for review, not user approval of Phase 1.

| Phase | Work and affected areas | Migration impact | Acceptance |
| --- | --- | --- | --- |
| 0 | Architecture, ERD/schema, rules, API, threat model, tests and milestone documents | None | Source specification and runtime inspection, coherent design, local link/diff checks |
| 1 | backend/frontend scaffold; Compose/web server; Sanctum cookie auth; seeded roles; policies/store scope; error envelopes; environment examples | Framework identity/session tables, users, stores, user_store_access and Spatie tables | Compose boots; fresh isolated MySQL migrations succeed; auth/role/store tests; initial backend/frontend checks pass |
| 2 | Masters, balances, receipt/issue services, ledger, numbering, idempotency, audit and master APIs | Categories/products, document/line/balance/ledger/sequence/audit/idempotency tables | Real MySQL atomicity, concurrency, rollback, duplicate protection, master validation and reconciliation |
| 3 | Transfer services/routes/policies, immutable submitted drafts, eligible approvals | Transfers and lines | Lifecycle tests, both-store access and no-self-approval, competing transfers, double approval and exact conservation |
| 4 | React authenticated shell, masters, users/role assignment, transactions, approvals, balances and ledger | None expected | Responsive real-backend demo, component/E2E checks, typecheck/lint/build; dashboard shows only available data |
| 5 | Aggregations, reports, CSV, audit viewer, OpenAPI completion, CI and measured query/index review | Only evidence-driven additive indexes | Scoped reports reconcile; CSV security checks; backend/frontend CI green; measured query evidence |
| 6 | Reproducible fictional demo, README, actual screenshots, API examples, deployment/recovery guide, video script and portfolio copy | No schema changes expected | Fresh-checkout walkthrough, no secrets, every claimed feature backed by implementation/tests |

Seed opening stock through receipts only. Seed 20–40 fictional products and three stores (Main Warehouse, Downtown Branch, Lakeside Branch). Use distinct creator and approver accounts; local demo credentials must be opt-in and disabled for production. Never insert balances directly.

Rollback: changes are additive until verified. For docs, revert through a new commit rather than rewriting history. For application stages, document a forward repair or reviewed revert before release; migration rollback may delete data and must never be run against a populated database without explicit approval and a verified backup. No deployment is part of these stage commits.

Out of MVP: multiple organizations, costing/accounting/currencies, serial/batch/expiry tracking, reservations/shipping/partial transfers, UOM conversion, negative stock, purchasing, offline sync, payments, category hierarchy, custom permission editing and compensating documents. Password reset waits for secure email delivery. Licensing and public deployment remain explicit release decisions.

Before Phase 1: review the Phase 0 design defaults and resolve Docker/MySQL availability. Exact installed dependency versions and reproducible commands belong in the Phase 1 report, not speculative Phase 0 claims.
