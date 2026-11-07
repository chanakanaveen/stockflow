# Authenticated API examples

The API uses same-origin session cookies, not personal bearer tokens. See [OpenAPI](openapi.json) for request/response schemas and [API guide](api.md) for errors and permissions. Examples below are for an opt-in local demo only.

## PowerShell session example

Start the local app using [setup](setup.md). Enter your locally selected demo password at the secure prompt; do not save it in this file. The session object holds cookies only in process memory.

```powershell
$base = 'http://127.0.0.1:18000'
$session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
Invoke-WebRequest "$base/sanctum/csrf-cookie" -WebSession $session | Out-Null
$csrf = [Uri]::UnescapeDataString($session.Cookies.GetCookies([Uri]$base)['XSRF-TOKEN'].Value)
$credential = Get-Credential -UserName 'manager@stockflow.test'
$body = @{ email = $credential.UserName; password = $credential.GetNetworkCredential().Password } | ConvertTo-Json
$headers = @{ Accept = 'application/json'; 'X-XSRF-TOKEN' = $csrf }
Invoke-RestMethod "$base/api/v1/auth/login" -Method Post -WebSession $session -Headers $headers -ContentType 'application/json' -Body $body
$body = $null
Invoke-RestMethod "$base/api/v1/auth/me" -WebSession $session -Headers @{ Accept = 'application/json' }
Invoke-RestMethod "$base/api/v1/inventory/balances?per_page=10" -WebSession $session -Headers @{ Accept = 'application/json' }
$csrf = [Uri]::UnescapeDataString($session.Cookies.GetCookies([Uri]$base)['XSRF-TOKEN'].Value)
Invoke-RestMethod "$base/api/v1/auth/logout" -Method Post -WebSession $session -Headers @{ Accept = 'application/json'; 'X-XSRF-TOKEN' = $csrf }
$session = $null
$credential = $null
```

Login returns the user in `data`; password fields are omitted. Logout returns 204. Unauthenticated `/auth/me` returns 401. CSRF failure is 419. Successful JSON responses carry a request ID; exact schemas are in OpenAPI. Avoid recording raw cookies or authentication headers.

## curl read requests

For an authenticated local cookie jar obtained through the same CSRF/login flow, these are equivalent read requests. Keep the jar outside tracked files, restrict its permissions and discard it after logout. `COOKIE_JAR` is a placeholder path, not a committed credential.

```sh
curl --fail-with-body --cookie "$COOKIE_JAR" -H 'Accept: application/json' \
  'http://127.0.0.1:18000/api/v1/inventory/balances?per_page=10'
curl --fail-with-body --cookie "$COOKIE_JAR" -H 'Accept: application/json' \
  'http://127.0.0.1:18000/api/v1/reports/low-stock?per_page=10'
```

For an automated, executable session example, run `node --env-file=backend/.env scripts/smoke-auth.mjs` from the repository root with the local API on 18000. It verifies real CSRF, session rotation, login/me/logout, roles and store scope and signs out its sessions. The script refuses non-local hosts. It uses the ordinary seeded admin/keeper accounts, not browser fixture accounts.

Posting and transfer approval require an `Idempotency-Key`. Keep a key stable when retrying the same request; changing a key is not a way to post twice. Obtain live resource IDs instead of assuming fixture IDs. Use a different eligible user for approval and consult the OpenAPI schemas before sending mutations.
