import { disableAutoUnmount, enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterAll, afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, defineComponent, h, ref, type App } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { connectModals, localesKey, toast } from '@webx-ui/core'
import { provideProductEditor } from './editor'
import GalleryField from './GalleryField.vue'
import { createGalleryVideo } from './galleryVideo'
import type { ProductImage, ProductRow, QueuedVideo } from './types'
import type { ProbeEnvironment } from './video'

afterEach(() => {
  for (const node of document.querySelectorAll('.wx-modal-host, .wx-dialog, .wx-dropdown')) {
    node.remove()
  }
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

// The caption pause and the uploads outlive a test that never unmounts the field (docs/pitfalls).
enableAutoUnmount(afterEach)
afterAll(disableAutoUnmount)

const lamp = { id: 7, name: 'Desk lamp' } as ProductRow

function picture(id: number, over: Partial<ProductImage> = {}): ProductImage {
  return {
    id,
    path: `catalog/0/7/${id}.jpg`,
    url: `/files/${id}.jpg`,
    thumb: null,
    alt: {},
    title: {},
    width: 800,
    height: 600,
    size: 1000,
    position: id,
    video: null,
    ...over,
  }
}

const youtube = {
  provider: 'youtube',
  url: 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
  embed: 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ',
  duration: null,
}

const clipFile = { provider: 'file', url: '/files/clip.mp4', embed: null, duration: 42 }

/** A `<video>` that opens any file as a 1280×720 clip of 42 seconds — jsdom decodes nothing. */
const decoding: ProbeEnvironment = {
  createVideo() {
    const listeners: Record<string, (() => void)[]> = {}
    const video = {
      duration: Number.NaN,
      videoWidth: 0,
      videoHeight: 0,
      addEventListener: (name: string, listener: () => void) =>
        (listeners[name] ??= []).push(listener),
      removeAttribute: () => undefined,
      load: () => undefined,
      set currentTime(_time: number) {
        queueMicrotask(() => listeners.seeked?.forEach((listener) => listener()))
      },
      set src(_url: string) {
        queueMicrotask(() => {
          Object.assign(video, { duration: 42.2, videoWidth: 1280, videoHeight: 720 })
          listeners.loadedmetadata?.forEach((listener) => listener())
        })
      },
    }

    return video as unknown as HTMLVideoElement
  },
  createCanvas: () =>
    ({
      getContext: () => ({ drawImage: () => undefined }),
      toBlob: (done: (blob: Blob) => void) =>
        queueMicrotask(() => done(new Blob(['frame'], { type: 'image/jpeg' }))),
    }) as unknown as HTMLCanvasElement,
  createUrl: () => 'blob:clip',
  revokeUrl: () => undefined,
}

/** The picture upload is XHR (`api.ts`); this one answers with the frame as a new row. */
function stubPictureUpload(row: ProductImage): { sent: FormData[] } {
  const sent: FormData[] = []

  class Request {
    status = 201
    response: unknown = { data: row }
    upload = { addEventListener: () => undefined }
    private done: (() => void) | undefined
    open() {}
    setRequestHeader() {}
    addEventListener(name: string, listener: () => void) {
      if (name === 'load') this.done = listener
    }
    send(body: FormData) {
      sent.push(body)
      queueMicrotask(() => this.done?.())
    }
  }

  vi.stubGlobal('XMLHttpRequest', Request)

  return { sent }
}

function memory(): Storage {
  const items = new Map<string, string>()

  return {
    getItem: (key) => items.get(key) ?? null,
    setItem: (key, value) => void items.set(key, value),
    removeItem: (key) => void items.delete(key),
    clear: () => items.clear(),
    key: () => null,
    get length() {
      return items.size
    },
  }
}

interface Options {
  props?: Record<string, unknown>
  images?: ProductImage[]
  post?: (url: string, body: Record<string, unknown>) => unknown
}

function gallery(options: Options = {}) {
  const post = vi.fn((url: string, body: Record<string, unknown>) =>
    Promise.resolve(options.post?.(url, body)),
  )
  const del = vi.fn((url: string) =>
    Promise.resolve(
      url.endsWith('/video') ? { data: { ...images.value[0]!, video: null } } : undefined,
    ),
  )
  const i18n = createI18n()
  const admin = {
    apiPath: '/api/cms',
    http: { get: vi.fn(), put: vi.fn(), post, delete: del },
    i18n,
  } as unknown as AdminContext
  const images = ref<ProductImage[]>(options.images ?? [picture(1)])
  const storage = memory()

  const Host = defineComponent({
    setup() {
      const product = ref(lamp)

      provideProductEditor({
        product,
        images,
        locked: ref(false),
        video: createGalleryVideo({
          admin,
          product: computed(() => product.value.id),
          images,
          storage,
          probe: { environment: decoding },
        }),
      })

      return () => h(GalleryField, options.props ?? { video: true })
    },
  })

  const wrapper = mount(Host, {
    attachTo: document.body,
    global: {
      plugins: [{ install: (app: App) => connectModals(app) }],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: i18n,
        [localesKey as symbol]: { list: ref([{ code: 'en' }]), active: ref('en') },
      },
    },
  })

  return { wrapper, post, del, images, storage }
}

async function openMenu(wrapper: ReturnType<typeof gallery>['wrapper'], row = 0) {
  await wrapper.findAll('.wx-row-menu .wx-actions__menu button')[row]!.trigger('click')
  await flushPromises()

  return [...document.querySelectorAll('.wx-dropdown-item')].map((item) => item.textContent?.trim())
}

function drop(wrapper: ReturnType<typeof gallery>['wrapper'], file: File) {
  const input = wrapper.find('.wx-upload input[type=file]')
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })

  return input.trigger('change')
}

