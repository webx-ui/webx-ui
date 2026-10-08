import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope } from 'vue'
import { createHttp, MIN_CHUNK } from '@webx-ui/module-admin'
import type { MediaFile } from './types'
import { MEDIA_UPLOAD_PURPOSE, useMediaUploads } from './uploading'

/**
 * The panel's upload protocol in memory, as far as the library needs it: sessions by
 * fingerprint, pieces appended at the offset the server holds, a 409 with the real one
 * otherwise, and a purpose that refuses what the library does not take.
 */
function createServer() {
  const sessions = new Map<string, { fingerprint: string; size: number; bytes: string }>()
  let count = 0
  let beforePatch: (() => void) | null = null
  let holding = false

  const fetch = vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
    const url = String(input)
    const method = init.method ?? 'GET'

    if (url.endsWith('/csrf-cookie')) {
      return new Response(null, { status: 204 })
    }

    if (method === 'POST' && url === '/api/cms/uploads') {
      const body = JSON.parse(String(init.body)) as {
        name: string
        fingerprint: string
        size: number
        purpose: string
      }

      if (body.purpose !== MEDIA_UPLOAD_PURPOSE || body.name.endsWith('.php')) {
        return json(
          { message: 'Only these can be uploaded here: jpg, png.', errors: { type: ['…'] } },
          422,
        )
      }

      const existing = [...sessions.entries()].find(
        ([, one]) => one.fingerprint === body.fingerprint,
      )
      const id = existing?.[0] ?? `upload-${++count}`

      if (existing === undefined) {
        sessions.set(id, { fingerprint: body.fingerprint, size: body.size, bytes: '' })
      }

      return json(
        {
          data: {
            id,
            offset: sessions.get(id)!.bytes.length,
            size: body.size,
            chunk_size: MIN_CHUNK,
          },
        },
        existing === undefined ? 201 : 200,
      )
    }

    const id = url.split('/').pop()!
    const session = sessions.get(id)

    if (session === undefined) {
      return json({ message: 'That upload no longer exists.' }, 404)
    }

    if (method === 'HEAD') {
      return new Response(null, { headers: { 'Upload-Offset': String(session.bytes.length) } })
    }

    if (method === 'DELETE') {
      sessions.delete(id)

      return new Response(null, { status: 204 })
    }

    // A PATCH. Held, it hangs until the client gives up on it — a slow line, as seen from the panel.
    if (holding) {
      await new Promise((_, reject) =>
        init.signal?.addEventListener('abort', () =>
          reject(new DOMException('The operation was aborted.', 'AbortError')),
        ),
      )
    }

    const piece = init.body as Blob
    const offset = Number((init.headers as Record<string, string>)['Upload-Offset'])
    const text = await piece.text()

    if (beforePatch) {
      const hook = beforePatch
      beforePatch = null
      // Half of the piece lands, then the connection goes: the server keeps what arrived.
      session.bytes += text.slice(0, Math.floor(text.length / 2))
      hook()
    }

    if (offset !== session.bytes.length) {
      return new Response(null, {
        status: 409,
        headers: { 'Upload-Offset': String(session.bytes.length) },
      })
    }

    session.bytes += text

    return new Response(null, {
      status: 204,
      headers: { 'Upload-Offset': String(session.bytes.length) },
    })
  })

  return {
    fetch,
    sessions,
    admin: {
      http: createHttp({ fetch, csrfUrl: '/api/cms/auth/csrf-cookie' }),
      apiPath: '/api/cms',
    },
    hold() {
      holding = true
    },
    dropNextPiece() {
      beforePatch = () => {
        throw new TypeError('Failed to fetch')
      }
    },
    requests(method: string) {
      return fetch.mock.calls.filter(([, init]) => (init?.method ?? 'GET') === method)
    },
  }
}

function json(body: unknown, status: number): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function photo(size: number, name = 'sofa.jpg'): File {
  let text = ''

  for (let index = 0; text.length < size; index += 1) {
    text += `${index % 10}`
  }

  return new File([text.slice(0, size)], name, { type: 'image/jpeg', lastModified: 1727600000000 })
}

