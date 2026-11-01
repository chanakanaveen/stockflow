# Planned API contract

Phase 1 implements POST /auth/login, POST /auth/logout, GET /auth/me, GET /stores, GET /stores/{id}, and GET /users (admin only). Other routes below remain planned. Prefix all application routes with `/api/v1`. Sanctum CSRF initialization uses `/sanctum/csrf-cookie`. Cookie credentials and CSRF header are required for browser mutations; use a same-origin dev proxy. Production uses HTTPS, Secure/HttpOnly session cookies and SameSite=Lax; no wildcard credentialed CORS.

| Area | Routes |
| --- | --- |
| Authentication | POST /auth/login, POST /auth/logout, GET /auth/me |
| Masters | GET/POST /products, /categories, /stores; GET/PATCH each /{id} |
| Administration | GET/POST /users, PATCH /users/{id}; GET /roles for fixed seeded role assignment |
| Inventory reads | GET /inventory/balances, /inventory/ledger |
| Receipt/issue drafts | GET/POST /inventory/receipts and /inventory/issues; GET/PATCH each /{id}; POST each /{id}/post and /{id}/cancel |
| Transfers | GET/POST /transfers, GET/PATCH /transfers/{id}; POST /transfers/{id}/submit, /approve, /reject, /cancel |
| Reports | GET /dashboard/summary, /reports/stock-summary, /reports/low-stock, /reports/movements, /reports/movements/export |
| Audit | GET /audit-logs |

No deletes of historical records or ledger writes. Master deactivation is PATCH. User changes include is_active, seeded role assignment and store assignments under users.manage; arbitrary role/permission creation is deferred. Reject requires a nonblank reason of at most 1000 characters. Draft endpoints accept store IDs and lines `{product_id, quantity}`; actor, numbering, status and source fields are server-derived.

Resource success: `{data: resource, meta: {request_id}}`. Collections add pagination meta and links. Errors: `{error: {code, message, details}, meta: {request_id}}`; details is an array, with validation entries containing field/message. Quantities are strings with four decimal places. Datetimes are ISO-8601 UTC. Successful creation returns 201; reads, updates and actions return 200; logout returns 204.

Use 401 unauthenticated, 403 action forbidden/inactive account, 404 resource absent or outside store scope, 422 invalid input or INSUFFICIENT_STOCK, 409 INVALID_STATE/ALREADY_PROCESSED/IDEMPOTENCY_CONFLICT, 419 CSRF/session expiry, 429 throttled, 503 INVENTORY_BUSY and sanitized 500. Frontend handles both 401 and 419 with a session-expired flow, without silently repeating stock mutations under a new key.

Pagination defaults to 25, max 100, with stable ID tie-breaks. Allowlist sort/filter fields per endpoint. Inventory supports store_id/product_id; transactions support status and date bounds; ledger/reports support store_id/product_id/date_from/date_to. Date ranges use inclusive UTC start and exclusive end. Queries always apply store scope before pagination/aggregation. Approval inbox uses submitted transfers filtered by policy eligibility. Search is debounced and filters appear in the UI URL.

Low stock means an active product's available quantity is below its reorder level in an accessible active store; missing balances count as zero. Reports include zero-stock products using a paginated store/product projection rather than requiring balance rows for every combination. Dashboard pending counts are scoped; UI may show an explanatory placeholder before Phase 5 adds aggregations. CSV export streams scoped data with safe quoting and neutralized formula-leading cells.

OpenAPI 3 documentation will describe only implemented endpoints; update alongside each backend stage and finalize in Phase 5. Password reset and outbound email are deferred until secure delivery is configured.
