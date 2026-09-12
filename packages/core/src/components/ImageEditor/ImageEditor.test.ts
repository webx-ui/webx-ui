import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import WxImageEditor from './ImageEditor.vue'
import type { ImageEditorProps, ImageEditorResult } from './types'

/*
 * jsdom draws nothing and lays nothing out, so what is checked here is the state either
 * side of the drawing: what the picture's loading sets up, what a turn does to the crop,
 * and — through a stubbed context — the transform the export would be drawn under. The
 * dragging itself is geometry and is checked in `crop.test.ts`; how it all looks is a
 * question for a browser.
 */

const context = {
  imageSmoothingQuality: '',
  fillStyle: '',
  /* Present, the way it is on every browser since Safari 16.4. */
  filter: '',
  fillRect: vi.fn(),
  setTransform: vi.fn(),
  translate: vi.fn(),
  scale: vi.fn(),
  rotate: vi.fn(),
  drawImage: vi.fn(),
}

const originalGetContext = HTMLCanvasElement.prototype.getContext

beforeEach(() => {
  context.filter = ''
  for (const call of Object.values(context)) {
    if (typeof call === 'function') call.mockClear()
  }

  HTMLCanvasElement.prototype.getContext = vi.fn(
    () => context,
  ) as unknown as typeof HTMLCanvasElement.prototype.getContext

  HTMLCanvasElement.prototype.toBlob = function toBlob(callback, type) {
    callback(new Blob(['picture'], { type: type ?? 'image/png' }))
  }
})

afterEach(() => {
  HTMLCanvasElement.prototype.getContext = originalGetContext
})

function editor(props: Partial<ImageEditorProps> = {}) {
  return mount(WxImageEditor, {
    props: { src: 'https://files.example/hero.png', ...props } as ImageEditorProps,
    attachTo: document.body,
  })
}

type Editor = ReturnType<typeof editor>

/** The picture arriving, which jsdom will never do on its own. */
async function loaded(wrapper: Editor, width = 800, height = 600) {
  const img = wrapper.get('img')
  Object.defineProperty(img.element, 'naturalWidth', { configurable: true, value: width })
  Object.defineProperty(img.element, 'naturalHeight', { configurable: true, value: height })
  await img.trigger('load')
  return wrapper
}

function cropOf(wrapper: Editor) {
  return (
    wrapper.vm as unknown as { crop: { x: number; y: number; width: number; height: number } }
  ).crop
}

function saved(wrapper: Editor) {
  return wrapper.emitted('save')?.at(-1)?.[0] as ImageEditorResult | undefined
}

