import {
  computed,
  getCurrentScope,
  onScopeDispose,
  readonly,
  ref,
  type ComputedRef,
  type Ref,
} from 'vue'
import {
  forgetUnfinishedUpload,
  unfinishedUploads,
  uploadFingerprint,
  useAdmin,
  useChunkedUpload,
  useErrorText,
  useTranslate,
  type AdminContext,
  type ChunkedUpload,
  type UnfinishedUpload,
} from '@webx-ui/module-admin'
import { toast } from '@webx-ui/core'
import { createCatalogApi } from './api'
import type { ProductImage } from './types'
import { posterName, probeVideo, VideoUnreadable, type ProbeOptions } from './video'

/**
 * Video files going into the gallery (§7 of the video spec): the frame, the picture, the file a
 * piece at a time, and the request that puts the two together.
 *
 * Kept by the product editor rather than by the gallery field. The field lives on a tab, and a tab
 * nobody is looking at is taken down — with the chunked upload inside it, which pauses when its
 * scope dies. Up here the upload carries on while the rest of the form is being filled in.
 *
 * One file at a time, as there is one chunked upload: several at once share one connection
 * anyway, and one bar that moves says more than three that crawl.
 */

export const VIDEO_PURPOSE = 'catalog.video'

export type VideoJobStage = 'waiting' | 'poster' | 'uploading' | 'attaching' | 'failed'

export interface VideoJob {
  key: number
  file: File
  /** The product it goes to, as it was when the file was chosen. */
  product: number
  /** The picture it goes onto; `null` until the frame has been uploaded as one. */
  image: number | null
  /** The job made that picture itself — a cancel takes it away again. */
  created: boolean
  /** The frame, until the picture is on the server and the row shows its own. */
  poster: string | null
  duration: number | null
  stage: VideoJobStage
  /** Why it stopped, in words; only in `failed`. */
  error: string | null
  /** The browser could not open the file, so there is no frame to make a picture of. */
  unreadable: boolean
}

/** An upload this browser left unfinished for this product, with the picture it was for. */
export interface UnfinishedVideo extends UnfinishedUpload {
  image: number | null
}

export interface GalleryVideo {
  readonly jobs: Readonly<Ref<readonly VideoJob[]>>
  /** The one upload that moves — its state, progress, speed and time left. */
  readonly upload: ChunkedUpload
  /** Something is on its way to the server; leaving now loses the rest of it. */
  readonly busy: ComputedRef<boolean>
  /** What a reload interrupted on this product, to be continued by choosing the file again. */
  readonly unfinished: ComputedRef<UnfinishedVideo[]>
  /** Onto `image`, or a new row made of its own frame when there is none. */
  add(file: File, image?: number | null): void
  pause(): void
  resume(): void
  /** Try a failed one again: a file the server has part of continues from there. */
  retry(job: VideoJob): void
  cancel(job: VideoJob): Promise<void>
  /** Take a failed one off the list. */
  dismiss(job: VideoJob): void
  /** Stop offering to continue an upload. */
  forget(entry: UnfinishedVideo): void
}

export interface GalleryVideoOptions {
  product: Ref<number | null>
  images: Ref<ProductImage[]>
  admin?: AdminContext
  storage?: Storage | null
  probe?: ProbeOptions
}

const TARGETS_KEY = 'webx:catalog-video-targets'

type Targets = Record<string, { product: number; image: number | null }>

