import { afterEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { adminKey, createI18n, i18nKey } from '@webx-ui/module-admin'
import { mediaMessages } from './messages'
import FileField from './FileField.vue'
import { openMediaPicker } from './openMediaPicker'
import type { MediaFile, MediaValue } from './types'

/*
 * The library is stood in for on both sides: the dialog that picks a file, and the endpoint that
 * says what a stored key points at now. What is under test is what the field keeps — the value it
 * always kept, `{ path, alt?, title? }` — and what the row says about it. The look of the row and
 * of the panel is checked in a browser: jsdom lays nothing out.
 */
vi.mock('./openMediaPicker', () => ({
  openMediaPicker: vi.fn(),
  openMediaFiles: vi.fn(),
  openMediaLibrary: vi.fn(),
}))

const library: Record<string, MediaFile> = {}

vi.mock('./api', () => ({
  createMediaApi: () => ({
    fileByPath: (path: string) => Promise.resolve(library[path] ?? null),
    thumb: (file: MediaFile) => file.thumb,
    directories: () => Promise.resolve([]),
  }),
}))

enableAutoUnmount(afterEach)

function file(path: string, extra: Partial<MediaFile> = {}): MediaFile {
  const name = path.split('/').pop()!

  return {
    id: path.length,
    directory_id: 1,
    name,
    file_name: name,
    extension: name.split('.').pop()!,
    mime: 'application/pdf',
    type: 'document',
    size: 62_464,
    width: null,
    height: null,
    path,
    url: `/storage/${path}`,
    thumb: null,
    source: null,
    editable: false,
    has_original: false,
    duplicate: false,
    created_at: null,
    ...extra,
  }
}

library['media/ab/floor-plan.pdf'] = file('media/ab/floor-plan.pdf')
library['media/cd/new-plan.pdf'] = file('media/cd/new-plan.pdf')

function panel(can = true) {
  const i18n = createI18n({ locale: 'en' })
  i18n.defaults('webx-media', mediaMessages)

  return {
    attachTo: document.body,
    global: {
      provide: {
        [adminKey as symbol]: { can: () => can, http: {}, apiPath: '/api', i18n },
        [i18nKey as symbol]: i18n,
      },
    },
  }
}

function field(modelValue: MediaValue | null, props: Record<string, unknown> = {}, can = true) {
  return mount(FileField, { props: { modelValue, ...props }, ...panel(can) })
}

const rowText = (wrapper: ReturnType<typeof field>, part: string) =>
  wrapper.find(`.wx-field-row__${part}`).exists()
    ? wrapper.get(`.wx-field-row__${part}`).text()
    : null

const body = () => document.body.querySelector<HTMLElement>('.wx-file-field__panel')

function button(text: string): HTMLButtonElement | undefined {
  return [...(body()?.querySelectorAll('button') ?? [])].find(
    (one) => one.textContent?.trim() === text,
  )
}

async function openPanel(wrapper: ReturnType<typeof field>): Promise<void> {
  await wrapper.get('button.wx-file-field__row').trigger('click')
  await flushPromises()
}

const last = (wrapper: ReturnType<typeof field>) =>
  wrapper.emitted('update:modelValue')?.at(-1)?.[0]

describe('WxFileField', () => {
  it('asks for a file when the field is empty, and keeps the key of the one chosen', async () => {
    const wrapper = field(null, { accept: 'document' })
    await flushPromises()

    expect(wrapper.get('button.wx-file-field__row').classes()).toContain('is-empty')
    expect(rowText(wrapper, 'title')).toBe('Choose a file')
    expect(wrapper.find('.wx-file-field__clear').exists()).toBe(false)

    vi.mocked(openMediaPicker).mockResolvedValueOnce(library['media/ab/floor-plan.pdf']!)
    await openPanel(wrapper)
    button('Choose from the library')!.click()
    await flushPromises()

    expect(vi.mocked(openMediaPicker)).toHaveBeenCalledWith({ accept: 'document' })
    // The key and nothing the library said: the address is worked out on every read.
    expect(last(wrapper)).toEqual({ path: 'media/ab/floor-plan.pdf' })
  })

  it('opens a value saved by the old card as it is, and says what the file is', async () => {
    const wrapper = field({ path: 'media/ab/floor-plan.pdf', title: { en: 'Floor plan' } })
    await flushPromises()

    expect(rowText(wrapper, 'title')).toBe('floor-plan.pdf')
    expect(rowText(wrapper, 'detail')).toBe('PDF · 61 kB')
    // Drawing a value is not changing it.
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('keeps the caption when the file is replaced', async () => {
    const wrapper = field({ path: 'media/ab/floor-plan.pdf', title: { en: 'Floor plan' } })
    await flushPromises()

    vi.mocked(openMediaPicker).mockResolvedValueOnce(library['media/cd/new-plan.pdf']!)
    await openPanel(wrapper)
    button('Replace')!.click()
    await flushPromises()

    expect(last(wrapper)).toEqual({ path: 'media/cd/new-plan.pdf', title: { en: 'Floor plan' } })
  })

  it('says so when the library no longer has the file, and still clears it', async () => {
    const wrapper = field({ path: 'media/zz/gone.pdf' })
    await flushPromises()

    expect(wrapper.get('button.wx-file-field__row').classes()).toContain('is-missing')
    expect(rowText(wrapper, 'title')).toBe('gone.pdf')
    expect(rowText(wrapper, 'detail')).toBe('No longer in the library')

    await wrapper.get('.wx-file-field__clear').trigger('click')

    expect(last(wrapper)).toBeNull()
  })

  it('offers «Download instead of opening» only where the node does, and keeps it', async () => {
    const plain = field({ path: 'media/ab/floor-plan.pdf' })
    await flushPromises()
    await openPanel(plain)

    expect(body()!.textContent).not.toContain('Download instead of opening')
    plain.unmount()

    const wrapper = field({ path: 'media/ab/floor-plan.pdf' }, { download: true })
    await flushPromises()
    await openPanel(wrapper)

    const box = body()!.querySelector<HTMLElement>('.wx-checkbox, [role="checkbox"]')
    expect(body()!.textContent).toContain('Download instead of opening')

    box!.click()
    await flushPromises()

    expect(last(wrapper)).toEqual({ path: 'media/ab/floor-plan.pdf', download: true })
  })

  it('without the library keeps the value and the caption, but offers nothing to choose from', async () => {
    const wrapper = field({ path: 'media/ab/floor-plan.pdf' }, {}, false)
    await flushPromises()

    // Not asked: a refusal would read as «no longer in the library».
    expect(rowText(wrapper, 'title')).toBe('floor-plan.pdf')
    expect(rowText(wrapper, 'detail')).toBe('media/ab/floor-plan.pdf')

    await openPanel(wrapper)

    expect(button('Choose from the library')).toBeUndefined()
    expect(button('Upload')).toBeUndefined()
    expect(body()!.textContent).toContain('The file library is not open to you')
  })

  it('closes on Escape and gives the focus back to the row', async () => {
    const wrapper = field({ path: 'media/ab/floor-plan.pdf' })
    await flushPromises()
    await openPanel(wrapper)

    expect(body()).not.toBeNull()

    document.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }),
    )
    await flushPromises()
    await nextTick()

    expect(body()).toBeNull()
    expect(wrapper.get('button.wx-file-field__row').attributes('aria-expanded')).toBe('false')
  })

  it('removes the file from the panel and closes it', async () => {
    const wrapper = field({ path: 'media/ab/floor-plan.pdf' })
    await flushPromises()
    await openPanel(wrapper)

    button('Remove')!.click()
    await flushPromises()

    expect(last(wrapper)).toBeNull()
    expect(body()).toBeNull()
  })
})