function setup(server: ReturnType<typeof createServer>) {
  const finished: Array<{ directoryId: number; id: string; bytes: string }> = []
  const settled = vi.fn()

  const api = {
    finishUpload: vi.fn(async (directoryId: number, id: string) => {
      const session = server.sessions.get(id)!
      finished.push({ directoryId, id, bytes: session.bytes })
      server.sessions.delete(id)

      return { id: finished.length, name: id, duplicate: false } as unknown as MediaFile
    }),
  }

  const scope = effectScope()
  const uploads = scope.run(() =>
    useMediaUploads({ admin: server.admin, api, storage: null, retryDelay: 1, onSettled: settled }),
  )!

  return { uploads, api, finished, settled, scope }
}

async function settle(): Promise<void> {
  for (let round = 0; round < 40; round += 1) {
    await flushPromises()
    await new Promise((resolve) => setTimeout(resolve, 2))
  }
}

describe('useMediaUploads', () => {
  beforeEach(() => {
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(true)
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('sends every file in pieces and hands each to the library whole', async () => {
    const server = createServer()
    const { uploads, finished, settled } = setup(server)
    const large = photo(MIN_CHUNK * 2 + 100, 'large.jpg')
    const small = photo(1000, 'small.jpg')

    uploads.add([large, small], 7)
    await settle()

    expect(finished.map((one) => one.bytes.length).sort((a, b) => a - b)).toEqual([
      1000,
      MIN_CHUNK * 2 + 100,
    ])
    expect(finished.every((one) => one.directoryId === 7)).toBe(true)
    // Three pieces for the large one, one for the small: the same way for both.
    expect(server.requests('PATCH')).toHaveLength(4)
    expect(settled).toHaveBeenCalledTimes(1)
    expect(settled.mock.calls[0]![0]).toHaveLength(2)
    // What arrived is in the grid; the list of uploads has nothing left to show.
    expect(uploads.jobs.value).toEqual([])
  })

  it('carries on from where the server is after a dropped connection', async () => {
    const server = createServer()
    const { uploads, finished } = setup(server)
    const file = photo(MIN_CHUNK * 2 + 100)
    const expected = await file.text()

    server.dropNextPiece()
    uploads.add([file], 1)
    await settle()

    expect(finished).toHaveLength(1)
    // Nothing skipped and nothing twice: the half that landed is not sent again.
    expect(finished[0]!.bytes).toBe(expected)
    expect(server.requests('HEAD').length).toBeGreaterThan(0)
  })

  it('shows a refusal at the start, and sends nothing of the file', async () => {
    const server = createServer()
    const { uploads, api, settled } = setup(server)

    uploads.add([photo(1000, 'payload.php')], 1)
    await settle()

    expect(uploads.jobs.value).toHaveLength(1)
    expect(uploads.jobs.value[0]!.stage).toBe('failed')
    expect(String((uploads.jobs.value[0]!.error as Error).message)).toContain('jpg')
    // Refused for what it is: another try would be refused the same way.
    expect(uploads.jobs.value[0]!.retryable).toBe(false)
    expect(server.requests('PATCH')).toHaveLength(0)
    expect(api.finishUpload).not.toHaveBeenCalled()
    expect(settled).not.toHaveBeenCalled()
  })

  it('throws the session away when an upload is cancelled', async () => {
    const server = createServer()
    const { uploads, api } = setup(server)

    server.hold()
    uploads.add([photo(MIN_CHUNK * 4)], 1)
    await settle()

    expect(uploads.jobs.value[0]?.stage).toBe('uploading')

    await uploads.cancel(uploads.jobs.value[0]!.key)
    await settle()

    expect(uploads.jobs.value).toEqual([])
    expect(server.requests('DELETE')).toHaveLength(1)
    expect(server.sessions.size).toBe(0)
    expect(api.finishUpload).not.toHaveBeenCalled()
  })

  it('tries a failed file again when asked', async () => {
    const server = createServer()
    const { uploads, finished } = setup(server)
    const file = photo(MIN_CHUNK + 10)

    // More drops in a row than the upload retries: it fails, with the session kept.
    let drops = 0
    server.fetch.mockImplementationOnce(async () => {
      drops += 1
      throw new TypeError('Failed to fetch')
    })

    uploads.add([file], 1)
    await settle()

    expect(drops).toBe(1)
    expect(uploads.jobs.value[0]?.stage).toBe('failed')
    expect(uploads.jobs.value[0]?.retryable).toBe(true)

    uploads.retry(uploads.jobs.value[0]!.key)
    await settle()

    expect(finished).toHaveLength(1)
    expect(finished[0]!.bytes).toBe(await file.text())
  })
})
