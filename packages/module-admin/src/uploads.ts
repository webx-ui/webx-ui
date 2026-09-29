import { computed, getCurrentScope, onScopeDispose, readonly, ref, type Ref } from 'vue'
import { useAdmin, type AdminContext } from './admin'
import { toError, type Http, type SendOptions } from './http'

/**
 * Large files a piece at a time, through the panel's own four requests (§4 of the video spec):
 *
 *     POST   uploads        { name, size, type, fingerprint, purpose } → { id, offset, size, chunk_size }
 *     HEAD   uploads/{id}   → Upload-Offset
 *     PATCH  uploads/{id}   a piece, Upload-Offset: n → 204, Upload-Offset: n + length
 *     DELETE uploads/{id}
 *
 * The server holds the offset and the client follows it: a 409 names where the file really
 * ends, a HEAD asks after a dropped connection, and the same file chosen again after a reload
 * finds its session by the fingerprint. What the server cannot know is the web server's own
 * limit, so a 413 halves the piece — down to 256 KB — and the smaller piece stays for the rest
 * of the file.
 */

export type ChunkedUploadState = 'idle' | 'uploading' | 'paused' | 'offline' | 'done' | 'failed'

/** An upload that did not finish, as remembered in this browser for the next visit. */
export interface UnfinishedUpload {
  fingerprint: string
  purpose: string
  name: string
  size: number
  offset: number
  /** Milliseconds since the epoch, of the last piece that landed. */
  updatedAt: number
}

export interface ChunkedUploadOptions {
  /** The panel to talk to; `useAdmin()` when left out. */
  admin?: Pick<AdminContext, 'http' | 'apiPath'>
  /** Where unfinished uploads are remembered; `localStorage` when left out, `null` for nowhere. */
  storage?: Storage | null
  /** How many times a piece lost to the network is sent again before the upload fails. */
  retries?: number
  /** The wait before the first of those, doubled for each after it. */
  retryDelay?: number
}

export interface ChunkedUpload {
  readonly state: Readonly<Ref<ChunkedUploadState>>
  /** 0 to 1. */
  readonly progress: Readonly<Ref<number>>
  /** Bytes the server has. */
  readonly uploaded: Readonly<Ref<number>>
  readonly total: Readonly<Ref<number>>
  /** Bytes a second, over the last few seconds; 0 until there is something to measure. */
  readonly speed: Readonly<Ref<number>>
  /** Seconds left at that speed, or null while there is no speed to go by. */
  readonly remaining: Readonly<Ref<number | null>>
  /** The session's id, which is what the module the file was for claims it by. */
  readonly id: Readonly<Ref<string | null>>
  readonly error: Readonly<Ref<Error | null>>
  /**
   * Send a file. Resolves with the session's id once the whole of it is on the server, with
   * null when it is cancelled; rejects when it fails. A pause does neither — the same promise
   * resolves once the upload is resumed and finished.
   */
  start(file: File, purpose: string): Promise<string | null>
  pause(): void
  /** Carry on after a pause, going offline or a failure; the promise of the upload. */
  resume(): Promise<string | null>
  /** Stop and throw away what the server has. */
  cancel(): Promise<void>
}

/** The smallest piece the client falls back to on a 413 (the server never advises less). */
export const MIN_CHUNK = 256 * 1024

const STORAGE_KEY = 'webx:uploads'
const SPEED_WINDOW = 8000

/**
 * What identifies a file without reading it: the same name, size and date of change is taken
 * for the same file. Cheap on purpose — hashing gigabytes to find out where to resume would take
 * longer than a good part of the upload.
 */
export function uploadFingerprint(file: Pick<File, 'name' | 'size' | 'lastModified'>): string {
  return `${file.name}|${file.size}|${file.lastModified}`
}

/**
 * The uploads this browser started and did not finish — what a panel offers to continue after a
 * reload ("choose the same file"). Empty wherever storage is refused.
 */
export function unfinishedUploads(
  purpose?: string,
  storage: Storage | null = defaultStorage(),
): UnfinishedUpload[] {
  return readAll(storage).filter((entry) => purpose === undefined || entry.purpose === purpose)
}

/** Stop offering to continue an upload — the person chose not to. */
export function forgetUnfinishedUpload(
  fingerprint: string,
  purpose: string,
  storage: Storage | null = defaultStorage(),
): void {
  writeAll(
    storage,
    readAll(storage).filter(
      (entry) => entry.fingerprint !== fingerprint || entry.purpose !== purpose,
    ),
  )
}

