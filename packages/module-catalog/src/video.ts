import type { ProductImage } from './types'

/**
 * The browser half of videos in the gallery (§1.6, §7 of the video spec): the frame that becomes
 * the poster, and the checks made before a byte is sent.
 */

/** What the panel learns about a video file by opening it in the browser. */
export interface VideoProbe {
  /** The frame, as a JPEG; `null` when only the length was asked for. */
  frame: Blob | null
  /** Whole seconds, or `null` when the file does not say. */
  duration: number | null
  width: number
  height: number
}

/**
 * The pieces of the DOM the probe needs, handed in so that a test can stand them up: jsdom does
 * not decode video, so a probe against a real `<video>` there never gets past loading.
 */
export interface ProbeEnvironment {
  createVideo(): HTMLVideoElement
  createCanvas(): HTMLCanvasElement
  createUrl(file: Blob): string
  revokeUrl(url: string): void
}

export interface ProbeOptions {
  /** Take a frame as well as the length. */
  frame?: boolean
  /** How long to wait for the browser to open the file before giving up on it. */
  timeout?: number
  environment?: ProbeEnvironment
}

/** The browser could not open the file — mostly a codec it does not play. */
export class VideoUnreadable extends Error {
  constructor(reason: string) {
    super(reason)
    this.name = 'VideoUnreadable'
  }
}

const browser: ProbeEnvironment = {
  createVideo: () => document.createElement('video'),
  createCanvas: () => document.createElement('canvas'),
  createUrl: (file) => URL.createObjectURL(file),
  revokeUrl: (url) => URL.revokeObjectURL(url),
}

/** Where the frame is taken: a little in, as the very first frame is so often black. */
export function frameTime(duration: number): number {
  if (!Number.isFinite(duration) || duration <= 0) return 0

  return Math.min(1, duration / 10)
}

/**
 * Open a video file in the browser and read its length and, if asked, one frame of it.
 *
 * Read off the local file, before the upload, so a poster does not wait on gigabytes going up
 * (decision 6). Rejects with `VideoUnreadable` when the browser cannot open the file, which is
 * what the panel then says in words: add a picture and attach the video to it.
 */
export function probeVideo(file: Blob, options: ProbeOptions = {}): Promise<VideoProbe> {
  const environment = options.environment ?? browser
  const timeout = options.timeout ?? 15000
  const video = environment.createVideo()
  const url = environment.createUrl(file)

  video.muted = true
  video.playsInline = true
  video.preload = 'auto'

  return new Promise<VideoProbe>((resolve, reject) => {
    let settled = false
    const timer = setTimeout(() => fail('The browser took too long to open the video.'), timeout)

    function done(): void {
      settled = true
      clearTimeout(timer)
      video.removeAttribute('src')
      video.load?.()
      environment.revokeUrl(url)
    }

    function fail(reason: string): void {
      if (settled) return

      done()
      reject(new VideoUnreadable(reason))
    }

    function duration(): number | null {
      return Number.isFinite(video.duration) && video.duration > 0
        ? Math.round(video.duration)
        : null
    }

    video.addEventListener('error', () => fail('The browser cannot play this video.'))

    video.addEventListener('loadedmetadata', () => {
      if (settled) return

      if (!options.frame) {
        done()
        resolve({
          frame: null,
          duration: duration(),
          width: video.videoWidth,
          height: video.videoHeight,
        })

        return
      }

      // Sound only, or a codec that gives a length and no picture.
      if (video.videoWidth === 0 || video.videoHeight === 0) {
        fail('The video has no picture the browser can show.')

        return
      }

      video.currentTime = frameTime(video.duration)
    })

    video.addEventListener('seeked', () => {
      if (settled) return

      const canvas = environment.createCanvas()
      canvas.width = video.videoWidth
      canvas.height = video.videoHeight

      const context = canvas.getContext('2d')

      if (context === null) {
        fail('The browser gave no canvas to draw the frame on.')

        return
      }

      context.drawImage(video, 0, 0, canvas.width, canvas.height)

      const { videoWidth: width, videoHeight: height } = video
      const length = duration()

      canvas.toBlob(
        (blob) => {
          if (settled) return

          if (blob === null) {
            fail('The frame could not be turned into a picture.')

            return
          }

          done()
          resolve({ frame: blob, duration: length, width, height })
        },
        'image/jpeg',
        0.85,
      )
    })

    video.src = url
  })
}

