import { describe, expect, it, vi } from 'vitest'
import type { ProductImage } from './types'
import {
  formatBytes,
  formatClock,
  frameTime,
  hasPlaceholderPoster,
  isVideoFileLink,
  isVideoLink,
  posterName,
  probeVideo,
  videoRefusal,
  VideoUnreadable,
  youTubeId,
  type ProbeEnvironment,
} from './video'

/**
 * A `<video>` that plays back what it is told, since jsdom decodes nothing: it fires the events a
 * browser would for a file of the given size and length — or an error, for one it cannot open.
 */
function fakeVideo(file: { width: number; height: number; duration: number } | 'broken') {
  const listeners: Record<string, (() => void)[]> = {}
  const video = {
    muted: false,
    playsInline: false,
    preload: '',
    duration: Number.NaN,
    videoWidth: 0,
    videoHeight: 0,
    seekedTo: null as number | null,
    addEventListener(name: string, listener: () => void) {
      ;(listeners[name] ??= []).push(listener)
    },
    removeAttribute: vi.fn(),
    load: vi.fn(),
    set currentTime(time: number) {
      video.seekedTo = time
      queueMicrotask(() => listeners.seeked?.forEach((listener) => listener()))
    },
    set src(_url: string) {
      queueMicrotask(() => {
        if (file === 'broken') {
          listeners.error?.forEach((listener) => listener())

          return
        }

        video.duration = file.duration
        video.videoWidth = file.width
        video.videoHeight = file.height
        listeners.loadedmetadata?.forEach((listener) => listener())
      })
    },
  }

  return video
}

function environment(video: ReturnType<typeof fakeVideo>, blob: Blob | null = new Blob(['jpeg'])) {
  const drawn: unknown[][] = []
  const canvas = {
    width: 0,
    height: 0,
    getContext: () => ({ drawImage: (...args: unknown[]) => drawn.push(args) }),
    toBlob: (done: (blob: Blob | null) => void, type: string, quality: number) => {
      canvas.type = type
      canvas.quality = quality
      queueMicrotask(() => done(blob))
    },
    type: '',
    quality: 0,
  }
  const revoked: string[] = []
  const env: ProbeEnvironment = {
    createVideo: () => video as unknown as HTMLVideoElement,
    createCanvas: () => canvas as unknown as HTMLCanvasElement,
    createUrl: () => 'blob:clip',
    revokeUrl: (url) => revoked.push(url),
  }

  return { env, canvas, drawn, revoked }
}

const clip = new Blob(['not decoded here'], { type: 'video/mp4' })

describe('the frame taken for the poster (decision 6)', () => {
  it('draws a frame a little in, as a JPEG the size of the video, and says its length', async () => {
    const video = fakeVideo({ width: 1920, height: 1080, duration: 42.4 })
    const { env, canvas, drawn, revoked } = environment(video)

    const probe = await probeVideo(clip, { frame: true, environment: env })

    expect(probe.frame).toBeInstanceOf(Blob)
    expect(probe).toMatchObject({ duration: 42, width: 1920, height: 1080 })
    expect(video.seekedTo).toBe(1)
    expect([canvas.width, canvas.height, canvas.type]).toEqual([1920, 1080, 'image/jpeg'])
    expect(drawn).toHaveLength(1)
    expect(video.muted).toBe(true)
    // The file is let go of: a gigabyte held by a forgotten object URL stays in memory.
    expect(revoked).toEqual(['blob:clip'])
  })

  it('reads only the length when no frame is asked for', async () => {
    const video = fakeVideo({ width: 640, height: 360, duration: 7.6 })
    const { env, drawn } = environment(video)

    const probe = await probeVideo(clip, { environment: env })

    expect(probe).toEqual({ frame: null, duration: 8, width: 640, height: 360 })
    expect(video.seekedTo).toBeNull()
    expect(drawn).toHaveLength(0)
  })

  it('gives up on a file the browser cannot open', async () => {
    const { env, revoked } = environment(fakeVideo('broken'))

    await expect(probeVideo(clip, { frame: true, environment: env })).rejects.toBeInstanceOf(
      VideoUnreadable,
    )
    expect(revoked).toEqual(['blob:clip'])
  })

  it('gives up on a file with a length and no picture', async () => {
    const { env } = environment(fakeVideo({ width: 0, height: 0, duration: 30 }))

    await expect(probeVideo(clip, { frame: true, environment: env })).rejects.toBeInstanceOf(
      VideoUnreadable,
    )
  })

  it('gives up when the frame cannot be made a picture', async () => {
    const { env } = environment(fakeVideo({ width: 320, height: 240, duration: 3 }), null)

    await expect(probeVideo(clip, { frame: true, environment: env })).rejects.toBeInstanceOf(
      VideoUnreadable,
    )
  })

  it('gives up when the browser never answers', async () => {
    vi.useFakeTimers()

    const video = fakeVideo({ width: 320, height: 240, duration: 3 })
    // A src that never loads.
    Object.defineProperty(video, 'src', { set: () => undefined })
    const { env } = environment(video)

    const probe = probeVideo(clip, { frame: true, timeout: 1000, environment: env })
    const caught = expect(probe).rejects.toBeInstanceOf(VideoUnreadable)

    await vi.advanceTimersByTimeAsync(1000)
    await caught

    vi.useRealTimers()
  })

  it('takes the frame at a tenth of a short clip and at a second of a long one', () => {
    expect(frameTime(4)).toBe(0.4)
    expect(frameTime(600)).toBe(1)
    expect(frameTime(Number.NaN)).toBe(0)
  })

  it('names the poster after the video', () => {
    expect(posterName({ name: 'Lamp at work.mp4' })).toBe('Lamp at work.jpg')
    expect(posterName({ name: '.webm' })).toBe('video.jpg')
  })
})