export function useChunkedUpload(options: ChunkedUploadOptions = {}): ChunkedUpload {
  const admin = options.admin ?? useAdmin()
  const storage = options.storage === undefined ? defaultStorage() : options.storage
  const retries = options.retries ?? 3
  const retryDelay = options.retryDelay ?? 1000
  const base = `${admin.apiPath}/uploads`

  const state = ref<ChunkedUploadState>('idle')
  const uploaded = ref(0)
  const total = ref(0)
  const speed = ref(0)
  const id = ref<string | null>(null)
  const error = ref<Error | null>(null)

  const progress = computed(() => (total.value > 0 ? Math.min(1, uploaded.value / total.value) : 0))
  const remaining = computed(() =>
    speed.value > 0 ? Math.ceil((total.value - uploaded.value) / speed.value) : null,
  )

  let file: File | null = null
  let purpose = ''
  let chunk = MIN_CHUNK
  // Every pause, cancel and trip offline moves this on; a loop that finds it moved stops.
  let generation = 0
  let controller: AbortController | null = null
  let samples: Array<{ at: number; bytes: number }> = []
  let settle: { resolve: (id: string | null) => void; reject: (error: Error) => void } | null = null
  let pending: Promise<string | null> | null = null

  function send(method: string, path: string, init: SendOptions = {}): Promise<Response> {
    return sendWith(admin.http, method, path, init)
  }

  function promise(): Promise<string | null> {
    if (pending === null) {
      pending = new Promise<string | null>((resolve, reject) => {
        settle = { resolve, reject }
      })
      // Handled here as well, so that a caller who only watches `state` does not get an
      // unhandled rejection; one who awaits it still sees the failure.
      pending.catch(() => undefined)
    }

    return pending
  }

  function finish(result: string | null, failure?: Error): void {
    const done = settle
    settle = null
    pending = null

    if (failure !== undefined) {
      done?.reject(failure)
    } else {
      done?.resolve(result)
    }
  }

  function fail(failure: unknown): void {
    error.value = failure instanceof Error ? failure : new Error(String(failure))
    state.value = 'failed'
    speed.value = 0
    finish(null, error.value)
  }

  function setOffset(offset: number): void {
    uploaded.value = Math.min(offset, total.value)

    const now = Date.now()
    samples.push({ at: now, bytes: uploaded.value })
    samples = samples.filter((sample) => now - sample.at <= SPEED_WINDOW)

    const first = samples[0]
    const seconds = first === undefined ? 0 : (now - first.at) / 1000
    speed.value = first !== undefined && seconds > 0 ? (uploaded.value - first.bytes) / seconds : 0

    if (file !== null) {
      remember(file, purpose, uploaded.value)
    }
  }

  function remember(target: File, forPurpose: string, offset: number): void {
    const fingerprint = uploadFingerprint(target)
    const others = readAll(storage).filter(
      (entry) => entry.fingerprint !== fingerprint || entry.purpose !== forPurpose,
    )

    writeAll(storage, [
      ...others,
      {
        fingerprint,
        purpose: forPurpose,
        name: target.name,
        size: target.size,
        offset,
        updatedAt: Date.now(),
      },
    ])
  }

  function forget(): void {
    if (file !== null) {
      forgetUnfinishedUpload(uploadFingerprint(file), purpose, storage)
    }
  }

  function stop(): void {
    generation += 1
    controller?.abort()
    controller = null
    speed.value = 0
    samples = []
  }

  async function start(target: File, forPurpose: string): Promise<string | null> {
    if (state.value === 'uploading' || state.value === 'paused' || state.value === 'offline') {
      throw new Error('This upload is still going; cancel it before starting another.')
    }

    stop()
    file = target
    purpose = forPurpose
    id.value = null
    error.value = null
    uploaded.value = 0
    total.value = target.size
    state.value = 'uploading'

    const result = promise()
    const token = generation

    try {
      const body = await admin.http.post<{
        data: { id: string; offset: number; size: number; chunk_size: number }
      }>(base, {
        name: target.name,
        size: target.size,
        type: target.type,
        fingerprint: uploadFingerprint(target),
        purpose: forPurpose,
      })

      if (token !== generation) {
        return result
      }

      id.value = body.data.id
      chunk = Math.max(MIN_CHUNK, body.data.chunk_size)
      setOffset(body.data.offset)
      void run()
    } catch (failure) {
      if (token === generation) {
        fail(failure)
      }
    }

    return result
  }

  /** Where the server says the file ends — after a pause, a trip offline or a lost piece. */
  async function sync(): Promise<boolean> {
    const response = await send('HEAD', `${base}/${id.value}`)

    if (!response.ok) {
      fail(await toError(response))

      return false
    }

    const offset = headerOffset(response)

    if (offset !== null) {
      setOffset(offset)
    }

    return true
  }

  async function run(): Promise<void> {
    const token = generation
    let failures = 0

    state.value = 'uploading'

    while (token === generation && file !== null && id.value !== null) {
      if (uploaded.value >= total.value) {
        state.value = 'done'
        speed.value = 0
        forget()
        finish(id.value)

        return
      }

      const from = uploaded.value
      const to = Math.min(total.value, from + chunk)
      controller = new AbortController()

      let response: Response

      try {
        response = await send('PATCH', `${base}/${id.value}`, {
          body: file.slice(from, to),
          headers: {
            'Upload-Offset': String(from),
            'Content-Type': 'application/offset+octet-stream',
          },
          signal: controller.signal,
        })
      } catch (failure) {
        // A pause or a cancel aborted it; whoever did that has already said what happens next.
        if (token !== generation) {
          return
        }

        if (isOffline()) {
          goOffline()

          return
        }

        failures += 1

        if (failures > retries) {
          fail(failure)

          return
        }

        await wait(retryDelay * 2 ** (failures - 1))

        if (token !== generation) {
          return
        }

        // Some of the piece may have landed before the connection went; the server knows.
        try {
          if (!(await sync())) {
            return
          }
        } catch {
          // Still unreachable; the next attempt counts as another failure.
        }

        continue
      }

      if (token !== generation) {
        return
      }

      if (response.ok) {
        failures = 0
        setOffset(headerOffset(response) ?? to)

        continue
      }

      if (response.status === 409) {
        const offset = headerOffset(response)

        if (offset !== null) {
          setOffset(offset)

          continue
        }
      }

      // The web server's limit, which the server behind it could not know about.
      if (response.status === 413 && chunk > MIN_CHUNK) {
        chunk = Math.max(MIN_CHUNK, Math.floor(chunk / 2))

        continue
      }

      fail(await toError(response))

      return
    }
  }

  function pause(): void {
    if (state.value !== 'uploading') {
      return
    }

    stop()
    state.value = 'paused'
  }

  function goOffline(): void {
    stop()
    state.value = 'offline'
  }

  async function resume(): Promise<string | null> {
    if (state.value === 'uploading' || state.value === 'done' || state.value === 'idle') {
      return pending ?? Promise.resolve(state.value === 'done' ? id.value : null)
    }

    if (file === null) {
      return Promise.resolve(null)
    }

    // A failure before the session existed leaves nothing to resume; start it again instead.
    if (id.value === null) {
      state.value = 'idle'

      return start(file, purpose)
    }

    stop()
    error.value = null
    state.value = 'uploading'

    const result = promise()
    const token = generation

    try {
      if ((await sync()) && token === generation) {
        void run()
      }
    } catch (failure) {
      if (token === generation) {
        if (isOffline()) {
          goOffline()
        } else {
          fail(failure)
        }
      }
    }

    return result
  }

  async function cancel(): Promise<void> {
    const session = id.value

    stop()
    forget()
    state.value = 'idle'
    uploaded.value = 0
    id.value = null
    finish(null)

    if (session !== null) {
      try {
        await send('DELETE', `${base}/${session}`)
      } catch {
        // Gone or unreachable: the server sweeps what nobody finishes.
      }
    }
  }

  function onOffline(): void {
    if (state.value === 'uploading') {
      goOffline()
    }
  }

  function onOnline(): void {
    if (state.value === 'offline') {
      void resume()
    }
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('offline', onOffline)
    window.addEventListener('online', onOnline)
  }

  if (getCurrentScope() !== undefined) {
    onScopeDispose(() => {
      if (typeof window !== 'undefined') {
        window.removeEventListener('offline', onOffline)
        window.removeEventListener('online', onOnline)
      }

      // Nothing is left to show the progress; the fingerprint stays, so it can be continued.
      pause()
    })
  }

  return {
    state: readonly(state),
    progress,
    uploaded: readonly(uploaded),
    total: readonly(total),
    speed: readonly(speed),
    remaining,
    id: readonly(id),
    error: readonly(error) as Readonly<Ref<Error | null>>,
    start,
    pause,
    resume,
    cancel,
  }
}

