# Phase 4 — React inventory dashboard

Implementation date: 2026-11-03. This stage supplies the responsive React interface and the administration endpoints required by the approved milestone. Reporting aggregates, exports, audit viewer and release packaging remain later stages.

## Implemented interface

- Authenticated workspace with permission-aware navigation, login/logout, mobile navigation, keyboard focus, loading states, empty/error states, success messages and not-found/error boundaries.
- Overview counts from existing paginated API totals: active products, accessible stores, submitted transfers and recorded balance positions. Recent ledger entries and role-appropriate shortcuts use live API data. No fabricated charts or report totals.
- Product/category/store management with validation, edit/deactivate controls, server pagination and product/category search/status filters.
- Balances with store/product filters, four-place quantities and low-stock markers for recorded positions. Missing balance records remain absent from this view; full zero-stock reporting belongs to Phase 5.
- Receipt/issue drafts, product lines, editing, detail, cancellation and confirmed posting with insufficient-stock feedback.
- Transfers with source/destination validation, drafting, submission, eligible approval inbox, approval/rejection confirmation, rejection reason, final-state detail and activity.
- Read-only ledger with date/store/product filters, signed quantities and source links.
- Admin team management: create/edit users, fixed role assignment, store assignments, activation and optional password replacement.

Forms use React Hook Form and Zod. React Router keeps list filters in the URL. TanStack Query handles server data and invalidation; posted stock is never asserted optimistically. Search is debounced. All lookup pages are loaded, avoiding a silent 100-item selection cutoff. Quantities remain strings; client comparisons use scaled BigInt.

Posting and approval keys are stored in sessionStorage under actor/action/resource. Retries and remounts in the same tab reuse a key until success. No passwords or authentication tokens are stored there. Failed confirmations stay open with actionable server errors. Login/logout/session expiry clear query data; 401/419 returns to sign-in without automatically replaying a stock mutation.

## Backend additions and data impact

POST /api/v1/users, PATCH /api/v1/users/{user}, and GET /api/v1/roles require users.manage. The fixed role allowlist is server-enforced. Passwords require 12–128 characters, are hashed and excluded from responses/audit metadata. User resources add assigned_store_ids separately from effective store_ids. Role/activation edits serialize on the fixed System Admin role row and reject removal of the last active administrator. Password replacement/deactivation removes stored sessions for that user.

No schema migration or existing inventory rewrite is needed. The optional browser-test runner resets only the guarded stockflow_test database with its dedicated user. It builds fictional data (three stores, 24 products, five users) and posts opening balances through receipt services. The development stockflow database is not reset.

## Local use and browser tests

Use the normal [setup instructions](setup.md) and launch the API on 18000 and Vite on 5173. Display timezone defaults to Asia/Colombo; set VITE_DISPLAY_TIMEZONE to another valid IANA timezone if needed. Recharts/trend charts are deferred until Phase 5 provides report aggregates.

From frontend:

```text
npm ci
npm run typecheck
npm run lint
npm run test -- --run
npm run build
npx playwright install chromium
npm run test:e2e
```

Configure backend/.env.testing with the isolated stockflow_test account and database before E2E. Run backend database tests and browser tests serially. The E2E runner generates a random password in process memory, resets/seeds the test schema, and starts owned test servers on 18100/5174. It refuses other database names/users or DB_URL overrides. It does not use the development server or credentials. Test traces and video are disabled; screenshots contain fictional application data and go under ignored .local/test-results locations. The test servers are stopped by Playwright.

The browser scenarios cover manager draft -> submit -> different approver -> post, 100/30 -> 80/50 balance conservation, paired ledger entries, final-state approval absence, admin master/user edits, auditor restrictions, insufficient-stock rollback feedback, and a mobile navigation/overflow check.

## Verification

Executed on 2026-11-03: 16 component/schema tests passed (113.93 seconds); TypeScript, ESLint and Vite production build passed. Two Chromium scenarios passed (2.0 minutes), including separate-user transfer approval, 80/50 balances, paired ledger, mobile navigation, admin edits, auditor denial and insufficient stock. Desktop ledger and mobile overview screenshots were visually reviewed. Shared form controls now have explicit accessible names; the browser test waits for ledger navigation before changing its filters.

Backend verification: the full run passed all 56 existing tests but initially failed the two new user tests on empty store assignment validation. After the validation correction, both new tests passed (18 assertions). PHPStan level 5, Pint and strict Composer validation passed. Real HTTP authentication smoke through the Vite proxy passed. Production npm audit reported zero vulnerabilities; 12 moderate development-tool advisories remain, with no forced breaking dependency changes. Build output is 471.12 kB JavaScript (145.15 kB gzip), 25.43 kB CSS (6.81 kB gzip). Screenshots and runtime logs remain ignored local artifacts. Native MySQL 9.1 remains the approved validation alternative; Docker/Compose and MySQL 8.4 are not established by these checks.

Implementation references: [React Hook Form validation](https://react-hook-form.com/integrations/zod), [TanStack Query quick start](https://github.com/TanStack/query/blob/main/docs/framework/react/quick-start.md), and [Playwright web servers](https://playwright.dev/docs/test-webserver).

Post-browser reconciliation passed: zero balance, document, transfer or source mismatches. A separate development mobile balance capture found no populated rows; the accepted mobile visual evidence is the seeded browser overview and navigation check.
