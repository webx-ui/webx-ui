import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { adminKey } from '@webx-ui/module-admin'
import FileField from './FileField.vue'
import FilesField from './FilesField.vue'
import GalleryField from './GalleryField.vue'
import { openMediaFiles } from './openMediaPicker'
import type { MediaFile, MediaValue } from './types'

/*
 * The library is stood in for on both sides: the dialog that picks files, and the endpoint that
 * says what a stored key currently points at. What is under test is what the field keeps — the
 * key and the order and nothing else — and what it draws for a key the library has lost.
 *
 * Layout is not here and cannot be: jsdom computes none of it, so the grid, the drag and the
 * width of a phone are checked in a browser.
 */
vi.mock('./openMediaPicker', () => ({
  openMediaFiles: vi.fn(),
  openMediaLibrary: vi.fn(),
}))

const library: Record<string, MediaFile> = {}

vi.mock('./api', () => ({
  createMediaApi: () => ({
    fileByPath: (path: string) => Promise.resolve(library[path] ?? null),
    thumb: (file: MediaFile) => file.thumb,
  }),
}))

function file(path: string, extra: Partial<MediaFile> = {}): MediaFile {
  return {
    id: path.length,
    directory_id: 1,
    name: path.split('/').pop()!,
    file_name: path.split('/').pop()!,
    extension: 'jpg',
    mime: 'image/jpeg',
    type: 'image',
    size: 2048,
    width: 800,
    height: 600,
    path,
    url: `/storage/${path}`,
    thumb: `/media/thumb/${path}`,
    source: null,
    editable: true,
    has_original: false,
    duplicate: false,
    created_at: null,
    ...extra,
  }
}

const panel = { global: { provide: { [adminKey as symbol]: {} } } }

function picks(...files: MediaFile[]): void {
  vi.mocked(openMediaFiles).mockResolvedValueOnce(files)
}

beforeEach(() => {
  vi.mocked(openMediaFiles).mockReset()
  for (const key of Object.keys(library)) delete library[key]
  library['a.jpg'] = file('a.jpg')
  library['b.jpg'] = file('b.jpg')
})

describe('a gallery', () => {
  it('keeps the keys and the order, and nothing the library told it', async () => {
    const wrapper = mount(GalleryField, { props: { modelValue: [] }, ...panel })

    picks(library['a.jpg']!, library['b.jpg']!)
    await wrapper.find('.wx-media-list__footer button').trigger('click')
    await flushPromises()

    const next = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as MediaValue[]

    expect(next).toEqual([{ path: 'a.jpg' }, { path: 'b.jpg' }])
  })

  it('asks the library only for what it does not already know', async () => {
    const wrapper = mount(GalleryField, { props: { modelValue: [{ path: 'a.jpg' }] }, ...panel })
    await flushPromises()

    // Three: the captions, the way into the library, and taking it out of the list. Two would
    // mean the wrapper had declared `captions` and handed the list a `false` nobody asked for.
    expect(wrapper.findAll('.wx-media-list__tools button')).toHaveLength(3)
    expect(wrapper.find('.wx-media-list__missing').exists()).toBe(false)
  })

  it('draws a key the library has lost as a card that says so', async () => {
    const wrapper = mount(GalleryField, {
      props: { modelValue: [{ path: '2026/gone.png' }] },
      ...panel,
    })
    await flushPromises()

    expect(wrapper.find('.wx-media-list__cell').classes()).toContain('is-broken')
    expect(wrapper.find('.wx-file-card__name').text()).toBe('gone.png')
    // No way into the library for a file that is not in it; the way out of the list stays.
    expect(wrapper.findAll('.wx-media-list__tools button')).toHaveLength(2)
  })

  it('tells the dialog how much room is left, and stops offering at the limit', async () => {
    // `max` rides in as an attribute, which is how a wrapper passes on what it never declared.
    const wrapper = mount(GalleryField, {
      props: { modelValue: [{ path: 'a.jpg' }] },
      attrs: { max: 2 },
      ...panel,
    })
    await flushPromises()

    picks(library['b.jpg']!)
    await wrapper.find('.wx-media-list__footer button').trigger('click')

    expect(vi.mocked(openMediaFiles)).toHaveBeenCalledWith({ accept: 'image', max: 1 })

    await wrapper.setProps({ modelValue: [{ path: 'a.jpg' }, { path: 'b.jpg' }] })

    expect(wrapper.find('.wx-media-list__footer button').attributes('disabled')).toBeDefined()
  })
})

describe('a list of files', () => {
  it('takes anything the field was told to take', async () => {
    const wrapper = mount(FilesField, {
      props: { modelValue: [] },
      attrs: { accept: 'document' },
      ...panel,
    })

    picks(file('notes.pdf', { extension: 'pdf', mime: 'application/pdf', type: 'document' }))
    await wrapper.find('.wx-media-list__footer button').trigger('click')

    expect(vi.mocked(openMediaFiles)).toHaveBeenCalledWith({ accept: 'document', max: null })
  })
})

describe('a single file', () => {
  it('stores one value rather than a list of one, and replaces it', async () => {
    const wrapper = mount(FileField, { props: { modelValue: null }, ...panel })

    picks(library['a.jpg']!)
    await wrapper.find('.wx-media-list__footer button').trigger('click')
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toEqual({ path: 'a.jpg' })

    await wrapper.setProps({ modelValue: { path: 'a.jpg' } })
    await flushPromises()

    expect(wrapper.find('.wx-media-list__footer button').attributes('disabled')).toBeDefined()
  })
})
