# Threat model and risk register

Assets: credentials, scoped business data, stock integrity, historical ledger and audit evidence. Boundaries: browser to API, authenticated actor to store/resource, application to database, CI/deployment to secrets.

| Threat or risk | Planned control and verification |
| --- | --- |
| Cross-store ID enumeration | Policies plus query scopes on detail/list/count/export; deny inaccessible resource as 404; cross-store API tests |
| Role escalation or self approval | Server-owned permission sets, guarded assignment fields, creator OR submitter check for every role; adversarial feature tests |
| Session theft/CSRF | HttpOnly Secure cookies, session rotation, CSRF header, same-origin serving, login throttling; logout and expiry tests |
| Concurrent overselling/replay | Sorted row locks, atomic ledger/balance/status/audit writes, idempotency and unique source keys; real MySQL concurrent processes |
| SQL injection/XSS/CSV formulas | Bound queries, allowlisted sort keys, React escaping, no untrusted HTML, CSV formula neutralization including leading control characters; injection tests |
| Secret leakage | Ignore environment files, no raw request audit payloads, production debug disabled, sanitized errors; staged diff review |
| Ledger tampering | No mutation routes, service-only append behavior, separate migration credentials; immutability and reconciliation tests |
| Denial of service | Bounded pagination/line counts, login 5/minute per normalized email+IP, posting 30/minute per actor, bounded transaction retries and export filters |
| Account/store deactivation races | Active-account middleware, transactional status rechecks and shared master row locks; deactivation/posting integration tests |
| Missing Docker/database | Phase 1 acceptance blocker; do not claim stack or MySQL tests passed until available |
| Dependency incompatibility | Resolve compatible versions in Phase 1, commit lockfiles and actual versions; no unsupported version claims |
| Inaccurate portfolio claims | README/status distinguish planned from implemented; actual app screenshots and test records only |

Audit metadata uses explicit allowlisted IDs, state transitions and safe reasons; never passwords, session cookies, tokens or raw request bodies. Audit readers are Admin/Auditor and still subject to store scope; global identity-management events are Admin-only. Retain history in MVP; define retention policy before any real deployment.

Recovery: run reconciliation read-only; if discrepancies appear, suspend affected posting, preserve logs and database evidence, investigate the failed invariant and restore from a verified backup through an explicitly authorized process. Do not repair by silently changing balances or historical ledger. Compensating business documents are future work.

Deployment and destructive migration execution require separate authorization. No databases, services, global runtimes or historical Git commits were modified by Phase 0. The repository-local Git identity change applies only to future commits.
