import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { api, ApiError } from './api'
import type { Store, User } from './api'
import './App.css'

function App() {
  const [user, setUser] = useState<User | null>(null)
  const [stores, setStores] = useState<Store[]>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    api.me(controller.signal).then(({ data }) => setUser(data)).catch((err: unknown) => {
      if (!controller.signal.aborted && !(err instanceof ApiError && [401, 419].includes(err.status))) {
        setError('Cannot reach StockFlow. Check that the API is running, then reload.')
      }
    }).finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [])

  useEffect(() => {
    if (!user || !user.permissions.includes('stores.view')) return
    const controller = new AbortController()
    api.stores(controller.signal).then(({ data }) => setStores(data)).catch((err: unknown) => {
      if (controller.signal.aborted) return
      if (err instanceof ApiError && [401, 419].includes(err.status)) {
        setUser(null)
        setStores([])
        setError('Your session expired. Please sign in again.')
      } else setError('Unable to load your stores. Please reload to try again.')
    })
    return () => controller.abort()
  }, [user])

  async function login(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError('')
    setBusy(true)
    try {
      const { data } = await api.login(email.trim(), password)
      setUser(data)
      setPassword('')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Sign in failed. Please try again.')
    } finally { setBusy(false) }
  }
  async function logout() {
    setBusy(true)
    setError('')
    try {
      await api.logout()
      setUser(null)
      setStores([])
    } catch (err) {
      if (err instanceof ApiError && [401, 419].includes(err.status)) {
        setUser(null)
        setStores([])
      } else setError('Sign out failed. Please try again.')
    } finally { setBusy(false) }
  }

  return (
    <div className="app">
      <header className="topbar">
        <a href="/" className="brand" aria-label="StockFlow home"><span className="brand-icon" aria-hidden="true">S</span>StockFlow</a>
        <span className="environment">Local demo · Phase 1</span>
      </header>
      <main>
        {loading ? <p role="status" className="loading">Checking your session…</p> : !user ? (
          <div className="login-layout">
            <section className="intro">
              <span className="eyebrow">MULTI-STORE INVENTORY</span>
              <h1>A clear view.<br />A solid foundation.</h1>
              <p>One place for your team and stores. Sign in to access the StockFlow foundation.</p>
              <div className="intro-note"><span aria-hidden="true">↗</span><div><strong>Built around access you can trust</strong><p>Your role and store assignments determine what you can see.</p></div></div>
            </section>
            <section className="card login-card" aria-labelledby="login-title">
              <h2 id="login-title">Welcome back</h2>
              <p className="muted">Sign in with your local demo account.</p>
              {error && <p role="alert" className="error">{error}</p>}
              <form onSubmit={login}>
                <label htmlFor="email">Email address</label>
                <input id="email" type="email" autoComplete="username" required maxLength={255} value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@stockflow.test" />
                <label htmlFor="password">Password</label>
                <input id="password" type="password" autoComplete="current-password" required maxLength={255} value={password} onChange={(e) => setPassword(e.target.value)} />
                <button disabled={busy} type="submit">{busy ? 'Signing in…' : 'Sign in'}</button>
              </form>
              <p className="footnote">Demo accounts are created during local setup. Ask the person who configured this instance for access.</p>
            </section>
          </div>
        ) : (
          <section className="workspace">
            <div className="workspace-heading"><div><span className="eyebrow">YOUR WORKSPACE</span><h1>Hello, {user.name}</h1><p className="muted">{user.email}</p></div><button className="secondary" disabled={busy} onClick={logout}>{busy ? 'Signing out…' : 'Sign out'}</button></div>
            {error && <p role="alert" className="error">{error}</p>}
            <div className="role-row">{user.roles.map((role) => <span className="badge" key={role}>{role}</span>)}</div>
            <section className="card" aria-labelledby="stores-title"><h2 id="stores-title">Your stores</h2><p className="muted">Stores available to your account.</p>
              {!user.permissions.includes('stores.view') ? <p>Your role does not include store access.</p> : stores.length === 0 ? <p>No stores to display. Your administrator can review your assignments.</p> : <ul className="store-list">{stores.map((store) => <li key={store.id}><div><strong>{store.name}</strong><span>{store.code}</span></div><span className={store.is_active ? 'status' : 'status inactive'}>{store.is_active ? 'Active' : 'Inactive'}</span></li>)}</ul>}
            </section>
            <aside className="stage-note"><strong>Foundation ready</strong><p>Authentication, roles, and store access are available. Inventory transactions and dashboards will arrive in later stages.</p></aside>
          </section>
        )}
      </main>
      <footer>StockFlow <span>Inventory starts with integrity.</span></footer>
    </div>
  )
}
export default App