export function createGalleryVideo(options: GalleryVideoOptions): GalleryVideo {
  const admin = options.admin ?? useAdmin()
  const api = createCatalogApi(admin)
  const storage = options.storage === undefined ? defaultStorage() : options.storage
  const upload = useChunkedUpload({ admin, storage })
  const t = useTranslate('webx-catalog')
  const message = useErrorText()

  const jobs = ref<VideoJob[]>([])
  // Bumped whenever storage changes under the list of unfinished uploads.
  const stored = ref(0)
  let next = 1
  let running = false
  // The job whose file the chunked upload holds, which is the only one a cancel can stop.
  let uploading: number | null = null

  const busy = computed(() => jobs.value.some((job) => job.stage !== 'failed'))

  const unfinished = computed<UnfinishedVideo[]>(() => {
    void stored.value
    const product = options.product.value
    const targets = readTargets(storage)
    const going = new Set(jobs.value.map((job) => uploadFingerprint(job.file)))

    if (product === null) return []

    return unfinishedUploads(VIDEO_PURPOSE, storage)
      .filter((entry) => targets[entry.fingerprint]?.product === product)
      .filter((entry) => !going.has(entry.fingerprint))
      .map((entry) => ({ ...entry, image: targets[entry.fingerprint]?.image ?? null }))
  })

  function patch(key: number, change: Partial<VideoJob>): void {
    jobs.value = jobs.value.map((job) => (job.key === key ? { ...job, ...change } : job))
  }

  function find(key: number): VideoJob | undefined {
    return jobs.value.find((job) => job.key === key)
  }

  function setTarget(file: File, product: number, image: number | null): void {
    writeTargets(storage, {
      ...readTargets(storage),
      [uploadFingerprint(file)]: { product, image },
    })
    stored.value += 1
  }

  function dropTarget(file: File): void {
    const targets = readTargets(storage)
    delete targets[uploadFingerprint(file)]
    writeTargets(storage, targets)
    stored.value += 1
  }

  function replace(image: ProductImage): void {
    options.images.value = options.images.value.map((one) => (one.id === image.id ? image : one))
  }

  function add(file: File, image: number | null = null): void {
    const product = options.product.value

    if (product === null) return

    jobs.value = [
      ...jobs.value,
      {
        key: next++,
        file,
        product,
        image,
        created: false,
        poster: null,
        duration: null,
        stage: 'waiting',
        error: null,
        unreadable: false,
      },
    ]

    void pump()
  }

  /** The jobs one after another, until none is waiting. */
  async function pump(): Promise<void> {
    if (running) return

    running = true

    try {
      let job = jobs.value.find((one) => one.stage === 'waiting')

      while (job !== undefined) {
        await work(job.key)
        job = jobs.value.find((one) => one.stage === 'waiting')
      }
    } finally {
      running = false
    }
  }

  async function work(key: number): Promise<void> {
    let job = find(key)

    if (job === undefined) return

    try {
      if (job.image === null) {
        patch(key, { stage: 'poster' })

        let probe

        try {
          probe = await probeVideo(job.file, { ...options.probe, frame: true })
        } catch (failure) {
          if (!(failure instanceof VideoUnreadable)) throw failure

          patch(key, { stage: 'failed', unreadable: true, error: t('panel.video-unreadable') })

          return
        }

        if (find(key) === undefined) return

        const poster = URL.createObjectURL(probe.frame!)
        patch(key, { poster, duration: probe.duration })

        try {
          const picture = await api.addImage(
            job.product,
            new File([probe.frame!], posterName(job.file), { type: 'image/jpeg' }),
          )

          // Cancelled while the picture was going up: it is nobody's now.
          if (find(key) === undefined) {
            await api.removeImage(job.product, picture.id).catch(() => undefined)

            return
          }

          if (options.product.value === job.product) {
            options.images.value = [...options.images.value, picture]
          }

          patch(key, { image: picture.id, created: true })
        } finally {
          URL.revokeObjectURL(poster)
          patch(key, { poster: null })
        }
      } else if (job.duration === null) {
        // The length is all a picture that is already there needs; a file the browser cannot
        // open still goes up — the server judges it by what is in it.
        const probe = await probeVideo(job.file, options.probe).catch(() => null)

        patch(key, { duration: probe?.duration ?? null })
      }

      job = find(key)

      if (job === undefined || job.image === null) return

      patch(key, { stage: 'uploading', error: null })
      setTarget(job.file, job.product, job.image)

      uploading = key
      const id = await upload.start(job.file, VIDEO_PURPOSE)

      // Cancelled: `cancel` has already tidied up after it.
      if (id === null || find(key) === undefined) return

      dropTarget(job.file)
      patch(key, { stage: 'attaching' })

      const answer = await api.attachVideo(job.product, job.image, {
        upload: id,
        duration: job.duration,
      })

      if ('queued' in answer) return

      if (options.product.value === job.product) replace(answer)

      jobs.value = jobs.value.filter((one) => one.key !== key)
      toast.success(t('panel.video-attached'))
    } catch (failure) {
      if (find(key) !== undefined) {
        patch(key, { stage: 'failed', error: message(failure) })
      }
    }
  }

  function retry(job: VideoJob): void {
    if (find(job.key)?.stage !== 'failed') return

    patch(job.key, { stage: 'waiting', error: null, unreadable: false })
    void pump()
  }

  async function cancel(job: VideoJob): Promise<void> {
    const current = find(job.key)

    if (current === undefined) return

    jobs.value = jobs.value.filter((one) => one.key !== job.key)
    dropTarget(current.file)

    if (uploading === current.key) {
      uploading = null
      await upload.cancel()
    }

    // The picture was made of the video's own frame for this video; without it, it is not
    // something anybody chose to put in the gallery.
    if (current.created && current.image !== null) {
      try {
        await api.removeImage(current.product, current.image)

        if (options.product.value === current.product) {
          options.images.value = options.images.value.filter((one) => one.id !== current.image)
        }
      } catch (failure) {
        toast.danger(message(failure))
      }
    }
  }

  function dismiss(job: VideoJob): void {
    jobs.value = jobs.value.filter((one) => one.key !== job.key)
  }

  function forget(entry: UnfinishedVideo): void {
    forgetUnfinishedUpload(entry.fingerprint, VIDEO_PURPOSE, storage)

    const targets = readTargets(storage)
    delete targets[entry.fingerprint]
    writeTargets(storage, targets)
    stored.value += 1
  }

  /* The browser's own question on the way out: it cannot wait, so all it can do is ask. */
  function guard(event: BeforeUnloadEvent): void {
    if (busy.value) event.preventDefault()
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', guard)
  }

  if (getCurrentScope() !== undefined) {
    onScopeDispose(() => {
      if (typeof window !== 'undefined') window.removeEventListener('beforeunload', guard)
    })
  }

  return {
    jobs: readonly(jobs) as Readonly<Ref<readonly VideoJob[]>>,
    upload,
    busy,
    unfinished,
    add,
    pause: () => upload.pause(),
    resume: () => void upload.resume(),
    retry,
    cancel,
    dismiss,
    forget,
  }
}

function defaultStorage(): Storage | null {
  try {
    return typeof localStorage === 'undefined' ? null : localStorage
  } catch {
    return null
  }
}

function readTargets(storage: Storage | null): Targets {
  if (storage === null) return {}

  try {
    const parsed: unknown = JSON.parse(storage.getItem(TARGETS_KEY) ?? '{}')

    return parsed !== null && typeof parsed === 'object' ? (parsed as Targets) : {}
  } catch {
    return {}
  }
}

function writeTargets(storage: Storage | null, targets: Targets): void {
  if (storage === null) return

  try {
    if (Object.keys(targets).length === 0) {
      storage.removeItem(TARGETS_KEY)
    } else {
      storage.setItem(TARGETS_KEY, JSON.stringify(targets))
    }
  } catch {
    // Refused or full: offering to continue after a reload is a courtesy.
  }
}
