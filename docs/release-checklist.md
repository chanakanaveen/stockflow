# Release evidence and hygiene

This checkpoint is a portfolio/documentation release, not production acceptance. Verification is recorded in [Phase 6 results](phase6-verification.md).

| Showcased behavior | Implementation / evidence |
| --- | --- |
| Sessions, roles, store boundaries | AuthController, policies, Phase 1 HTTP checks and backend tests |
| Atomic receipts/issues, no overselling, retries and rollback | Inventory posting services; Phase 2 independent-process MySQL tests |
| Separate approver, transfer conservation and concurrent posting | Transfer services; Phase 3 concurrency suite; real two-user browser scenario |
| Responsive workspace and master/user management | React feature pages; UI tests and browser scenarios |
| Scoped reports, CSV safety and audit | ReportController/ReportQueries/SafeCsv; report feature and browser tests |
| Actual fictional demo | Guarded browser fixture; posted opening receipts; screenshots and reconciliation |
| API contract consistency | `node scripts/verify-openapi.mjs` checks routes and references |

## Publication review

- Review explicit staged paths, text diffs, screenshots and whitespace before committing.
- Keep environment files, credentials, cookies, local databases, dependencies, test caches and generated build artifacts ignored.
- Scan tracked text and history for private keys, credential patterns and known local secret values without printing those values. Pattern checks cannot guarantee absence of every possible secret; review suspicious matches rather than claiming a formal audit.
- Review screenshots for secrets and real personal/client data; fixture identities are fictional.
- Keep exact test counts tied to their run/commit; do not relabel historical results as freshly executed.
- Preserve license uncertainty and third-party attribution; no stock photos, proprietary client assets or generated mockups are used.
- Verify origin, author identity, upstream and local/remote agreement after push. Never force-push.

Production release remains conditional on the [deployment runbook](deployment.md), unresolved [limitations](roadmap.md), an initial-admin process, a restore drill, and separate deployment authorization.
