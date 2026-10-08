import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { vWxSelect } from '@webx-ui/core'
import FileGrid from './FileGrid.vue'
import type { MediaApi } from './api'
import type { MediaFile } from './types'

/*
 * Names in the library are titles without an extension, so the grid is the one place the
 * format can be read off a file — and a JPEG and a PNG of the same photo look alike.
 */
function file(id: number, extension: string, mime: string): MediaFile {
  return {
    id,
    directory_id: 1,
    name: `File ${id}`,
    file_name: `file-${id}.${extension}`,
    extension,
    mime,
    type: mime.startsWith('image/') ? 'image' : 'document',
    size: 2048,
    width: mime.startsWith('image/') ? 800 : null,
    height: mime.startsWith('image/') ? 600 : null,
    path: `media/aa/bb/uuid-${id}.${extension}`,
    url: `/storage/media/aa/bb/uuid-${id}.${extension}`,
    thumb: null,
    source: null,
    editable: false,
    has_original: false,
    duplicate: false,
    created_at: null,
  }
}

const api = { thumb: (f: MediaFile) => `/thumb/${f.id}` } as unknown as MediaApi

function grid(files: MediaFile[]) {
  return mount(FileGrid, {
    props: { files, api },
    global: { directives: { 'wx-select': vWxSelect } },
  })
}

describe('FileGrid', () => {
  it('puts the extension over every picture, captions untouched', () => {
    const wrapper = grid([file(1, 'jpg', 'image/jpeg'), file(2, 'webp', 'image/webp')])

    const badges = wrapper.findAll('.wx-file-card__badge').map((badge) => badge.text())

    expect(badges).toEqual(['jpg', 'webp'])
    expect(wrapper.findAll('.wx-file-card__name').map((name) => name.text())).toEqual([
      'File 1',
      'File 2',
    ])
  })

  it('writes the extension into the placeholder of a file with no preview', () => {
    const wrapper = grid([file(3, 'pdf', 'application/pdf')])

    expect(wrapper.find('.wx-file-card__badge').exists()).toBe(false)
    expect(wrapper.get('.wx-file-card__extension').text()).toBe('pdf')
  })

  it('says what the file is in the tip over its card', () => {
    const wrapper = grid([file(1, 'png', 'image/png')])

    expect(wrapper.get('.wx-file-card').attributes('title')).toBe(
      'PNG · image/png · 800×600 · 2.0 kB',
    )
  })

  /* jsdom has no DataTransfer: this one records what a drag would carry. */
  function transfer() {
    const data = new Map<string, string>()

    return {
      data,
      effectAllowed: '',
      items: { clear: () => data.clear() },
      setData: (type: string, value: string) => data.set(type, value),
    }
  }

  async function drag(wrapper: ReturnType<typeof grid>, index: number) {
    const dataTransfer = transfer()
    const event = new Event('dragstart', { bubbles: true, cancelable: true })

    Object.defineProperty(event, 'dataTransfer', { value: dataTransfer })
    wrapper.findAll('.wx-file-card')[index]!.element.dispatchEvent(event)

    return JSON.parse(dataTransfer.data.get('application/x-webx-media-files') ?? 'null')
  }

  it('drags one file that was never selected, then highlights it', async () => {
    vi.useFakeTimers()
    const wrapper = grid([file(1, 'png', 'image/png'), file(2, 'png', 'image/png')])
    await wrapper.setProps({ draggable: true, selected: [1] })

    expect(await drag(wrapper, 1)).toEqual([2])
    // Not inside dragstart: Chrome cancels a drag whose card changes while it starts.
    expect(wrapper.emitted('update:selected')).toBeUndefined()

    vi.runAllTimers()
    expect(wrapper.emitted('update:selected')?.at(-1)).toEqual([[2]])
    vi.useRealTimers()
  })

  it('drags the only selected file, and a whole selection from any of its cards', async () => {
    const wrapper = grid([file(1, 'png', 'image/png'), file(2, 'png', 'image/png')])

    await wrapper.setProps({ draggable: true, selected: [2] })
    expect(await drag(wrapper, 1)).toEqual([2])

    await wrapper.setProps({ selected: [1, 2] })
    expect(await drag(wrapper, 0)).toEqual([1, 2])
    expect(wrapper.emitted('update:selected')).toBeUndefined()
  })
})