/** The name of the poster: the video's own, as a JPEG. */
export function posterName(file: Pick<File, 'name'>): string {
  const base = file.name.replace(/\.[^.]+$/, '')

  return `${base === '' ? 'video' : base}.jpg`
}

const YOUTUBE_HOSTS = new Set([
  'youtube.com',
  'www.youtube.com',
  'm.youtube.com',
  'music.youtube.com',
  'youtu.be',
  'www.youtube-nocookie.com',
  'youtube-nocookie.com',
])

const YOUTUBE_ID = /^[A-Za-z0-9_-]{11}$/

/**
 * The id of a YouTube video in any of the forms the server takes (§3), or `null`. The server
 * decides in the end; this only says what the field should call the address.
 */
export function youTubeId(address: string): string | null {
  let url: URL

  try {
    url = new URL(address.trim())
  } catch {
    return null
  }

  if (!/^https?:$/.test(url.protocol) || !YOUTUBE_HOSTS.has(url.hostname.toLowerCase())) {
    return null
  }

  if (url.hostname.toLowerCase() === 'youtu.be') {
    const id = url.pathname.split('/')[1] ?? ''

    return YOUTUBE_ID.test(id) ? id : null
  }

  const watched = url.pathname === '/watch' ? url.searchParams.get('v') : null

  if (watched !== null) return YOUTUBE_ID.test(watched) ? watched : null

  const [, kind, id] = url.pathname.split('/')

  return ['shorts', 'embed', 'live'].includes(kind ?? '') && YOUTUBE_ID.test(id ?? '')
    ? (id ?? null)
    : null
}

/** A direct link to an MP4 or WebM file, judged by its name — the server checks the answer. */
export function isVideoFileLink(address: string): boolean {
  try {
    const url = new URL(address.trim())

    return /^https?:$/.test(url.protocol) && /\.(mp4|webm)$/i.test(url.pathname)
  } catch {
    return false
  }
}

/** Anything the gallery treats as a video rather than a picture. */
export function isVideoLink(address: string): boolean {
  return youTubeId(address) !== null || isVideoFileLink(address)
}

/** A file the gallery may take as a video, by the limits the screen gave it. */
export function videoRefusal(
  file: Pick<File, 'type' | 'size'>,
  types: readonly string[],
  maxBytes: number,
): 'type' | 'size' | null {
  if (!types.includes(file.type)) return 'type'
  if (file.size > maxBytes) return 'size'

  return null
}

/**
 * The poster the server puts on a video that came by a direct link without a picture: a plain
 * dark PNG, 1280×720 (a single pixel where the server has no GD). Recognised by its shape, as the
 * resource carries no mark of it; a panel-made poster is a JPEG, so a mistake here needs a PNG of
 * exactly that size with a file video on it, and then the hint only says what may be done anyway.
 */
export function hasPlaceholderPoster(image: ProductImage): boolean {
  if (image.video?.provider !== 'file' || !/\.png$/i.test(image.path)) return false

  return (image.width === 1280 && image.height === 720) || (image.width === 1 && image.height === 1)
}

/** Bytes as the panel says them: "840 KB", "1.2 GB". */
export function formatBytes(bytes: number, locale?: string): string {
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let value = Math.max(0, bytes)
  let unit = 0

  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit += 1
  }

  const figure = new Intl.NumberFormat(locale, {
    maximumFractionDigits: value >= 100 || unit === 0 ? 0 : 1,
  }).format(value)

  return `${figure} ${units[unit]}`
}

/** A length of time as a clock: "0:42", "12:05", "1:02:03". */
export function formatClock(seconds: number): string {
  const whole = Math.max(0, Math.round(seconds))
  const hours = Math.floor(whole / 3600)
  const minutes = Math.floor((whole % 3600) / 60)
  const rest = String(whole % 60).padStart(2, '0')

  return hours > 0 ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${minutes}:${rest}`
}
