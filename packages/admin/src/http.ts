/**
 * The panel's way of talking to its own backend.
 *
 * Small on purpose — it is not a general HTTP library, it is the handful of conventions
 * `webx-ui/admin` and `webx-ui/module-auth` answer with: a session cookie rather than a token,
 * 422 for a bad form, 401 for a stranger, 429 with `Retry-After` when somebody is guessing.
 */

export interface HttpOptions {
  /** Prefixed to every relative path, e.g. `/api/cms`. */
  baseUrl?: string
  /** Where to fetch the CSRF cookie from before an unsafe request. */
  csrfUrl?: string
  /** Called whenever the server answers 401, however deep in the app the call was. */
  onUnauthenticated?: () => void
  /** Swappable for tests. */
  fetch?: typeof globalThis.fetch
}

export interface RequestOptions {
  /** Query parameters; `undefined` and `null` are left out rather than sent empty. */
  query?: Record<string, string | number | boolean | null | undefined>
  headers?: Record<string, string>
  signal?: AbortSignal
}

/**
 * Everything that went wrong, in the shape the panel needs to react:
 * `errors` goes straight into `WxForm`, `retryAfter` into "try again in a moment".
 */
export class HttpError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly errors: Record<string, string[]> = {},
    readonly retryAfter: number | null = null,
    readonly body: unknown = null,
  ) {
    super(message)
    this.name = 'HttpError'
  }

  /** A failed form rather than a failed request. */
  get isValidation(): boolean {
    return this.status === 422
  }

  get isUnauthenticated(): boolean {
    return this.status === 401
  }

  get isThrottled(): boolean {
    return this.status === 429
  }
}

export interface Http {
  get<T>(path: string, options?: RequestOptions): Promise<T>
  post<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T>
  put<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T>
  patch<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T>
  delete<T>(path: string, options?: RequestOptions): Promise<T>
}

const UNSAFE = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

export function createHttp(options: HttpOptions = {}): Http {
  const baseUrl = (options.baseUrl ?? '').replace(/\/$/, '')
  const csrfUrl = options.csrfUrl ?? '/sanctum/csrf-cookie'
  const doFetch = options.fetch ?? globalThis.fetch.bind(globalThis)
  const onUnauthenticated = options.onUnauthenticated

  let csrfFetched = false

  async function ensureCsrfCookie(force = false): Promise<void> {
    if (csrfFetched && !force && readCookie('XSRF-TOKEN') !== null) {
      return
    }

    await doFetch(csrfUrl, { credentials: 'same-origin' })
    csrfFetched = true
  }

  async function request<T>(
    method: string,
    path: string,
    body?: unknown,
    options: RequestOptions = {},
    retried = false,
  ): Promise<T> {
    const unsafe = UNSAFE.has(method)

    if (unsafe) {
      await ensureCsrfCookie()
    }

    const headers: Record<string, string> = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...options.headers,
    }

    if (unsafe) {
      const token = readCookie('XSRF-TOKEN')

      if (token !== null) {
        headers['X-XSRF-TOKEN'] = token
      }
    }

    if (body !== undefined) {
      headers['Content-Type'] = 'application/json'
    }

    const response = await doFetch(url(baseUrl, path, options.query), {
      method,
      credentials: 'same-origin',
      headers,
      signal: options.signal,
      body: body === undefined ? undefined : JSON.stringify(body),
    })

    // 419 is Laravel for "your CSRF token has gone stale", which happens after a session is
    // regenerated — at sign-in, most of all. Fetching a fresh one and going again is what a
    // person would do by reloading, without the reload.
    if (response.status === 419 && unsafe && !retried) {
      await ensureCsrfCookie(true)

      return request<T>(method, path, body, options, true)
    }

    if (response.status === 401) {
      onUnauthenticated?.()
    }

    if (!response.ok) {
      throw await toError(response)
    }

    if (response.status === 204) {
      return undefined as T
    }

    return (await response.json()) as T
  }

  return {
    get: (path, options) => request('GET', path, undefined, options),
    post: (path, body, options) => request('POST', path, body, options),
    put: (path, body, options) => request('PUT', path, body, options),
    patch: (path, body, options) => request('PATCH', path, body, options),
    delete: (path, options) => request('DELETE', path, undefined, options),
  }
}

async function toError(response: Response): Promise<HttpError> {
  let body: unknown = null

  try {
    body = await response.json()
  } catch {
    // A gateway or a fatal error answers with HTML; there is nothing to read out of it.
  }

  const payload = (body ?? {}) as { message?: unknown; errors?: unknown }
  const message =
    typeof payload.message === 'string' && payload.message !== ''
      ? payload.message
      : response.statusText || `Request failed with ${response.status}`

  const errors =
    payload.errors !== null && typeof payload.errors === 'object'
      ? (payload.errors as Record<string, string[]>)
      : {}

  const header = response.headers.get('Retry-After')
  const retryAfter = header === null ? null : Number.parseInt(header, 10)

  return new HttpError(
    message,
    response.status,
    errors,
    Number.isFinite(retryAfter) ? retryAfter : null,
    body,
  )
}

function url(baseUrl: string, path: string, query?: RequestOptions['query']): string {
  const absolute = /^https?:\/\//i.test(path)
  const full = absolute ? path : `${baseUrl}/${path.replace(/^\//, '')}`

  if (query === undefined) {
    return full
  }

  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null) {
      search.set(key, String(value))
    }
  }

  const serialised = search.toString()

  return serialised === '' ? full : `${full}${full.includes('?') ? '&' : '?'}${serialised}`
}

/**
 * Laravel writes the token URL-encoded, and it is read back the same way it was written.
 */
export function readCookie(name: string): string | null {
  if (typeof document === 'undefined') {
    return null
  }

  for (const part of document.cookie.split(';')) {
    const [key, ...rest] = part.trim().split('=')

    if (key === name) {
      return decodeURIComponent(rest.join('='))
    }
  }

  return null
}