beforeEach(() => {
  vi.stubGlobal(
    'URL',
    Object.assign(URL, { createObjectURL: () => 'blob:frame', revokeObjectURL: () => undefined }),
  )
})

describe('a row with a video', () => {
  it('has ▶ on its preview, and the preview opens the video', () => {
    const { wrapper } = gallery({
      images: [picture(1, { video: youtube }), picture(2, { video: clipFile }), picture(3)],
    })

    const thumbs = wrapper.findAll('.wx-catalog-gallery__thumb')

    expect(thumbs[0]!.find('.wx-catalog-gallery__play').exists()).toBe(true)
    expect(thumbs[0]!.attributes('href')).toBe(youtube.url)
    // A file says how long it is.
    expect(thumbs[1]!.find('.wx-catalog-gallery__play').text()).toContain('0:42')
    expect(thumbs[1]!.attributes('href')).toBe('/files/clip.mp4')
    // A plain picture opens itself, with no ▶.
    expect(thumbs[2]!.find('.wx-catalog-gallery__play').exists()).toBe(false)
    expect(thumbs[2]!.attributes('href')).toBe('/files/3.jpg')
  })

  it('says how to replace the server’s plain poster', () => {
    const { wrapper } = gallery({
      images: [
        picture(1, { path: 'catalog/0/7/p.png', width: 1280, height: 720, video: clipFile }),
      ],
    })

    expect(wrapper.text()).toContain('add a picture, attach the same link to it')
  })
})

describe('the row menu', () => {
  it('offers a file or a YouTube link on a picture without a video', async () => {
    const { wrapper } = gallery()

    expect(await openMenu(wrapper)).toEqual([
      'Attach a video file…',
      'Attach a YouTube link…',
      'Delete',
    ])
  })

  it('offers to remove the video on a row with one, and asks first', async () => {
    const { wrapper, del, images } = gallery({ images: [picture(1, { video: clipFile })] })

    expect(await openMenu(wrapper)).toEqual(['Remove the video', 'Delete'])
    ;(document.querySelector('.wx-dropdown-item') as HTMLElement).click()
    await flushPromises()

    // A file goes from the disk at once, which the question says.
    expect(document.body.textContent).toContain('The video file is deleted from the disk at once')
    expect(del).not.toHaveBeenCalled()

    const agree = [...document.querySelectorAll('.wx-dialog button')].find(
      (button) => button.textContent?.trim() === 'Remove the video',
    ) as HTMLElement
    agree.click()
    await flushPromises()

    expect(del).toHaveBeenCalledWith('/api/cms/catalog/products/7/images/1/video')
    expect(images.value[0]!.video).toBeNull()
  })
})

describe('the address card', () => {
  it('takes a YouTube link as a new row, and says so', async () => {
    const { wrapper, post, images } = gallery({
      post: () => ({ data: picture(9, { video: youtube }) }),
    })

    expect(wrapper.text()).toContain('a YouTube link, or a direct link to an MP4 or WebM file')

    await wrapper
      .find('.wx-catalog-gallery__address input')
      .setValue('https://youtu.be/aqz-KE-bpKQ')
    await wrapper.find('.wx-catalog-gallery__address button').trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/catalog/products/7/images', {
      url: 'https://youtu.be/aqz-KE-bpKQ',
    })
    expect(images.value.map((one) => one.id)).toEqual([1, 9])
  })

  it('answers a direct link with a download on the server rather than a row', async () => {
    const queued: QueuedVideo = {
      queued: true,
      product: 7,
      url: 'https://cdn.test/a.mp4',
      image: null,
    }
    const info = vi.spyOn(toast, 'info')
    const { wrapper, images } = gallery({ post: () => ({ data: queued }) })

    await wrapper.find('.wx-catalog-gallery__address input').setValue('https://cdn.test/a.mp4')
    await wrapper.find('.wx-catalog-gallery__address button').trigger('click')
    await flushPromises()

    expect(images.value).toHaveLength(1)
    expect(info).toHaveBeenCalledWith(expect.stringContaining('downloading on the server'))
  })
})

