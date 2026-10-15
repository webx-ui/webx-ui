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

  it('sends the standing headers as they are at the time of the request', async () => {
    // Read each time rather than captured once: the panel's language changes while it runs,
    // and the server decides which language to write a 422 in from this.
    const fetch = vi.fn().mockImplementation(() => respond({ data: [] }))
    let locale = 'en'
    const http = createHttp({ fetch, headers: () => ({ 'X-Webx-Locale': locale }) })

    await http.get('/pages')
    locale = 'uk'
    await http.get('/pages')
    // A caller who names a header for one request still wins.
    await http.get('/pages', { headers: { 'X-Webx-Locale': 'ru' } })

    const sent = fetch.mock.calls.map(
      (call) =>
        (call[1] as RequestInit & { headers: Record<string, string> }).headers['X-Webx-Locale'],
    )

    expect(sent).toEqual(['en', 'uk', 'ru'])
  })

  it('leaves out query parameters that have no value', async () => {
    const fetch = vi.fn().mockImplementation(() => respond({ data: [] }))
    const http = createHttp({ baseUrl: '/api', fetch })

    await http.get('pages', { query: { page: 2, search: '', parent: null, trashed: undefined } })

    expect(fetch.mock.calls[0]?.[0]).toBe('/api/pages?page=2&search=')
  })

  it('fetches the CSRF cookie before an unsafe request and sends the token', async () => {
    const fetch = vi.fn().mockImplementation((url: string) => {
      if (url === '/api/cms/auth/csrf-cookie') {
        document.cookie = 'XSRF-TOKEN=a%20token; path=/'

        return Promise.resolve(new Response(null, { status: 204 }))
      }

      return Promise.resolve(respond({ data: {} }))
    })

    const http = createHttp({ baseUrl: '/api', fetch })
    await http.post('login', { email: 'a@b.test' })

    expect(fetch.mock.calls[0]?.[0]).toBe('/api/cms/auth/csrf-cookie')

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
      if (url === '/api/cms/auth/csrf-cookie') {
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
          url === '/api/cms/auth/csrf-cookie'
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
        url === '/api/cms/auth/csrf-cookie'
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
        url === '/api/cms/auth/csrf-cookie'
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

  describe('methodOverride', () => {
    /** Answers the CSRF cookie and records everything else. */
    function recording() {
      const fetch = vi.fn().mockImplementation((url: string) => {
        if (url === '/api/cms/auth/csrf-cookie') {
          document.cookie = 'XSRF-TOKEN=token; path=/'

          return Promise.resolve(new Response(null, { status: 204 }))
        }

        return Promise.resolve(respond({ data: {} }))
      })

      const sent = () =>
        fetch.mock.calls
          .filter((call) => call[0] !== '/api/cms/auth/csrf-cookie')
          .map((call) => {
            const init = call[1] as RequestInit & { headers: Record<string, string> }

            return { url: call[0] as string, ...init }
          })

      return { fetch, sent }
    }

    async function everyMethod(http: ReturnType<typeof createHttp>): Promise<void> {
      await http.get('pages')
      await http.post('pages', { title: 'A' })
      await http.put('pages/1', { title: 'B' })
      await http.patch('pages/1', { title: 'C' })
      await http.delete('pages/1')
    }

    it('changes nothing when it is not asked for', async () => {
      const { fetch, sent } = recording()
      await everyMethod(createHttp({ baseUrl: '/api', fetch }))

      expect(sent().map((request) => request.method)).toEqual([
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
      ])
      expect(sent().every((request) => !('X-HTTP-Method-Override' in request.headers))).toBe(true)
      expect(sent()[2]?.body).toBe(JSON.stringify({ title: 'B' }))
    })

    it.each([true, 'header'] as const)(
      'sends PUT, PATCH and DELETE as POST with the header (%s)',
      async (methodOverride) => {
        const { fetch, sent } = recording()
        await everyMethod(createHttp({ baseUrl: '/api', fetch, methodOverride }))

        const requests = sent()

        expect(requests.map((request) => request.method)).toEqual([
          'GET',
          'POST',
          'POST',
          'POST',
          'POST',
        ])
        expect(requests.map((request) => request.headers['X-HTTP-Method-Override'])).toEqual([
          undefined,
          undefined,
          'PUT',
          'PATCH',
          'DELETE',
        ])
        // The body and the address are what they would have been.
        expect(requests[2]?.body).toBe(JSON.stringify({ title: 'B' }))
        expect(requests[4]?.body).toBeUndefined()
        expect(requests[4]?.url).toBe('/api/pages/1')
        // The CSRF token follows the method that was meant, and a read still carries none.
        expect(requests.map((request) => request.headers['X-XSRF-TOKEN'])).toEqual([
          undefined,
          'token',
          'token',
          'token',
          'token',
        ])
      },
    )

    it('puts _method in the body when asked for the field', async () => {
      const { fetch, sent } = recording()
      await everyMethod(createHttp({ baseUrl: '/api', fetch, methodOverride: 'field' }))

      const requests = sent()

      expect(requests.map((request) => request.method)).toEqual([
        'GET',
        'POST',
        'POST',
        'POST',
        'POST',
      ])
      expect(requests.map((request) => request.body)).toEqual([
        undefined,
        JSON.stringify({ title: 'A' }),
        JSON.stringify({ title: 'B', _method: 'PUT' }),
        JSON.stringify({ title: 'C', _method: 'PATCH' }),
        // DELETE has no body of its own, so it gets one to carry the field.
        JSON.stringify({ _method: 'DELETE' }),
      ])
      expect(requests[4]?.headers['Content-Type']).toBe('application/json')
      expect(requests.every((request) => !('X-HTTP-Method-Override' in request.headers))).toBe(true)
      expect(
        requests.slice(1).every((request) => request.headers['X-XSRF-TOKEN'] === 'token'),
      ).toBe(true)
    })

    it('adds the field to a form, and to the query when the body is bytes', async () => {
      const { fetch, sent } = recording()
      const http = createHttp({ baseUrl: '/api', fetch, methodOverride: 'field' })

      const form = new FormData()
      form.append('file', 'x')
      await http.send!('PUT', 'media', { body: form })
      await http.send!('PATCH', 'uploads/1', { body: new Blob(['chunk']), query: { part: 2 } })

      const [first, second] = sent()

      expect(first?.method).toBe('POST')
      expect((first?.body as FormData).get('_method')).toBe('PUT')
      expect((first?.body as FormData).get('file')).toBe('x')
      expect(first?.headers['Content-Type']).toBeUndefined()

      expect(second?.method).toBe('POST')
      expect(second?.url).toBe('/api/uploads/1?part=2&_method=PATCH')
      expect(second?.body).toBeInstanceOf(Blob)
    })

    it('keeps the override when a stale token is refreshed and the request goes again', async () => {
      let first = true
      const fetch = vi.fn().mockImplementation((url: string) => {
        if (url === '/api/cms/auth/csrf-cookie') {
          document.cookie = 'XSRF-TOKEN=token; path=/'

          return Promise.resolve(new Response(null, { status: 204 }))
        }

        if (first) {
          first = false

          return Promise.resolve(new Response(null, { status: 419 }))
        }

        return Promise.resolve(respond({ data: {} }))
      })

      const http = createHttp({ baseUrl: '/api', fetch, methodOverride: 'header' })
      await http.delete('pages/1')

      const retried = fetch.mock.calls.at(-1)?.[1] as RequestInit & {
        headers: Record<string, string>
      }

      expect(retried.method).toBe('POST')
      expect(retried.headers['X-HTTP-Method-Override']).toBe('DELETE')
    })
  })
})