describe('WxImageEditor', () => {
  it('says what it loaded, and starts on the whole of it', async () => {
    const wrapper = await loaded(editor())

    expect(wrapper.emitted('load')?.at(-1)).toEqual([{ width: 800, height: 600 }])
    expect(cropOf(wrapper)).toEqual({ x: 0, y: 0, width: 800, height: 600 })
  })

  it('opens on the ratio it is locked to, in the middle', async () => {
    const wrapper = await loaded(editor({ aspect: 1 }))

    expect(cropOf(wrapper)).toEqual({ x: 100, y: 0, width: 600, height: 600 })
  })

  it('has no ratio picker when it is locked to one', async () => {
    const wrapper = await loaded(editor({ aspect: 1 }))

    expect(wrapper.find('.wx-image-editor__ratios').exists()).toBe(false)
    expect((await loaded(editor())).find('.wx-image-editor__ratios').exists()).toBe(true)
  })

  it('turns the picture, and the crop with it', async () => {
    const wrapper = await loaded(editor())

    await wrapper.get('[title="Turn right"]').trigger('click')

    /* The sides have swapped, and the crop is still the whole picture. */
    expect(cropOf(wrapper)).toEqual({ x: 0, y: 0, width: 600, height: 800 })
    expect(saved(wrapper)).toBeUndefined()
  })

  it('mirrors it', async () => {
    const wrapper = await loaded(editor())

    await wrapper.get('[title="Mirror across"]').trigger('click')
    const result = await (
      wrapper.vm as unknown as { apply: () => Promise<ImageEditorResult> }
    ).apply()

    expect(result.flipX).toBe(true)
    expect(result.flipY).toBe(false)
  })

  it('goes back to the whole picture when it is reset', async () => {
    const wrapper = await loaded(editor())

    await wrapper.get('[title="Turn right"]').trigger('click')
    await wrapper.get('.wx-image-editor__reset').trigger('click')

    expect(cropOf(wrapper)).toEqual({ x: 0, y: 0, width: 800, height: 600 })
  })

  it('draws the crop under the transform the picture was turned by', async () => {
    const wrapper = await loaded(editor())

    await wrapper.get('[title="Turn right"]').trigger('click')
    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()

    /* The turned picture is 600×800, and the whole of it is asked for. */
    expect(context.setTransform).toHaveBeenCalledWith(1, 0, 0, 1, -0, -0)
    expect(context.translate).toHaveBeenCalledWith(300, 400)
    expect(context.rotate).toHaveBeenCalledWith(Math.PI / 2)
    expect(context.drawImage).toHaveBeenCalledWith(expect.anything(), -400, -300, 800, 600)
  })

  it('answers with the blob, its size and the crop it came from', async () => {
    const wrapper = await loaded(editor())

    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()
    const result = saved(wrapper)!

    expect(result.blob).toBeInstanceOf(Blob)
    expect(result.width).toBe(800)
    expect(result.height).toBe(600)
    expect(result.crop).toEqual({ x: 0, y: 0, width: 800, height: 600 })
    expect(result.rotation).toBe(0)
  })

  it('names the file after the picture, with the extension it was actually written as', async () => {
    const wrapper = await loaded(editor())

    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()

    expect(saved(wrapper)!.file.name).toBe('hero.png')
    expect(saved(wrapper)!.type).toBe('image/png')
  })

  it('writes a JPEG when asked, over a background, since a JPEG has no transparency', async () => {
    const wrapper = await loaded(editor({ format: 'image/jpeg', fileName: 'avatar.png' }))

    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()

    expect(context.fillRect).toHaveBeenCalled()
    expect(saved(wrapper)!.file.name).toBe('avatar.jpg')
  })

  it('scales the result down to the cap, keeping its shape', async () => {
    const wrapper = await loaded(editor({ maxWidth: 400 }))

    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()

    expect(saved(wrapper)!.width).toBe(400)
    expect(saved(wrapper)!.height).toBe(300)
    /* Half the size, so the whole thing is drawn at half scale. */
    expect(context.setTransform).toHaveBeenCalledWith(0.5, 0, 0, 0.5, -0, -0)
  })

  it('says so when the picture will not load', async () => {
    const wrapper = editor()

    await wrapper.get('img').trigger('error')

    expect(wrapper.emitted('error')).toHaveLength(1)
    expect(wrapper.find('.wx-image-editor__failed').exists()).toBe(true)
  })

  it('reports a refusal to encode rather than throwing at the caller', async () => {
    HTMLCanvasElement.prototype.toBlob = function toBlob(callback) {
      callback(null)
    }

    const wrapper = await loaded(editor())
    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()

    expect(wrapper.emitted('save')).toBeUndefined()
    expect(wrapper.emitted('error')).toHaveLength(1)
  })

  it('has its own footer, and gives it up on request', async () => {
    const wrapper = await loaded(editor())

    expect(wrapper.findAll('.wx-image-editor__footer button')).toHaveLength(2)

    await wrapper.get('.wx-image-editor__footer button').trigger('click')
    expect(wrapper.emitted('cancel')).toHaveLength(1)

    const bare = await loaded(editor({ footer: false }))
    expect(bare.find('.wx-image-editor__footer').exists()).toBe(false)
  })

  it('does nothing at all when it is disabled', async () => {
    const wrapper = await loaded(editor({ disabled: true }))

    await wrapper.get('[title="Turn right"]').trigger('click')

    expect(cropOf(wrapper)).toEqual({ x: 0, y: 0, width: 800, height: 600 })
  })
  it('sizes the result from either side, and says what the size is of', async () => {
    const wrapper = await loaded(editor())

    expect(wrapper.get('.wx-image-editor__caption').text()).toBe('Output')

    /* A height typed in comes back as exactly that height, not a pixel either side. */
    await wrapper.get('[aria-label="Height"]').setValue('410')
    expect((wrapper.get('[aria-label="Width"]').element as HTMLInputElement).value).toBe('547')

    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()

    expect(saved(wrapper)!.width).toBe(547)
    expect(saved(wrapper)!.height).toBe(410)
  })

  it('will not be asked for more pixels than the crop has', async () => {
    const wrapper = await loaded(editor())

    await wrapper.get('[aria-label="Width"]').setValue('4000')
    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()

    expect(saved(wrapper)!.width).toBe(800)
  })

  it('reads the size out when it may not be changed', async () => {
    const wrapper = await loaded(editor({ resizable: false }))

    expect(wrapper.findAll('.wx-image-editor__field')).toHaveLength(0)
    expect(wrapper.get('.wx-image-editor__size').text()).toContain('800')
    expect(wrapper.get('.wx-image-editor__size').text()).toContain('600')
  })
  it('has no adjustments unless they are asked for', async () => {
    expect((await loaded(editor())).find('.wx-image-editor__adjust').exists()).toBe(false)
    expect(
      (await loaded(editor({ filters: true }))).find('.wx-image-editor__adjust').exists(),
    ).toBe(true)
  })

  it('draws under the adjustments, and says what they were', async () => {
    const wrapper = await loaded(editor({ filters: true }))
    const vm = wrapper.vm as unknown as { adjust: { brightness: number; mono: boolean } }

    vm.adjust.brightness = 120
    vm.adjust.mono = true
    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()

    expect(context.filter).toBe('brightness(120%) grayscale(1)')
    expect(saved(wrapper)!.filter).toBe('brightness(120%) grayscale(1)')
    expect(saved(wrapper)!.adjustments.brightness).toBe(120)
  })

  it('leaves the canvas alone when nothing was adjusted', async () => {
    const wrapper = await loaded(editor({ filters: true }))

    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()

    expect(saved(wrapper)!.filter).toBe('')
  })

  it('does the arithmetic itself where the canvas cannot', async () => {
    /* Safari before 16.4: the property is simply not there. */
    const without = {
      ...context,
      getImageData: vi.fn(() => ({ data: new Uint8ClampedArray([10, 20, 30, 255]) })),
      putImageData: vi.fn(),
    }
    delete (without as { filter?: unknown }).filter
    HTMLCanvasElement.prototype.getContext = vi.fn(
      () => without,
    ) as unknown as typeof HTMLCanvasElement.prototype.getContext

    const wrapper = await loaded(editor({ filters: true }))
    ;(wrapper.vm as unknown as { adjust: { brightness: number } }).adjust.brightness = 200
    await (wrapper.vm as unknown as { apply: () => Promise<unknown> }).apply()

    /* Not silently unadjusted: the pixels were read, changed and put back. */
    expect(without.getImageData).toHaveBeenCalled()
    expect(without.putImageData).toHaveBeenCalled()
    expect(saved(wrapper)!.filter).toBe('brightness(200%)')
  })
})