describe('links to videos', () => {
  it.each([
    'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
    'https://youtube.com/watch?feature=share&v=aqz-KE-bpKQ',
    'https://m.youtube.com/watch?v=aqz-KE-bpKQ',
    'https://music.youtube.com/watch?v=aqz-KE-bpKQ',
    'https://youtu.be/aqz-KE-bpKQ?t=10',
    'https://www.youtube.com/shorts/aqz-KE-bpKQ',
    'https://www.youtube.com/embed/aqz-KE-bpKQ',
    'https://www.youtube.com/live/aqz-KE-bpKQ',
  ])('%s is YouTube', (url) => {
    expect(youTubeId(url)).toBe('aqz-KE-bpKQ')
    expect(isVideoLink(url)).toBe(true)
  })

  it.each([
    'https://www.youtube.com/watch?v=short',
    'https://www.youtube.com/channel/aqz-KE-bpKQ',
    'https://notyoutube.com/watch?v=aqz-KE-bpKQ',
    'https://vimeo.com/123456',
    'ftp://youtu.be/aqz-KE-bpKQ',
    'not an address',
  ])('%s is not', (url) => {
    expect(youTubeId(url)).toBeNull()
  })

  it('takes a direct link to an MP4 or WebM file, by its name', () => {
    expect(isVideoFileLink('https://cdn.example.com/clips/lamp.mp4')).toBe(true)
    expect(isVideoFileLink('https://cdn.example.com/lamp.WEBM?sig=1')).toBe(true)
    expect(isVideoFileLink('https://cdn.example.com/lamp.mov')).toBe(false)
    expect(isVideoFileLink('https://cdn.example.com/lamp.jpg')).toBe(false)
    expect(isVideoLink('https://cdn.example.com/lamp.jpg')).toBe(false)
  })
})

describe('the checks made before a byte is sent', () => {
  const types = ['video/mp4', 'video/webm']

  it('refuses another type, and a file over the limit', () => {
    expect(videoRefusal({ type: 'video/quicktime', size: 10 }, types, 100)).toBe('type')
    expect(videoRefusal({ type: 'video/mp4', size: 101 }, types, 100)).toBe('size')
    expect(videoRefusal({ type: 'video/webm', size: 100 }, types, 100)).toBeNull()
  })
})

describe('the server’s plain poster', () => {
  const row = (over: Partial<ProductImage>): ProductImage => ({
    id: 1,
    path: 'catalog/0/7/abc.png',
    url: '/abc.png',
    thumb: null,
    alt: {},
    title: {},
    width: 1280,
    height: 720,
    size: 900,
    position: 1,
    video: { provider: 'file', url: '/clip.mp4', embed: null, duration: null },
    ...over,
  })

  it('is a PNG of its shape with a file video on it', () => {
    expect(hasPlaceholderPoster(row({}))).toBe(true)
    expect(hasPlaceholderPoster(row({ width: 1, height: 1 }))).toBe(true)
  })

  it('is not a frame the panel took, a YouTube cover or a picture without a video', () => {
    expect(hasPlaceholderPoster(row({ path: 'catalog/0/7/abc.jpg' }))).toBe(false)
    expect(
      hasPlaceholderPoster(
        row({ video: { provider: 'youtube', url: 'u', embed: 'e', duration: null } }),
      ),
    ).toBe(false)
    expect(hasPlaceholderPoster(row({ video: null }))).toBe(false)
  })
})

describe('figures', () => {
  it('says bytes the short way', () => {
    expect(formatBytes(512, 'en')).toBe('512 B')
    expect(formatBytes(1536, 'en')).toBe('1.5 KB')
    expect(formatBytes(3.4 * 1024 ** 3, 'en')).toBe('3.4 GB')
    expect(formatBytes(250 * 1024 ** 2, 'en')).toBe('250 MB')
  })

  it('says a length as a clock', () => {
    expect(formatClock(42)).toBe('0:42')
    expect(formatClock(725)).toBe('12:05')
    expect(formatClock(3723)).toBe('1:02:03')
  })
})
