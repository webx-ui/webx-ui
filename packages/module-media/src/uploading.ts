import {
  computed,
  effectScope,
  getCurrentScope,
  onScopeDispose,
  reactive,
  ref,
  watch,
  type EffectScope,
} from 'vue'
import {
  forgetUnfinishedUpload,
  unfinishedUploads,
  uploadFingerprint,
  useChunkedUpload,
  type AdminContext,
  type ChunkedUpload,
  type UnfinishedUpload,
} from '@webx-ui/module-admin'
import type { MediaApi } from './api'
import type { MediaFile } from './types'

/** What the library's uploads are registered as on the server (`FileStore::UPLOAD_PURPOSE`). */
export const MEDIA_UPLOAD_PURPOSE = 'media.library'

export type MediaUploadStage =
  'queued' | 'uploading' | 'paused' | 'offline' | 'storing' | 'done' | 'failed'

/** One file on its way into the library, as the list of uploads draws it. */
export interface MediaUploadJob {
  key: number
  name: string
  size: number
  directoryId: number
  stage: MediaUploadStage
  /** 0 to 1, of the bytes the server has. */
  progress: number
  error: unknown
  /**
   * Worth another try: the network or the server failed. A refusal — a type the library does
   * not take, a file too large — would only be refused again.
   */
  retryable: boolean
  /** What the library made of it, once it has. */
  file: MediaFile | null
}

export interface MediaUploadsOptions {
  admin: Pick<AdminContext, 'http' | 'apiPath'>
  api: Pick<MediaApi, 'finishUpload'>
  /** Files sent at once. A few, so a folder of small pictures is not one round trip each. */
  concurrency?: number
  /** Where unfinished uploads are remembered; `localStorage` when left out. */
  storage?: Storage | null
  /** Tries of a piece lost to the network before the file is marked failed. */
  retries?: number
  /** The wait before the first of those, doubled for each after it. */
  retryDelay?: number
  /** Everything queued has finished one way or the other; these are what was stored. */
  onSettled?: (stored: MediaFile[]) => void
}

interface Running {
  job: MediaUploadJob
  source: File
  upload: ChunkedUpload
  scope: EffectScope
  /** The server has a session for it, and a retry carries on from its offset. */
  resume: boolean
}

/**
 * Uploads into the library, every one a piece at a time through the panel's protocol
 * (`useChunkedUpload`) and then handed to the library by its id.
 *
 * Every file goes the same way whatever its size — a small one is one piece — so a dropped
 * connection costs the piece in flight and not the file, PHP's request limits stop mattering,
 * and there is one behaviour to learn. A piece lost to the network is sent again with a growing
 * wait, from the offset the server reports; a browser that goes offline waits for the network
 * and carries on by itself. The same file chosen again after a reload finds its session by its
 * fingerprint and continues where it stopped.
 */
