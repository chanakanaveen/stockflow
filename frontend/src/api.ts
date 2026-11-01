export interface User {
  id: number
  name: string
  email: string
  is_active: boolean
  roles: string[]
  permissions: string[]
  store_ids: number[]
}
export interface Store { id: number; code: string; name: string; location_label: string | null; is_active: boolean }
interface Envelope<T> { data: T; meta: { request_id: string } }

export class ApiError extends Error {
  status: number
  requestId?: string
  constructor(status: number, message: string, requestId?: string) { super(message); this.status = status; this.requestId = requestId }
}
async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))?.slice(11)
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (options.body) headers.set('Content-Type', 'application/json')
  if (token) headers.set('X-XSRF-TOKEN', decodeURIComponent(token))
  const response = await fetch(path, { ...options, headers, credentials: 'same-origin' })
  if (response.status === 204) return undefined as T
  const body = await response.json().catch(() => null)
  if (!response.ok) {
    const message = response.status === 422 && body?.error?.details?.length
      ? body.error.details[0].message
      : body?.error?.message ?? 'Unable to complete the request. Please try again.'
    throw new ApiError(response.status, message, body?.meta?.request_id)
  }
  return body as T
}
export const api = {
  me: (signal?: AbortSignal) => request<Envelope<User>>('/api/v1/auth/me', { signal }),
  stores: (signal?: AbortSignal) => request<Envelope<Store[]>>('/api/v1/stores?per_page=100', { signal }),
  login: async (email: string, password: string) => {
    await request<void>('/sanctum/csrf-cookie')
    return request<Envelope<User>>('/api/v1/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) })
  },
  logout: () => request<void>('/api/v1/auth/logout', { method: 'POST' }),
}
