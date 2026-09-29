import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createHttp } from './http'
import {
  MIN_CHUNK,
  forgetUnfinishedUpload,
  unfinishedUploads,
  uploadFingerprint,
  useChunkedUpload,
} from './uploads'

/**
 * The server of §4 of the video spec, in memory: sessions by fingerprint, pieces appended at
 * the offset it holds, 409 with the real one otherwise. `limit` plays the web server in front
 * of it, which answers 413 to a body over its size without the server behind ever seeing it.
 */
function createServer(options: { chunkSize?: number; limit?: number } = {}) {
  const sessions = new Map<string, { fingerprint: string; size: number; bytes: string }>()
  const pieces: number[] = []
  let count = 0
  let beforePatch: ((id: string) => Response | void | Promise<Response | void>) | null = null

  const fetch = vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
    const url = String(input)
    const method = init.method ?? 'GET'

    if (url.endsWith('/csrf-cookie')) {
      return new Response(null, { status: 204 })
    }

    if (method === 'POST' && url === '/api/cms/uploads') {
      const body = JSON.parse(String(init.body)) as { fingerprint: string; size: number }
      const existing = [...sessions.entries()].find(
        ([, one]) => one.fingerprint === body.fingerprint,
      )
      const id = existing?.[0] ?? `00000000-0000-4000-8000-${String(++count).padStart(12, '0')}`

      if (existing === undefined) {
        sessions.set(id, { fingerprint: body.fingerprint, size: body.size, bytes: '' })
      }

      const offset = sessions.get(id)!.bytes.length

      return json(
        { data: { id, offset, size: body.size, chunk_size: options.chunkSize ?? MIN_CHUNK } },
        existing === undefined ? 201 : 200,
      )
    }

    const id = url.split('/').pop()!
    const session = sessions.get(id)

    if (session === undefined) {
      return json({ message: 'That upload no longer exists.' }, 404)
    }

    if (method === 'HEAD') {
      return new Response(null, {
        status: 200,
        headers: { 'Upload-Offset': String(session.bytes.length) },
      })
    }

    if (method === 'DELETE') {
      sessions.delete(id)

      return new Response(null, { status: 204 })
    }

    if (method === 'PATCH') {
      const early = await beforePatch?.(id)

      if (early instanceof Response) {
        return early
      }

      const piece = init.body as Blob

      if (options.limit !== undefined && piece.size > options.limit) {
        return new Response('<html>413 Request Entity Too Large</html>', { status: 413 })
      }

      const offset = Number((init.headers as Record<string, string>)['Upload-Offset'])

      if (offset !== session.bytes.length) {
        return new Response(null, {
          status: 409,
          headers: { 'Upload-Offset': String(session.bytes.length) },
        })
      }

      pieces.push(piece.size)
      session.bytes += await piece.text()

      return new Response(null, {
        status: 204,
        headers: { 'Upload-Offset': String(session.bytes.length) },
      })
    }

    return json({ message: 'Unexpected request.' }, 500)
  })

  return {
    fetch,
    sessions,
    pieces,
    admin: {
      http: createHttp({ fetch, csrfUrl: '/api/cms/auth/csrf-cookie' }),
      apiPath: '/api/cms',
    },
    onPatch(hook: typeof beforePatch) {
      beforePatch = hook
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

/** What a fetch whose signal was aborted rejects with. */
function aborted(): DOMException {
  return new DOMException('The operation was aborted.', 'AbortError')
}

function clip(size: number, name = 'clip.mp4'): File {
  let text = ''

  for (let index = 0; text.length < size; index += 1) {
    text += `${index % 10}`
  }

  return new File([text.slice(0, size)], name, { type: 'video/mp4', lastModified: 1727600000000 })
}

describe('useChunkedUpload', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('sends the file in the pieces the server advises and resolves with the session', async () => {
    const server = createServer()
    const upload = useChunkedUpload({ admin: server.admin })
    const file = clip(MIN_CHUNK * 2 + 100)

    const id = await upload.start(file, 'catalog.video')

    expect(id).toBe(upload.id.value)
    expect(upload.state.value).toBe('done')
    expect(upload.progress.value).toBe(1)
    expect(server.pieces).toEqual([MIN_CHUNK, MIN_CHUNK, 100])
    expect(server.sessions.get(id!)!.bytes).toBe(await file.text())
    // Finished is not unfinished: nothing to offer after a reload.
    expect(unfinishedUploads()).toEqual([])
  })

  it('halves the piece on a 413 down to the floor and keeps the smaller piece', async () => {
    const server = createServer({ chunkSize: MIN_CHUNK * 4, limit: MIN_CHUNK + 10 })
    const upload = useChunkedUpload({ admin: server.admin })
    const file = clip(MIN_CHUNK * 3)

    await upload.start(file, 'catalog.video')

    expect(upload.state.value).toBe('done')
    // 1 MB and 512 KB were refused once each; after that every piece is 256 KB.
    expect(server.requests('PATCH')).toHaveLength(2 + 3)
    expect(server.pieces).toEqual([MIN_CHUNK, MIN_CHUNK, MIN_CHUNK])
  })

  it('fails when even the smallest piece is too large', async () => {
    const server = createServer({ chunkSize: MIN_CHUNK * 2, limit: 1000 })
    const upload = useChunkedUpload({ admin: server.admin })

    await expect(upload.start(clip(MIN_CHUNK * 3), 'catalog.video')).rejects.toMatchObject({
      status: 413,
    })
    expect(upload.state.value).toBe('failed')
  })

  it("carries on from the server's offset after a 409", async () => {
    const server = createServer()
    const upload = useChunkedUpload({ admin: server.admin })
    const file = clip(MIN_CHUNK + 50)
    const whole = await file.text()
    let first = true

    // Another tab got the first 50 bytes in before this one sent anything.
    server.onPatch((id) => {
      if (first) {
        first = false
        server.sessions.get(id)!.bytes = whole.slice(0, 50)
      }
    })

    const id = await upload.start(file, 'catalog.video')

    expect(upload.state.value).toBe('done')
    expect(server.sessions.get(id!)!.bytes).toBe(whole)
    expect(server.pieces).toEqual([MIN_CHUNK])
  })

  it('pauses when the browser goes offline and carries on when it is back', async () => {
    const server = createServer()
    const upload = useChunkedUpload({ admin: server.admin })
    const online = vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(true)
    const file = clip(MIN_CHUNK * 3)
    let dropped = false

    server.onPatch(() => {
      if (!dropped && server.pieces.length === 1) {
        dropped = true
        online.mockReturnValue(false)
        window.dispatchEvent(new Event('offline'))

        throw new TypeError('Failed to fetch')
      }
    })

    const result = upload.start(file, 'catalog.video')

    await vi.waitFor(() => expect(upload.state.value).toBe('offline'))
    expect(upload.uploaded.value).toBe(MIN_CHUNK)
    // What is left is remembered for a reload.
    expect(unfinishedUploads('catalog.video')).toMatchObject([
      { name: 'clip.mp4', size: MIN_CHUNK * 3, offset: MIN_CHUNK },
    ])

    online.mockReturnValue(true)
    window.dispatchEvent(new Event('online'))

    const id = await result

    expect(upload.state.value).toBe('done')
    // It asked the server where to resume rather than trusting its own count.
    expect(server.requests('HEAD')).toHaveLength(1)
    expect(server.sessions.get(id!)!.bytes).toBe(await file.text())
  })

  it('sends a piece lost to the network again after asking where the file ends', async () => {
    const server = createServer()
    const upload = useChunkedUpload({ admin: server.admin, retryDelay: 1 })
    let lost = false

    server.onPatch(() => {
      if (!lost) {
        lost = true

        throw new TypeError('Failed to fetch')
      }
    })

    await upload.start(clip(MIN_CHUNK + 1), 'catalog.video')

    expect(upload.state.value).toBe('done')
    expect(server.requests('HEAD')).toHaveLength(1)
  })

  it('pauses and resumes on request with the same promise', async () => {
    const server = createServer()
    const upload = useChunkedUpload({ admin: server.admin })
    const file = clip(MIN_CHUNK * 3)

    server.onPatch(() => {
      if (server.pieces.length === 1 && upload.state.value === 'uploading') {
        upload.pause()

        throw aborted()
      }
    })

    const result = upload.start(file, 'catalog.video')

    await vi.waitFor(() => expect(upload.state.value).toBe('paused'))
    server.onPatch(null)

    const resumed = upload.resume()
    const id = await result

    expect(await resumed).toBe(id)
    expect(upload.state.value).toBe('done')
    expect(server.sessions.get(id!)!.bytes).toBe(await file.text())
  })

  it('cancels: the server forgets the session and the promise resolves with null', async () => {
    const server = createServer()
    const upload = useChunkedUpload({ admin: server.admin })

    server.onPatch(() => {
      if (server.pieces.length === 1 && upload.state.value === 'uploading') {
        void upload.cancel()

        throw aborted()
      }
    })

    const result = await upload.start(clip(MIN_CHUNK * 3), 'catalog.video')

    expect(result).toBeNull()
    expect(upload.state.value).toBe('idle')
    await vi.waitFor(() => expect(server.requests('DELETE')).toHaveLength(1))
    expect(server.sessions.size).toBe(0)
    expect(unfinishedUploads()).toEqual([])
  })

  it('continues the same file after a reload from where the server has it', async () => {
    const server = createServer()
    const file = clip(MIN_CHUNK * 2)
    const first = useChunkedUpload({ admin: server.admin })

    server.onPatch(() => {
      if (server.pieces.length === 1 && first.state.value === 'uploading') {
        first.pause()

        throw aborted()
      }
    })

    void first.start(file, 'catalog.video')
    await vi.waitFor(() => expect(first.state.value).toBe('paused'))
    server.onPatch(null)

    // A new page: the only thing left is what this browser remembered.
    const offered = unfinishedUploads('catalog.video')
    expect(offered).toMatchObject([{ fingerprint: uploadFingerprint(file), offset: MIN_CHUNK }])

    const second = useChunkedUpload({ admin: server.admin })
    const id = await second.start(clip(MIN_CHUNK * 2), 'catalog.video')

    expect(server.pieces).toEqual([MIN_CHUNK, MIN_CHUNK])
    expect(server.sessions.get(id!)!.bytes).toBe(await file.text())

    forgetUnfinishedUpload(offered[0]!.fingerprint, 'catalog.video')
    expect(unfinishedUploads()).toEqual([])
  })

  it('uploads all the same where storage is refused', async () => {
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('Blocked', 'SecurityError')
    })

    const server = createServer()
    const upload = useChunkedUpload({ admin: server.admin })

    await upload.start(clip(MIN_CHUNK + 1), 'catalog.video')

    expect(upload.state.value).toBe('done')
  })

  it('fails with the server’s words when the session has gone', async () => {
    const server = createServer()
    const upload = useChunkedUpload({ admin: server.admin })

    server.onPatch(() => json({ message: 'That upload no longer exists.' }, 404))

    await expect(upload.start(clip(MIN_CHUNK), 'catalog.video')).rejects.toThrow(
      'That upload no longer exists.',
    )
    expect(upload.error.value?.message).toBe('That upload no longer exists.')
  })
})
