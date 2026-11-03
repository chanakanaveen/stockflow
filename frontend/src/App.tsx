import { Component, useEffect, useState } from 'react'
import type { FormEvent, ReactNode } from 'react'
import { BrowserRouter, Link, NavLink, Route, Routes, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider, useQueryClient } from '@tanstack/react-query'
import {
  Activity,
  ArrowDownLeft,
  ArrowLeftRight,
  ArrowUpRight,
  Boxes,
  CheckCheck,
  ChevronRight,
  LayoutDashboard,
  LogOut,
  Menu,
  Package,
  ShieldCheck,
  Store,
  Tags,
  Users,
  X,
} from 'lucide-react'
import { api, ApiError } from './api'
import type { User } from './types'
import { Session } from './state'
import { Empty, ErrorNotice, Loading } from './ui'
import { Masters } from './Masters'
import { Inventory, Overview } from './Inventory'
import { Reports } from './Reports'
import { Transactions, TransactionDetail } from './Transactions'
import './App.css'
const makeQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: { retry: false, staleTime: 15000, refetchOnWindowFocus: true },
      mutations: { retry: false },
    },
  })
export default function App() {
  const [client] = useState(makeQueryClient)
  return (
    <QueryClientProvider client={client}>
      <BrowserRouter>
        <AppContent />
      </BrowserRouter>
    </QueryClientProvider>
  )
}
function AppContent() {
  const client = useQueryClient()
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<unknown>(null)
  const [toast, setToast] = useState('')
  useEffect(() => {
    const controller = new AbortController()
    api
      .me(controller.signal)
      .then((r) => setUser(r.data))
      .catch((e: unknown) => {
        if (!controller.signal.aborted && !(e instanceof ApiError && [401, 419].includes(e.status)))
          setError(e)
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })
    const expire = () => {
      client.clear()
      setUser(null)
      setError(new Error('Your session expired. Please sign in again.'))
    }
    const refresh = () => {
      api
        .me()
        .then((r) => {
          client.clear()
          setUser(r.data)
        })
        .catch(expire)
    }
    const notify = (event: Event) => setToast((event as CustomEvent<string>).detail)
    window.addEventListener('stockflow:session-expired', expire)
    window.addEventListener('stockflow:refresh-session', refresh)
    window.addEventListener('stockflow:toast', notify)
    return () => {
      controller.abort()
      window.removeEventListener('stockflow:session-expired', expire)
      window.removeEventListener('stockflow:refresh-session', refresh)
      window.removeEventListener('stockflow:toast', notify)
    }
  }, [client])
  useEffect(() => {
    if (!toast) return
    const timeout = setTimeout(() => setToast(''), 5000)
    return () => clearTimeout(timeout)
  }, [toast])
  async function logout() {
    try {
      await api.logout()
      client.clear()
      setUser(null)
      setError(null)
    } catch (e) {
      if (e instanceof ApiError && [401, 419].includes(e.status)) {
        client.clear()
        setUser(null)
      } else setError(e)
    }
  }
  if (loading)
    return (
      <div className="initial-loading">
        <Brand />
        <Loading />
      </div>
    )
  return (
    <>
      {!user ? (
        <Login
          error={error}
          signedIn={(data) => {
            client.clear()
            setUser(data)
            setError(null)
          }}
        />
      ) : (
        <Session.Provider value={{ user, logout }}>
          <Shell user={user} logout={logout} error={error} />
        </Session.Provider>
      )}
      {toast && (
        <div className="toast" role="status">
          <CheckCheck size={19} />
          {toast}
          <button aria-label="Dismiss notification" onClick={() => setToast('')}>
            <X size={15} />
          </button>
        </div>
      )}
    </>
  )
}
function Brand() {
  return (
    <Link to="/" className="brand" aria-label="StockFlow home">
      <span className="brand-icon">
        <Boxes size={23} strokeWidth={1.7} />
      </span>
      <span>
        Stock<span className="brand-light">Flow</span>
        <small>INVENTORY WORKSPACE</small>
      </span>
    </Link>
  )
}
function Login({
  error: initialError,
  signedIn,
}: {
  error: unknown
  signedIn: (user: User) => void
}) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)
  async function submit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const result = await api.login(email.trim(), password)
      setPassword('')
      signedIn(result.data)
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }
  return (
    <div className="login-page">
      <header>
        <Brand />
        <span className="muted">Your inventory, in sync.</span>
      </header>
      <main className="login-layout">
        <section className="login-intro">
          <span className="eyebrow">A CLEARER WAY TO MANAGE STOCK</span>
          <h1>
            Every store.
            <br />
            Every movement.
            <br />
            <em>One clear view.</em>
          </h1>
          <p>
            Bring your inventory together. Receive stock, coordinate transfers, and keep your team
            moving with confidence.
          </p>
          <div className="login-promise">
            <ShieldCheck size={26} />
            <div>
              <strong>Control that follows your team</strong>
              <p>Secure access. Clear approvals. A traceable history.</p>
            </div>
          </div>
        </section>
        <section className="panel login-card">
          <h2>Welcome back</h2>
          <p className="muted">Sign in to your StockFlow workspace.</p>
          <ErrorNotice error={error ?? initialError} />
          <form onSubmit={submit}>
            <label className="field">
              <span>Email address</span>
              <input
                type="email"
                required
                maxLength={255}
                autoComplete="username"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="you@company.com"
              />
            </label>
            <label className="field">
              <span>Password</span>
              <input
                type="password"
                required
                maxLength={128}
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </label>
            <button disabled={busy}>
              {busy ? 'Signing in…' : 'Sign in'}
              <ArrowUpRight size={17} />
            </button>
          </form>
          <p className="login-help">Need access? Contact your workspace administrator.</p>
        </section>
      </main>
      <footer>
        StockFlow<span>Inventory starts with integrity.</span>
      </footer>
    </div>
  )
}
const nav = [
  {
    group: 'WORKSPACE',
    items: [
      { to: '/', label: 'Overview', icon: LayoutDashboard, permission: '' },
      { to: '/products', label: 'Products', icon: Package, permission: 'products.view' },
      { to: '/categories', label: 'Categories', icon: Tags, permission: 'categories.view' },
      { to: '/stores', label: 'Stores', icon: Store, permission: 'stores.view' },
    ],
  },
  {
    group: 'INVENTORY',
    items: [
      { to: '/balances', label: 'Stock balances', icon: Boxes, permission: 'inventory.view' },
      { to: '/receipts', label: 'Receipts', icon: ArrowDownLeft, permission: 'inventory.view' },
      { to: '/issues', label: 'Issues', icon: ArrowUpRight, permission: 'inventory.view' },
      { to: '/transfers', label: 'Transfers', icon: ArrowLeftRight, permission: 'transfers.view' },
      { to: '/approvals', label: 'Approvals', icon: CheckCheck, permission: 'transfers.approve' },
      { to: '/ledger', label: 'Stock ledger', icon: Activity, permission: 'inventory.view' },
      { to: '/reports', label: 'Reports', icon: Activity, permission: 'reports.view' },
      { to: '/audit', label: 'Audit history', icon: ShieldCheck, permission: 'audit.view' },
    ],
  },
  {
    group: 'ADMINISTRATION',
    items: [{ to: '/users', label: 'Team & access', icon: Users, permission: 'users.manage' }],
  },
]
function Shell({
  user,
  logout,
  error,
}: {
  user: User
  logout: () => Promise<void>
  error: unknown
}) {
  const location = useLocation()
  const [open, setOpen] = useState(false)
  const [signingOut, setSigningOut] = useState(false)
  const title =
    nav.flatMap((g) => g.items).find((item) => item.to === '/' + location.pathname.split('/')[1])
      ?.label ?? 'Overview'
  async function signOut() {
    setSigningOut(true)
    await logout()
    setSigningOut(false)
  }
  return (
    <div className="workspace-shell">
      <a className="skip-link" href="#main">
        Skip to content
      </a>
      {open && (
        <button
          className="nav-backdrop"
          aria-label="Close navigation"
          onClick={() => setOpen(false)}
        />
      )}
      <aside className={'sidebar ' + (open ? 'nav-open' : '')}>
        <div className="sidebar-brand">
          <Brand />
          <button
            className="mobile-only icon-button"
            aria-label="Close navigation"
            onClick={() => setOpen(false)}
          >
            <X size={20} />
          </button>
        </div>
        <nav aria-label="Main navigation">
          {nav.map((group) => {
            const items = group.items.filter(
              (i) => !i.permission || user.permissions.includes(i.permission),
            )
            return (
              items.length > 0 && (
                <div className="nav-group" key={group.group}>
                  <span>{group.group}</span>
                  {items.map((item) => (
                    <NavLink
                      key={item.to}
                      to={item.to}
                      end={item.to === '/'}
                      onClick={() => setOpen(false)}
                    >
                      <item.icon size={18} />
                      <span>{item.label}</span>
                    </NavLink>
                  ))}
                </div>
              )
            )
          })}
        </nav>
        <div className="sidebar-footer">
          <div className="avatar">{user.name.slice(0, 2).toUpperCase()}</div>
          <div>
            <strong>{user.name}</strong>
            <small>{user.roles.join(', ')}</small>
          </div>
          <button
            className="icon-button"
            aria-label="Sign out"
            title="Sign out"
            disabled={signingOut}
            onClick={() => void signOut()}
          >
            <LogOut size={17} />
          </button>
        </div>
      </aside>
      <div className="workspace-main">
        <header className="workspace-topbar">
          <div>
            <button
              className="mobile-only icon-button"
              aria-label="Open navigation"
              onClick={() => setOpen(true)}
            >
              <Menu size={22} />
            </button>
            <span className="muted">Workspace</span>
            <ChevronRight size={14} />
            <strong>{title}</strong>
          </div>
          <span className="access-pill">
            <ShieldCheck size={14} />
            {user.roles[0]}
          </span>
        </header>
        <main id="main" tabIndex={-1}>
          <ErrorNotice error={error} />
          <PageBoundary key={location.pathname}>
            <Routes>
              <Route path="/" element={<Overview />} />
              {(['products', 'categories', 'stores', 'users'] as const).map((kind) => (
                <Route key={kind} path={'/' + kind} element={<Masters kind={kind} />} />
              ))}
              {(['receipts', 'issues', 'transfers'] as const).map((kind) => (
                <Route key={kind} path={'/' + kind} element={<Transactions kind={kind} />} />
              ))}
              {(['receipts', 'issues', 'transfers'] as const).map((kind) => (
                <Route
                  key={kind}
                  path={'/' + kind + '/:id'}
                  element={<TransactionDetail kind={kind} />}
                />
              ))}
              <Route path="/approvals" element={<Transactions kind="transfers" inbox />} />
              <Route path="/balances" element={<Inventory />} />
              <Route path="/ledger" element={<Inventory ledger />} />
              <Route path="/reports" element={<Reports />} />
              <Route path="/audit" element={<Reports audit />} />
              <Route
                path="*"
                element={
                  <Empty
                    title="Page not found"
                    text="This page may have moved or the address may be incorrect."
                    action={
                      <Link className="button" to="/">
                        Back to overview
                      </Link>
                    }
                  />
                }
              />
            </Routes>
          </PageBoundary>
        </main>
        <footer>
          <span>StockFlow · Every movement matters.</span>
          <span>Times shown in {import.meta.env.VITE_DISPLAY_TIMEZONE || 'Asia/Colombo'}</span>
        </footer>
      </div>
    </div>
  )
}
class PageBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  state = { failed: false }
  static getDerivedStateFromError() {
    return { failed: true }
  }
  render() {
    return this.state.failed ? (
      <Empty
        title="This page couldn’t be displayed"
        text="Reload to try again. Your saved records are safe."
        action={<button onClick={() => window.location.reload()}>Reload page</button>}
      />
    ) : (
      this.props.children
    )
  }
}
