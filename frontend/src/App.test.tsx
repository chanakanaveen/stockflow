import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
const manager = {
  id: 1,
  name: 'Demo Manager',
  email: 'manager@stockflow.test',
  is_active: true,
  roles: ['Inventory Manager'],
  permissions: [
    'products.view',
    'categories.view',
    'stores.view',
    'inventory.view',
    'inventory.receive',
    'inventory.issue',
    'transfers.view',
    'transfers.create',
    'transfers.update',
    'transfers.submit',
    'transfers.cancel',
    'transfers.approve',
    'transfers.reject',
  ],
  store_ids: [1, 2],
}
const stores = [
  { id: 1, code: 'MAIN', name: 'Main Warehouse', location_label: null, is_active: true },
  { id: 2, code: 'DOWN', name: 'Downtown Branch', location_label: null, is_active: true },
]
const products = [
  {
    id: 1,
    sku: 'P-01',
    name: 'Desk notebook',
    category_id: 1,
    base_uom: 'unit',
    reorder_level: '5.0000',
    is_active: true,
  },
]
let signedIn = true
let currentUser = manager
let failLogin = false
let failApproval = false
let failLedger = false
let status = 'submitted'
let requests: { path: string; method: string; headers: Headers; body: unknown }[] = []
const transfer = () => ({
  id: 1,
  number: 'TRF-2026-000001',
  from_store_id: 1,
  to_store_id: 2,
  status,
  created_by: 1,
  submitted_by: 1,
  approved_by: status === 'approved_posted' ? 2 : null,
  created_at: '2026-11-01T10:00:00Z',
  submitted_at: '2026-11-01T10:01:00Z',
  posted_at: status === 'approved_posted' ? '2026-11-01T11:00:00Z' : null,
  rejection_reason: null,
  lines: [{ id: 1, product_id: 1, quantity: '20.0000' }],
})
const response = (data: unknown, code = 200) =>
  new Response(JSON.stringify(data), {
    status: code,
    headers: { 'Content-Type': 'application/json' },
  })
