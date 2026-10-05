const TOKEN_KEY = 'supli.token'
const COMPANY_KEY = 'supli.company'

export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>
  readonly data: unknown

  constructor(message: string, status: number, errors: Record<string, string[]> = {}, data: unknown = null) {
    super(message)
    this.status = status
    this.errors = errors
    this.data = data
  }

  fieldError(field?: string): string | undefined {
    if (field) return this.errors[field]?.[0]
    return Object.values(this.errors)[0]?.[0]
  }

  messages(): string[] {
    const messages = Object.values(this.errors).flat()
    return messages.length > 0 ? messages : [this.message]
  }
}

function read(key: string): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

function write(key: string, value: string | null): void {
  try {
    if (value === null) localStorage.removeItem(key)
    else localStorage.setItem(key, value)
  } catch {
  }
}

export const session = {
  token: () => read(TOKEN_KEY),
  setToken: (token: string | null) => write(TOKEN_KEY, token),
  companyId: () => read(COMPANY_KEY),
  setCompanyId: (id: number | null) => write(COMPANY_KEY, id === null ? null : String(id)),
}

type Query = Record<string, string | number | boolean | string[] | null | undefined>

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  query?: Query
  signal?: AbortSignal
}

export function toQueryString(query: Query = {}): string {
  const params = new URLSearchParams()

  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '' || value === false) continue
    if (Array.isArray(value)) value.forEach((v) => params.append(`${key}[]`, v))
    else params.set(key, value === true ? '1' : String(value))
  }

  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

export async function api<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  const token = session.token()
  const companyId = session.companyId()

  if (token) headers.Authorization = `Bearer ${token}`
  if (companyId) headers['X-Company-Id'] = companyId

  let body: BodyInit | undefined
  if (options.body instanceof FormData) {
    body = options.body
  } else if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json'
    body = JSON.stringify(options.body)
  }

  const response = await fetch(`/api/v1${path}${toQueryString(options.query)}`, {
    method: options.method ?? 'GET',
    headers,
    body,
    signal: options.signal,
  })

  if (response.status === 204) return undefined as T

  const data = await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401) {
      session.setToken(null)
      window.dispatchEvent(new Event('auth:expired'))
    }

    const payload = (data ?? {}) as { message?: string; errors?: Record<string, string[]> }
    throw new ApiError(payload.message || response.statusText || 'Request failed', response.status, payload.errors, data)
  }

  return data as T
}
