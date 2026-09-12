import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createHttp, HttpError } from './http'

function respond(body: unknown, init: ResponseInit = {}): Response {
  return new Response(body === undefined ? null : JSON.stringify(body), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
    ...init,
  })
}

describe('createHttp', () => {
  beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
  })

  it('puts the base path in front of a relative path and leaves an absolute one alone', async () => {
    const fetch = vi.fn().mockImplementation(() => respond({ data: [] }))
    const http = createHttp({ baseUrl: '/api/cms', fetch })

    await http.get('pages')
    await http.get('/pages')
    await http.get('https://elsewhere.test/pages')

    expect(fetch.mock.calls.map((call) => call[0])).toEqual([
      '/api/cms/pages',
      '/api/cms/pages',
      'https://elsewhere.test/pages',
    ])
  })

  it('leaves out query parameters that have no value', async () => {
    const fetch = vi.fn().mockImplementation(() => respond({ data: [] }))
    const http = createHttp({ baseUrl: '/api', fetch })

    await http.get('pages', { query: { page: 2, search: '', parent: null, trashed: undefined } })

    expect(fetch.mock.calls[0]?.[0]).toBe('/api/pages?page=2&search=')
  })

  it('fetches the CSRF cookie before an unsafe request and sends the token', async () => {
    const fetch = vi.fn().mockImplementation((url: string) => {
      if (url === '/sanctum/csrf-cookie') {
        document.cookie = 'XSRF-TOKEN=a%20token; path=/'

        return Promise.resolve(new Response(null, { status: 204 }))
      }

      return Promise.resolve(respond({ data: {} }))
    })

    const http = createHttp({ baseUrl: '/api', fetch })
    await http.post('login', { email: 'a@b.test' })

    expect(fetch.mock.calls[0]?.[0]).toBe('/sanctum/csrf-cookie')

    const headers = fetch.mock.calls[1]?.[1]?.headers as Record<string, string>
    // Decoded: Laravel writes it URL-encoded and expects it back as it was written.
    expect(headers['X-XSRF-TOKEN']).toBe('a token')
  })

  it('does not go for a CSRF cookie before a read', async () => {
    const fetch = vi.fn().mockImplementation(() => respond({ data: [] }))
    const http = createHttp({ baseUrl: '/api', fetch })

    await http.get('pages')

    expect(fetch).toHaveBeenCalledTimes(1)
  })

  it('refreshes a stale CSRF token once and tries again', async () => {
    // 419 is what Laravel answers after a session is regenerated, which is exactly what
    // signing in does — so the second attempt is the difference between working and not.
    let attempts = 0

    const fetch = vi.fn().mockImplementation((url: string) => {
      if (url === '/sanctum/csrf-cookie') {
        document.cookie = `XSRF-TOKEN=token-${attempts}; path=/`

        return Promise.resolve(new Response(null, { status: 204 }))
      }

      attempts += 1

      return Promise.resolve(
        attempts === 1
          ? respond({ message: 'CSRF token mismatch.' }, { status: 419 })
          : respond({ data: { ok: true } }),
      )
    })

    const http = createHttp({ baseUrl: '/api', fetch })

    await expect(http.post('login', {})).resolves.toEqual({ data: { ok: true } })
    expect(attempts).toBe(2)
  })

  it('gives up after one retry rather than looping', async () => {
    const fetch = vi
      .fn()
      .mockImplementation((url: string) =>
        Promise.resolve(
          url === '/sanctum/csrf-cookie'
            ? new Response(null, { status: 204 })
            : respond({ message: 'CSRF token mismatch.' }, { status: 419 }),
        ),
      )

    const http = createHttp({ baseUrl: '/api', fetch })

    await expect(http.post('login', {})).rejects.toMatchObject({ status: 419 })
  })

  it('turns a 422 into the errors a form can show', async () => {
    const fetch = vi.fn().mockImplementation((url: string) =>
      Promise.resolve(
        url === '/sanctum/csrf-cookie'
          ? new Response(null, { status: 204 })
          : respond(
              {
                message: 'The given data was invalid.',
                errors: { email: ['These do not match.'] },
              },
              { status: 422 },
            ),
      ),
    )

    const http = createHttp({ baseUrl: '/api', fetch })

    await expect(http.post('login', {})).rejects.toSatisfy((error: HttpError) => {
      expect(error).toBeInstanceOf(HttpError)
      expect(error.isValidation).toBe(true)
      expect(error.errors).toEqual({ email: ['These do not match.'] })

      return true
    })
  })

  it('reads how long to wait out of a throttled answer', async () => {
    const fetch = vi.fn().mockImplementation((url: string) =>
      Promise.resolve(
        url === '/sanctum/csrf-cookie'
          ? new Response(null, { status: 204 })
          : new Response(JSON.stringify({ message: 'Too many attempts.' }), {
              status: 429,
              headers: { 'Content-Type': 'application/json', 'Retry-After': '43' },
            }),
      ),
    )

    const http = createHttp({ baseUrl: '/api', fetch })

    await expect(http.post('login', {})).rejects.toMatchObject({
      isThrottled: true,
      retryAfter: 43,
    })
  })

  it('tells the panel when the server says nobody is signed in', async () => {
    const onUnauthenticated = vi.fn()
    const fetch = vi
      .fn()
      .mockImplementation(() => respond({ message: 'Unauthenticated.' }, { status: 401 }))

    const http = createHttp({ baseUrl: '/api', fetch, onUnauthenticated })

    await expect(http.get('manifest')).rejects.toMatchObject({ status: 401 })
    expect(onUnauthenticated).toHaveBeenCalledOnce()
  })

  it('survives an answer that is not JSON', async () => {
    const fetch = vi.fn().mockResolvedValue(new Response('<html>502</html>', { status: 502 }))

    const http = createHttp({ baseUrl: '/api', fetch })

    await expect(http.get('manifest')).rejects.toMatchObject({ status: 502 })
  })

  it('returns nothing for a 204 rather than trying to read a body', async () => {
    const fetch = vi
      .fn()
      .mockImplementation(() => Promise.resolve(new Response(null, { status: 204 })))

    const http = createHttp({ baseUrl: '/api', fetch })

    await expect(http.post('logout')).resolves.toBeUndefined()
  })
})
