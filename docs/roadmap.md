# Limitations and roadmap

See [status](implementation-status.md) for delivered features; the original design is not a claim that every proposed feature shipped.

## Limits

- No public deployment, production acceptance, backup restoration or disaster-recovery drill has taken place.
- Full Compose execution remains unverified. MySQL 8.4 CI passed independently; native validation used isolated MySQL 9.1.
- Phase 5 recorded 12 moderate development-tool advisories and zero production dependency advisories. Reassess before deployment.
- Small-fixture query timings do not establish capacity or speedup.
- Licensing remains undecided; no open-source grant or client endorsement is implied.
- No multi-tenancy, accounting/costing, batch/serial/expiry tracking, reservations, partial fulfillment, purchasing, offline sync, UOM conversion or negative stock.
- No secure email-backed password reset, compensating documents or custom permission editor.
- Ledger immutability is enforced through application paths; privileged database administrators remain a trust boundary.
- Stock reports show current state, not historical snapshots. No forecasting or trend chart is claimed.

## Separately authorized future work

1. Resolve development-tool advisories with compatible upgrades and regression checks.
2. Validate complete Compose execution and a production-like deployment/restore rehearsal.
3. Measure representative volumes and contention before adding indexes or capacity claims.
4. Decide license, hosting, operational ownership, retention, monitoring, recovery objectives and initial-admin provisioning.
5. Design business-approved compensating documents and secure password recovery.