describe('videos switched off', () => {
  it('leaves out the menu items, the video types and every word about videos', async () => {
    const { wrapper } = gallery({ props: { video: false } })

    expect(wrapper.find('.wx-upload input').attributes('accept')).not.toContain('video/')
    expect(wrapper.find('.wx-catalog-gallery__picker').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('YouTube')
    expect(await openMenu(wrapper)).toEqual(['Delete'])
  })

  it('never starts an upload, whatever is dropped', async () => {
    const { wrapper, post } = gallery({ props: { video: false } })

    await drop(wrapper, new File(['x'], 'clip.mp4', { type: 'video/mp4' }))
    await flushPromises()

    expect(post).not.toHaveBeenCalled()
  })
})

describe('a video file dropped into the gallery', () => {
  it('checks the type and the size before a byte is sent', async () => {
    const danger = vi.spyOn(toast, 'danger')
    const { wrapper, post } = gallery({ props: { video: true, videoMaxBytes: 4 } })

    await drop(wrapper, new File(['12345'], 'big.mp4', { type: 'video/mp4' }))
    await flushPromises()

    expect(post).not.toHaveBeenCalled()
    expect(danger).toHaveBeenCalledWith('The video is larger than 4 B.')

    await drop(wrapper, new File(['1'], 'clip.mov', { type: 'video/quicktime' }))
    await flushPromises()

    expect(post).not.toHaveBeenCalled()
    expect(danger).toHaveBeenLastCalledWith(expect.stringContaining('MP4'))
  })

  it('becomes a picture of its own frame, goes up in pieces and is attached to it', async () => {
    const bytes = new Uint8Array(300 * 1024)
    const file = new File([bytes], 'lamp.mp4', { type: 'video/mp4', lastModified: 5 })
    const frame = picture(2, { path: 'catalog/0/7/lamp.jpg' })
    const { sent } = stubPictureUpload(frame)

    const fetch = vi.fn((url: string, init: RequestInit) => {
      const from = Number((init.headers as Record<string, string>)['Upload-Offset'])
      const length = (init.body as Blob).size

      expect(url).toBe('/api/cms/uploads/u1')
      expect(init.method).toBe('PATCH')

      return Promise.resolve(
        new Response(null, { status: 204, headers: { 'Upload-Offset': String(from + length) } }),
      )
    })
    vi.stubGlobal('fetch', fetch)

    const { wrapper, post, images } = gallery({
      post: (url) =>
        url === '/api/cms/uploads'
          ? { data: { id: 'u1', offset: 0, size: file.size, chunk_size: 256 * 1024 } }
          : { data: { ...frame, video: { ...clipFile, url: '/files/lamp.mp4' } } },
    })

    await drop(wrapper, file)
    await vi.waitFor(() => expect(images.value[1]?.video?.url).toBe('/files/lamp.mp4'))

    // The frame went up first, as a JPEG named after the video.
    expect((sent[0]!.get('file') as File).name).toBe('lamp.jpg')
    expect((sent[0]!.get('file') as File).type).toBe('image/jpeg')
    // Then the file, announced with its fingerprint, in two pieces of the advised size.
    expect(post).toHaveBeenCalledWith('/api/cms/uploads', {
      name: 'lamp.mp4',
      size: file.size,
      type: 'video/mp4',
      fingerprint: 'lamp.mp4|307200|5',
      purpose: 'catalog.video',
    })
    expect(fetch).toHaveBeenCalledTimes(2)
    // And the two put together, with the length the browser read.
    expect(post).toHaveBeenLastCalledWith('/api/cms/catalog/products/7/images/2/video', {
      upload: 'u1',
      duration: 42,
    })
    expect(wrapper.find('.wx-catalog-video-progress').exists()).toBe(false)
  })

  it('asks before the page is left while it goes up', async () => {
    stubPictureUpload(picture(2))
    // The first piece never answers: the upload is still going when the page is left.
    vi.stubGlobal(
      'fetch',
      vi.fn(() => new Promise(() => undefined)),
    )

    const { wrapper } = gallery({
      post: () => ({ data: { id: 'u1', offset: 0, size: 10, chunk_size: 262144 } }),
    })

    const idle = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(idle)
    expect(idle.defaultPrevented).toBe(false)

    await drop(wrapper, new File(['0123456789'], 'clip.mp4', { type: 'video/mp4' }))
    await vi.waitFor(() => expect(wrapper.find('.wx-catalog-video-progress').exists()).toBe(true))

    const leaving = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(leaving)
    expect(leaving.defaultPrevented).toBe(true)
  })
})
