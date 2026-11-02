# StockFlow

StockFlow is a multi-store inventory management project. Its Laravel API supports secure sign-in, role-based store access, inventory masters, and atomic receipt/issue posting. The React interface currently provides sign-in and assigned-store visibility.

Implemented foundation:
- Cookie-based login, session inspection and logout, with CSRF protection.
- Active-account enforcement, four seeded roles and granular permissions.
- Server-enforced store scope and an admin-only user listing.
- Opt-in fictional demo accounts, isolated MySQL tests and a local Compose configuration.

Phase 2 adds category/product/store management, receipt/issue drafts, transactional posting, exact decimal balances, an immutable ledger, idempotent retries, audit records and reconciliation. See the [Phase 2 report](docs/phase2-verification.md) for APIs, commands and verification.

Phase 3 adds transfers with submission, eligible approvals, rejection, cancellation and atomic movement between stores. Creators and submitters cannot approve their own transfers, including admins. See the [Phase 3 report](docs/phase3-verification.md) and [transfer OpenAPI contract](docs/openapi-transfers.json). Reports and the full inventory dashboard remain planned for later phases.

Start with [local setup](docs/setup.md). See [implementation status](docs/implementation-status.md) and [verification](docs/testing.md) for actual check results and limitations. Docker execution has not been verified on this host; native MySQL validation was explicitly approved as the Phase 1 alternative.

Design references: [architecture](docs/architecture.md), [database](docs/database.md), [business rules](docs/business-rules.md), [API](docs/api.md), [security](docs/security.md), and [milestones](docs/milestones.md).

Every verified, authorized stage is committed and pushed using the [Git workflow](docs/git-workflow.md). Project licensing remains undecided; dependency licenses remain their respective authors' licenses.

Repository: [chanakanaveen/stockflow](https://github.com/chanakanaveen/stockflow)
