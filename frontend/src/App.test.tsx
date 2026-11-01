import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { api, ApiError } from './api'
vi.mock('./api', async (original) => {
  const actual = await original<typeof import('./api')>()
  return { ...actual, api: { me: vi.fn(), stores: vi.fn(), login: vi.fn(), logout: vi.fn() } }
})
const user = { id: 1, name: 'Demo Keeper', email: 'keeper@stockflow.test', is_active: true, roles: ['Store Keeper'], permissions: ['stores.view'], store_ids: [1] }
const meta = { request_id: 'test-request' }
beforeEach(() => {
  vi.resetAllMocks()
  vi.mocked(api.me).mockRejectedValue(new ApiError(401, 'Sign in'))
  vi.mocked(api.stores).mockResolvedValue({ data: [{ id: 1, code: 'MAIN', name: 'Main Warehouse', location_label: null, is_active: true }], meta })
})
describe('Foundation', () => {
  it('signs in, displays assigned stores, and signs out', async () => {
    vi.mocked(api.login).mockResolvedValue({ data: user, meta })
    vi.mocked(api.logout).mockResolvedValue(undefined)
    render(<App />)
    const interaction = userEvent.setup()
    await interaction.type(await screen.findByLabelText('Email address'), user.email)
    await interaction.type(screen.getByLabelText('Password'), 'test-password')
    await interaction.click(screen.getByRole('button', { name: 'Sign in' }))
    expect(await screen.findByText('Main Warehouse')).toBeInTheDocument()
    expect(api.login).toHaveBeenCalledWith(user.email, 'test-password')
    await interaction.click(screen.getByRole('button', { name: 'Sign out' }))
    expect(await screen.findByRole('heading', { name: 'Welcome back' })).toBeInTheDocument()
  })
  it('shows failed login without entering the workspace', async () => {
    vi.mocked(api.login).mockRejectedValue(new ApiError(422, 'The provided credentials are incorrect.'))
    render(<App />)
    const interaction = userEvent.setup()
    await interaction.type(await screen.findByLabelText('Email address'), user.email)
    await interaction.type(screen.getByLabelText('Password'), 'wrong')
    await interaction.click(screen.getByRole('button', { name: 'Sign in' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('credentials are incorrect')
    expect(screen.queryByRole('button', { name: 'Sign out' })).not.toBeInTheDocument()
  })
  it('does not request stores without the permission', async () => {
    vi.mocked(api.me).mockResolvedValue({ data: { ...user, permissions: [] }, meta })
    render(<App />)
    expect(await screen.findByText('Your role does not include store access.')).toBeInTheDocument()
    expect(api.stores).not.toHaveBeenCalled()
  })
})
