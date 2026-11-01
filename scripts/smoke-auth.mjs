import assert from 'node:assert/strict'

const base = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:18000'
if (!['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw new Error('Smoke tests are restricted to localhost')
const password = process.env.DEMO_PASSWORD
const sessionCookie = process.env.SESSION_COOKIE ?? 'stockflow-session'
if (!password) throw new Error('Set DEMO_PASSWORD for the opt-in local demo')
function client() {
  const jar = new Map()
  return {
    jar,
    async request(path, { method = 'GET', body, csrf = true, bearer = false } = {}) {
      const headers = { Accept: 'application/json', Cookie: [...jar].map(([key, value]) => key + '=' + value).join('; ') }
      if (body) headers['Content-Type'] = 'application/json'
      if (csrf && jar.has('XSRF-TOKEN')) headers['X-XSRF-TOKEN'] = decodeURIComponent(jar.get('XSRF-TOKEN'))
      if (bearer) headers.Authorization = 'Bearer 1|invalid'
      const response = await fetch(base + path, { method, headers, body: body ? JSON.stringify(body) : undefined, redirect: 'manual', signal: AbortSignal.timeout(90000) })
      for (const cookie of response.headers.getSetCookie()) {
        const pair = cookie.split(';')[0]
        const split = pair.indexOf('=')
        jar.set(pair.slice(0, split), pair.slice(split + 1))
      }
      const text = await response.text()
      let json = null
      if (text) {
        try { json = JSON.parse(text) } catch { throw new Error(method + ' ' + path + ' returned non-JSON HTTP ' + response.status) }
      }
      return { status: response.status, json, headers: response.headers }
    },
  }
}
async function login(session, email) {
  assert.equal((await session.request('/sanctum/csrf-cookie')).status, 204)
  const before = session.jar.get(sessionCookie)
  assert.ok(before, 'CSRF initialization must establish a session cookie')
  const result = await session.request('/api/v1/auth/login', { method: 'POST', body: { email, password } })
  assert.equal(result.status, 200)
  assert.equal(result.json.data.email, email)
  assert.equal('password' in result.json.data, false)
  assert.notEqual(session.jar.get(sessionCookie), before)
  assert.equal(result.json.meta.request_id, result.headers.get('x-request-id'))
}
const keeper = client()
assert.equal((await keeper.request('/api/v1/auth/me')).status, 401)
assert.equal((await keeper.request('/api/v1/auth/login', { method: 'POST', body: { email: 'keeper@stockflow.test', password }, csrf: false })).status, 419)
await login(keeper, 'keeper@stockflow.test')
assert.equal((await keeper.request('/api/v1/auth/me')).status, 200)
const ownStores = await keeper.request('/api/v1/stores')
assert.equal(ownStores.status, 200)
assert.equal(ownStores.json.data.length, 1)
assert.equal((await keeper.request('/api/v1/users')).status, 403)
const admin = client()
await login(admin, 'admin@stockflow.test')
const allStores = await admin.request('/api/v1/stores')
const forbiddenStore = allStores.json.data.find((store) => store.id !== ownStores.json.data[0].id)
assert.ok(forbiddenStore)
assert.equal((await keeper.request('/api/v1/stores/' + forbiddenStore.id)).status, 404)
assert.equal((await admin.request('/api/v1/users')).status, 200)
assert.equal((await keeper.request('/api/v1/auth/logout', { method: 'POST', csrf: false })).status, 419)
assert.equal((await keeper.request('/api/v1/auth/logout', { method: 'POST' })).status, 204)
assert.equal((await keeper.request('/api/v1/auth/me')).status, 401)
assert.equal((await keeper.request('/api/v1/auth/me', { bearer: true })).status, 401)
assert.equal((await admin.request('/api/v1/auth/logout', { method: 'POST' })).status, 204)
console.log('PASS: real HTTP CSRF, session rotation, login/me/logout, secret redaction, roles, store scope, and bearer-token denial')
