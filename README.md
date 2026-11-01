# StockFlow

StockFlow is a multi-store inventory management project. Phase 1 provides a Laravel API and React interface for secure sign-in, role-based access, and assigned-store visibility.

Implemented foundation:
- Cookie-based login, session inspection and logout, with CSRF protection.
- Active-account enforcement, four seeded roles and granular permissions.
- Server-enforced store scope and an admin-only user listing.
- Opt-in fictional demo accounts, isolated MySQL tests and a local Compose configuration.

Stock receipts, issues, transfers, ledger posting, reports and the full dashboard are planned for later phases.

Start with [local setup](docs/setup.md). See [implementation status](docs/implementation-status.md) and [verification](docs/testing.md) for actual check results and limitations. Docker execution has not been verified on this host; native MySQL validation was explicitly approved as the Phase 1 alternative.

Design references: [architecture](docs/architecture.md), [database](docs/database.md), [business rules](docs/business-rules.md), [API](docs/api.md), [security](docs/security.md), and [milestones](docs/milestones.md).

Every verified, authorized stage is committed and pushed using the [Git workflow](docs/git-workflow.md). Project licensing remains undecided; dependency licenses remain their respective authors' licenses.

Repository: [chanakanaveen/stockflow](https://github.com/chanakanaveen/stockflow)
