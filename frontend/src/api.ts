import type { Envelope, Store, User } from './types'
export type { Store, User } from './types'
export class ApiError extends Error {
  status: number
  requestId?: string
  code?: string
  details: {
    field?: string
    message?: string
    available?: string
    required?: string
    product_id?: number
  }[]
  constructor(
    status: number,
    message: string,
    requestId?: string,
    code?: string,
    details: ApiError['details'] = [],
  ) {
    super(message)
    this.status = status
    this.requestId = requestId
    this.code = code
    this.details = details
  }
}
export async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = document.cookie
    .split('; ')
    .find((part) => part.startsWith('XSRF-TOKEN='))
    ?.slice(11)
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (options.body) headers.set('Content-Type', 'application/json')
  if (token) headers.set('X-XSRF-TOKEN', decodeURIComponent(token))
  const response = await fetch(path, { ...options, headers, credentials: 'same-origin' })
  if (response.status === 204) return undefined as T
  const body = await response.json().catch(() => null)
  if (!response.ok) {
    if ([401, 419].includes(response.status) && !path.includes('/auth/'))
      window.dispatchEvent(new Event('stockflow:session-expired'))
    const message =
      response.status === 422 && body?.error?.details?.[0]?.message
        ? body.error.details[0].message
        : (body?.error?.message ?? 'Unable to complete the request. Please try again.')
    throw new ApiError(
      response.status,
      message,
      body?.meta?.request_id,
      body?.error?.code,
      body?.error?.details ?? [],
    )
  }
  return body as T
}
export const api = {
  me: (signal?: AbortSignal) => request<Envelope<User>>('/api/v1/auth/me', { signal }),
  stores: (signal?: AbortSignal) =>
    request<Envelope<Store[]>>('/api/v1/stores?per_page=100', { signal }),
  list: <T>(path: string, params = '', signal?: AbortSignal) =>
    request<Envelope<T[]>>('/api/v1/' + path + (params ? '?' + params : ''), { signal }),
  get: <T>(path: string, signal?: AbortSignal) =>
    request<Envelope<T>>('/api/v1/' + path, { signal }),
  mutate: <T>(path: string, method: string, data: unknown = {}, key?: string) =>
    request<Envelope<T>>('/api/v1/' + path, {
      method,
      body: JSON.stringify(data),
      headers: key ? { 'Idempotency-Key': key } : undefined,
    }),
  login: async (email: string, password: string) => {
    await request<void>('/sanctum/csrf-cookie')
    return request<Envelope<User>>('/api/v1/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })
  },
  logout: () => request<void>('/api/v1/auth/logout', { method: 'POST' }),
}