beforeEach(() => {
  vi.restoreAllMocks()
  sessionStorage.clear()
  window.history.replaceState({}, '', '/')
  signedIn = true
  currentUser = manager
  failLogin = false
  failApproval = false
  failLedger = false
  status = 'submitted'
  requests = []
  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: string | URL | Request, init?: RequestInit) => {
      const path = String(input)
      const method = init?.method ?? 'GET'
      requests.push({
        path,
        method,
        headers: new Headers(init?.headers),
        body: init?.body ? JSON.parse(String(init.body)) : null,
      })
      const envelope = (data: unknown) =>
        response({
          data,
          meta: {
            request_id: 'test-id',
            current_page: 1,
            last_page: 1,
            total: Array.isArray(data) ? data.length : 1,
            per_page: 25,
          },
        })
      const error = (code: number, message: string) =>
        response(
          { error: { code: 'TEST_ERROR', message, details: [] }, meta: { request_id: 'test-id' } },
          code,
        )
      if (path === '/sanctum/csrf-cookie') return new Response(null, { status: 204 })
      if (path.endsWith('/auth/me')) return signedIn ? envelope(currentUser) : error(401, 'Sign in')
      if (path.endsWith('/auth/login')) {
        if (failLogin) return error(422, 'The provided credentials are incorrect.')
        signedIn = true
        return envelope(currentUser)
      }
      if (path.endsWith('/auth/logout')) {
        signedIn = false
        return new Response(null, { status: 204 })
      }
      if (path.includes('/inventory/ledger') && failLedger) return error(419, 'Expired')
      if (path.endsWith('/transfers/1/approve')) {
        if (failApproval) {
          failApproval = false
          return error(503, 'Inventory is busy. Retry with the same idempotency key.')
        }
        status = 'approved_posted'
        return envelope(transfer())
      }
      if (path.endsWith('/transfers/1')) return envelope(transfer())
      if (path.startsWith('/api/v1/products')) return envelope(products)
      if (path.startsWith('/api/v1/stores')) return envelope(stores)
      if (path.startsWith('/api/v1/categories'))
        return envelope([{ id: 1, name: 'Office', is_active: true }])
      if (path.startsWith('/api/v1/transfers')) return envelope([transfer()])
      return envelope([])
    }),
  )
})
describe('Inventory workspace', () => {
  it('signs in and out with role-aware navigation', async () => {
    signedIn = false
    const interaction = userEvent.setup()
    render(<App />)
    await interaction.type(await screen.findByLabelText('Email address'), manager.email)
    await interaction.type(screen.getByLabelText('Password'), 'test-password')
    await interaction.click(screen.getByRole('button', { name: 'Sign in' }))
    expect(await screen.findByRole('heading', { name: 'Inventory overview' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Approvals' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Team & access' })).not.toBeInTheDocument()
    await interaction.click(screen.getByRole('button', { name: 'Sign out' }))
    expect(await screen.findByRole('heading', { name: 'Welcome back' })).toBeInTheDocument()
  })
  it('shows login errors without opening the workspace', async () => {
    signedIn = false
    failLogin = true
    render(<App />)
    const interaction = userEvent.setup()
    await interaction.type(await screen.findByLabelText('Email address'), manager.email)
    await interaction.type(screen.getByLabelText('Password'), 'incorrect')
    await interaction.click(screen.getByRole('button', { name: 'Sign in' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('credentials are incorrect')
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument()
  })
  it('blocks an auditor from the approval page and hides write navigation', async () => {
    currentUser = {
      ...manager,
      roles: ['Auditor'],
      permissions: [
        'products.view',
        'categories.view',
        'stores.view',
        'inventory.view',
        'transfers.view',
      ],
    }
    window.history.replaceState({}, '', '/approvals')
    render(<App />)
    expect(await screen.findByText('Access restricted')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Approvals' })).not.toBeInTheDocument()
    expect(requests.some((r) => r.path.includes('approval_inbox'))).toBe(false)
  })
  it('validates transfer routes and decimal quantities before sending a draft', async () => {
    window.history.replaceState({}, '', '/transfers')
    render(<App />)
    const interaction = userEvent.setup()
    await interaction.click(await screen.findByRole('button', { name: 'New transfer' }))
    await interaction.selectOptions(screen.getByLabelText('Source store'), '1')
    await interaction.selectOptions(screen.getByLabelText('Destination store'), '1')
    await interaction.selectOptions(screen.getByLabelText('Product 1'), '1')
    await interaction.clear(screen.getByLabelText('Quantity 1'))
    await interaction.type(screen.getByLabelText('Quantity 1'), '0')
    await interaction.click(screen.getByRole('button', { name: 'Save draft' }))
    expect(await screen.findByText('Choose a different destination.')).toBeInTheDocument()
    expect(screen.getByText('Quantity must be greater than zero.')).toBeInTheDocument()
    expect(requests.filter((r) => r.method === 'POST')).toHaveLength(0)
  })
  it('reuses the same approval key after a retryable failure and never posts optimistically', async () => {
    currentUser = { ...manager, id: 2, name: 'Eligible Approver' }
    failApproval = true
    window.history.replaceState({}, '', '/transfers/1')
    render(<App />)
    const interaction = userEvent.setup()
    await interaction.click(await screen.findByRole('button', { name: 'Approve & post' }))
    await interaction.click(screen.getByRole('button', { name: 'Confirm approve' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Inventory is busy')
    expect(status).toBe('submitted')
    await interaction.click(screen.getByRole('button', { name: 'Retry with same request' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    const attempts = requests.filter((r) => r.path.endsWith('/approve'))
    expect(attempts).toHaveLength(2)
    expect(attempts[0].headers.get('Idempotency-Key')).toBeTruthy()
    expect(attempts[0].headers.get('Idempotency-Key')).toBe(
      attempts[1].headers.get('Idempotency-Key'),
    )
    expect(await screen.findByText('approved posted')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Approve & post' })).not.toBeInTheDocument()
  })
  it('keeps the creator from seeing approve and displays the restriction', async () => {
    window.history.replaceState({}, '', '/transfers/1')
    render(<App />)
    expect(
      await screen.findByText('A different authorized person must approve this transfer.'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Approve & post' })).not.toBeInTheDocument()
  })
  it('clears the workspace on session expiry', async () => {
    render(<App />)
    const interaction = userEvent.setup()
    await screen.findByRole('heading', { name: 'Inventory overview' })
    failLedger = true
    await interaction.click(
      within(screen.getByRole('navigation')).getByRole('link', { name: 'Stock ledger' }),
    )
    expect(await screen.findByRole('heading', { name: 'Welcome back' })).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('session expired')
  })
})