export function useMediaUploads(options: MediaUploadsOptions) {
  const concurrency = Math.max(1, options.concurrency ?? 3)
  const storage = options.storage === undefined ? defaultStorage() : options.storage

  const jobs = ref<MediaUploadJob[]>([])
  const running = new Map<number, Running>()
  let next = 0
  let active = 0
  let stored: MediaFile[] = []

  // Bumped whenever storage changes under the list of unfinished uploads.
  const remembered = ref(0)

  /** Uploads this browser started for the library and did not finish, and which are not on screen. */
  const unfinished = computed<UnfinishedUpload[]>(() => {
    void remembered.value

    const going = new Set([...running.values()].map((one) => uploadFingerprint(one.source)))

    return unfinishedUploads(MEDIA_UPLOAD_PURPOSE, storage).filter(
      (entry) => !going.has(entry.fingerprint),
    )
  })

  const busy = computed(() =>
    jobs.value.some((job) => job.stage !== 'done' && job.stage !== 'failed'),
  )

  function add(files: File[], directoryId: number): void {
    for (const source of files) {
      const job = reactive<MediaUploadJob>({
        key: ++next,
        name: source.name,
        size: source.size,
        directoryId,
        stage: 'queued',
        progress: 0,
        error: null,
        retryable: false,
        file: null,
      })

      // Its own scope, so it can be let go of on its own: the chunked upload listens to the
      // network for as long as it lives.
      const scope = effectScope(true)
      const upload = scope.run(() => {
        const one = useChunkedUpload({
          admin: options.admin,
          storage,
          retries: options.retries ?? 6,
          retryDelay: options.retryDelay ?? 1000,
        })

        watch(one.progress, (progress) => {
          job.progress = progress
        })

        watch(one.state, (state) => {
          if (job.stage === 'uploading' || job.stage === 'paused' || job.stage === 'offline') {
            job.stage = state === 'paused' || state === 'offline' ? state : 'uploading'
          }
        })

        return one
      })!

      jobs.value.push(job)
      running.set(job.key, {
        job: jobs.value[jobs.value.length - 1]!,
        source,
        upload,
        scope,
        resume: false,
      })
    }

    pump()
  }

  function pump(): void {
    for (const one of running.values()) {
      if (active >= concurrency) {
        return
      }

      if (one.job.stage === 'queued') {
        void send(one)
      }
    }
  }

  async function send(one: Running): Promise<void> {
    const key = one.job.key

    active += 1
    one.job.stage = 'uploading'
    one.job.error = null

    try {
      const id = one.resume
        ? await one.upload.resume()
        : await one.upload.start(one.source, MEDIA_UPLOAD_PURPOSE)

      one.resume = false

      if (!running.has(key)) {
        return
      }

      // Cancelled: the upload has already told the server to throw away what it had.
      if (id === null) {
        drop(key)

        return
      }

      one.job.stage = 'storing'
      const file = await options.api.finishUpload(one.job.directoryId, id)

      one.job.file = file
      one.job.progress = 1
      one.job.stage = 'done'
      stored.push(file)
    } catch (error) {
      if (!running.has(key)) {
        return
      }

      one.job.stage = 'failed'
      one.job.error = error
      one.job.retryable = !refusal(error)
      // A failure on the way: the session is still there, and a retry asks it where it stopped.
      // A failure once the file was whole — refused by the library — has nothing to continue.
      one.resume = one.upload.state.value === 'failed' && one.upload.id.value !== null
    } finally {
      active -= 1
      remembered.value += 1
      pump()
      settle()
    }
  }

  function settle(): void {
    if (active > 0 || jobs.value.some((job) => job.stage === 'queued')) {
      return
    }

    // What was stored is in the grid now, which is the answer; what failed stays, with why.
    for (const job of jobs.value.filter((one) => one.stage === 'done')) {
      drop(job.key)
    }

    if (stored.length > 0) {
      const batch = stored
      stored = []
      options.onSettled?.(batch)
    }
  }

  function drop(key: number): void {
    const one = running.get(key)

    running.delete(key)
    one?.scope.stop()
    jobs.value = jobs.value.filter((job) => job.key !== key)
  }

  /** Stop a file and throw away what the server has of it. Nothing to stop once it is being stored. */
  async function cancel(key: number): Promise<void> {
    const one = running.get(key)

    if (!one || one.job.stage === 'storing') {
      return
    }

    const sending =
      one.job.stage === 'uploading' || one.job.stage === 'paused' || one.job.stage === 'offline'

    drop(key)
    await one.upload.cancel()
    remembered.value += 1

    if (!sending) {
      settle()
    }
  }

  function retry(key: number): void {
    const one = running.get(key)

    if (one?.job.stage === 'failed') {
      one.job.stage = 'queued'
      pump()
    }
  }

  /** Stop offering to continue an upload from an earlier visit. */
  function forget(entry: UnfinishedUpload): void {
    forgetUnfinishedUpload(entry.fingerprint, entry.purpose, storage)
    remembered.value += 1
  }

  if (getCurrentScope() !== undefined) {
    onScopeDispose(() => {
      // Each upload pauses as its scope goes; its fingerprint stays, so it can be continued by
      // choosing the same file again.
      for (const one of running.values()) {
        one.scope.stop()
      }

      running.clear()
    })
  }

  return { jobs, unfinished, busy, add, cancel, retry, forget }
}

export type MediaUploads = ReturnType<typeof useMediaUploads>

/** A 4xx that says no about the file itself, rather than about the moment it was sent in. */
function refusal(error: unknown): boolean {
  const status = (error as { status?: unknown } | null)?.status

  return (
    typeof status === 'number' &&
    status >= 400 &&
    status < 500 &&
    ![401, 408, 409, 419, 429].includes(status)
  )
}

function defaultStorage(): Storage | null {
  try {
    return typeof localStorage === 'undefined' ? null : localStorage
  } catch {
    return null
  }
}