/**
 * The client's own `send` when it has one, so the CSRF cookie and the standing headers are the
 * same as everywhere else; a bare fetch for a hand-made client that does not.
 */
async function sendWith(
  http: Http,
  method: string,
  path: string,
  init: SendOptions,
): Promise<Response> {
  if (http.send !== undefined) {
    return http.send(method, path, init)
  }

  return globalThis.fetch(path, {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', ...init.headers },
    body: init.body ?? undefined,
    signal: init.signal,
  })
}

function headerOffset(response: Response): number | null {
  const value = response.headers.get('Upload-Offset')

  if (value === null || !/^\d+$/.test(value)) {
    return null
  }

  return Number.parseInt(value, 10)
}

function isOffline(): boolean {
  return typeof navigator !== 'undefined' && navigator.onLine === false
}

function wait(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

function defaultStorage(): Storage | null {
  try {
    return typeof localStorage === 'undefined' ? null : localStorage
  } catch {
    // A browser that blocks site data throws on the very access.
    return null
  }
}

function readAll(storage: Storage | null): UnfinishedUpload[] {
  if (storage === null) {
    return []
  }

  try {
    const parsed: unknown = JSON.parse(storage.getItem(STORAGE_KEY) ?? '[]')

    return Array.isArray(parsed) ? (parsed as UnfinishedUpload[]) : []
  } catch {
    return []
  }
}

function writeAll(storage: Storage | null, entries: UnfinishedUpload[]): void {
  if (storage === null) {
    return
  }

  try {
    if (entries.length === 0) {
      storage.removeItem(STORAGE_KEY)
    } else {
      storage.setItem(STORAGE_KEY, JSON.stringify(entries))
    }
  } catch {
    // Full or refused: continuing after a reload is a courtesy, not something to fail over.
  }
}
