import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { adminKey } from '@webx-ui/module-admin'
import MediaField from './MediaField.vue'
import { openMediaPicker } from './openMediaPicker'

/*
 * `wx-media` against the library's permission (choice 2(a) of the permissions spec): the dialog
 * and the question about a stored key are stood in for, and what is under test is whether the
 * field reaches for them at all.
 */
vi.mock('./openMediaPicker', () => ({ openMediaPicker: vi.fn() }))

const fileByPath = vi.fn(() => Promise.resolve(null))

vi.mock('./api', () => ({ createMediaApi: () => ({ fileByPath }) }))

function panel(can: (permission: string) => boolean) {
  return { global: { provide: { [adminKey as symbol]: { can } } } }
}

beforeEach(() => {
  vi.mocked(openMediaPicker).mockReset()
  fileByPath.mockClear()
})

describe('a picture field', () => {
  it('opens the library for somebody who may browse it', async () => {
    const wrapper = mount(MediaField, {
      props: { modelValue: null },
      ...panel((permission) => permission === 'media.view'),
    })

    await wrapper.find('.wx-media-field__pick').trigger('click')

    expect(vi.mocked(openMediaPicker)).toHaveBeenCalledWith({ accept: 'image' })
  })

  it('lets `media.manage` choose too: it names no `view`, and still has to see', async () => {
    const wrapper = mount(MediaField, {
      props: { modelValue: null },
      ...panel((permission) => permission === 'media.manage'),
    })

    await wrapper.find('.wx-media-field__pick').trigger('click')

    expect(vi.mocked(openMediaPicker)).toHaveBeenCalled()
  })

  it('says the library is closed instead of opening a dialog that would answer 403', async () => {
    const wrapper = mount(MediaField, { props: { modelValue: null }, ...panel(() => false) })

    const pick = wrapper.find('.wx-media-field__pick')

    expect(pick.attributes('disabled')).toBeDefined()
    // The bare key: a test installs no dictionary.
    expect(pick.text()).toBe('field.locked')

    await pick.trigger('click')

    expect(vi.mocked(openMediaPicker)).not.toHaveBeenCalled()
  })

  it('keeps a chosen picture, and does not ask the library about its key', async () => {
    const wrapper = mount(MediaField, {
      props: { modelValue: { path: '2026/cover.jpg' } },
      ...panel(() => false),
    })
    await flushPromises()

    expect(fileByPath).not.toHaveBeenCalled()
    expect(wrapper.find('.wx-media-field__picture').attributes('disabled')).toBeDefined()
    // Clearing the field is the record's business, not the library's.
    expect(wrapper.find('.wx-media-field__bar').exists()).toBe(true)
  })
})
