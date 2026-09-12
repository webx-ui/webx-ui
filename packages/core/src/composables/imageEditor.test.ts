import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { openImageEditor } from './imageEditor'
import type { ImageEditorResult } from '../components/ImageEditor/types'

/* The same stubs the editor's own tests use: jsdom has no canvas to draw on. */
const context = {
  imageSmoothingQuality: '',
  fillStyle: '',
  fillRect: vi.fn(),
  setTransform: vi.fn(),
  translate: vi.fn(),
  scale: vi.fn(),
  rotate: vi.fn(),
  drawImage: vi.fn(),
}

const originalGetContext = HTMLCanvasElement.prototype.getContext

beforeEach(() => {
  HTMLCanvasElement.prototype.getContext = vi.fn(
    () => context,
  ) as unknown as typeof HTMLCanvasElement.prototype.getContext

  HTMLCanvasElement.prototype.toBlob = function toBlob(callback, type) {
    callback(new Blob(['picture'], { type: type ?? 'image/png' }))
  }
})

afterEach(() => {
  HTMLCanvasElement.prototype.getContext = originalGetContext
  for (const host of document.querySelectorAll('.wx-modal-host')) host.remove()
})

/** The picture arriving in the panel that was just opened. */
async function loadPicture(width = 800, height = 600) {
  await nextTick()
  await nextTick()

  const img = document.querySelector('.wx-image-editor img') as HTMLImageElement
  Object.defineProperty(img, 'naturalWidth', { configurable: true, value: width })
  Object.defineProperty(img, 'naturalHeight', { configurable: true, value: height })
  img.dispatchEvent(new Event('load'))
  await nextTick()
  return img
}

function button(label: string) {
  const found = [...document.querySelectorAll('button')].find((el) =>
    el.textContent?.trim().startsWith(label),
  )
  if (!found) throw new Error(`No "${label}" button in the panel`)
  return found
}

describe('openImageEditor', () => {
  it('opens the editor in a panel and answers with what came out of it', async () => {
    const opened = openImageEditor({ src: 'https://files.example/hero.png' }, { duration: 0 })

    await loadPicture()
    button('Save').click()

    const result = (await opened) as ImageEditorResult
    expect(result.blob).toBeInstanceOf(Blob)
    expect(result.width).toBe(800)
    expect(result.height).toBe(600)
    expect(result.file.name).toBe('hero.png')
  })

  it('answers with nothing when it is closed instead', async () => {
    const opened = openImageEditor({ src: 'https://files.example/hero.png' }, { duration: 0 })

    await loadPicture()
    button('Cancel').click()

    await expect(opened).resolves.toBeUndefined()
  })

  it('can be closed from outside, the way any modal can', async () => {
    const opened = openImageEditor({ src: 'https://files.example/hero.png' }, { duration: 0 })

    await loadPicture()
    opened.close()

    await expect(opened).resolves.toBeUndefined()
  })
})
