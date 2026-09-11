import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxUpload from './Upload.vue'
import type { UploadFile } from './types'

function file(name: string, { size = 1000, type = 'image/png' } = {}) {
  const made = new File(['x'], name, { type })
  Object.defineProperty(made, 'size', { value: size })
  return made
}

/** Hands files to the hidden input the way a file picker would. */
async function pick(wrapper: ReturnType<typeof mount>, files: File[]) {
  const input = wrapper.get('input[type=file]').element as HTMLInputElement
  Object.defineProperty(input, 'files', { value: files, configurable: true })
  await wrapper.get('input[type=file]').trigger('change')
  await nextTick()
}

describe('WxUpload', () => {
  it('adds what it is given', async () => {
    const wrapper = mount(WxUpload)
    await pick(wrapper, [file('one.png'), file('two.png')])

    expect(wrapper.emitted('add')?.[0][0]).toHaveLength(2)
    expect(wrapper.findAll('.wx-upload__file')).toHaveLength(2)
  })

  it('keeps only the last file when it takes one at a time', async () => {
    const wrapper = mount(WxUpload, { props: { multiple: false } })
    await pick(wrapper, [file('one.png')])
    await pick(wrapper, [file('two.png')])

    expect(wrapper.findAll('.wx-upload__file')).toHaveLength(1)
    expect(wrapper.get('.wx-upload__file-name').text()).toBe('two.png')
  })

  it('refuses a file that is too big, and says why', async () => {
    const wrapper = mount(WxUpload, { props: { maxSize: 500 } })
    await pick(wrapper, [file('huge.png', { size: 900 })])

    expect(wrapper.emitted('add')).toBeUndefined()
    expect(wrapper.emitted('reject')?.[0][1]).toBe('size')
  })

  it('refuses a file of the wrong kind', async () => {
    const wrapper = mount(WxUpload, { props: { accept: 'image/*' } })
    await pick(wrapper, [file('notes.pdf', { type: 'application/pdf' })])

    expect(wrapper.emitted('reject')?.[0][1]).toBe('type')
  })

  it('matches an extension as well as a media type', async () => {
    const wrapper = mount(WxUpload, { props: { accept: '.pdf' } })
    await pick(wrapper, [file('notes.pdf', { type: 'application/pdf' })])

    expect(wrapper.emitted('add')?.[0][0]).toHaveLength(1)
  })

  it('stops at the limit', async () => {
    const wrapper = mount(WxUpload, { props: { max: 2 } })
    await pick(wrapper, [file('a.png'), file('b.png'), file('c.png')])

    expect(wrapper.findAll('.wx-upload__file')).toHaveLength(2)
    expect(wrapper.emitted('reject')?.[0][1]).toBe('count')
  })

  it('asks before adding, and takes no for an answer', async () => {
    const wrapper = mount(WxUpload, { props: { beforeAdd: () => false } })
    await pick(wrapper, [file('a.png')])

    expect(wrapper.emitted('add')).toBeUndefined()
    expect(wrapper.emitted('reject')?.[0][1]).toBe('rejected')
  })

  it('takes a file back off the list', async () => {
    const wrapper = mount(WxUpload)
    await pick(wrapper, [file('a.png')])

    await wrapper.get('.wx-upload__file-remove').trigger('click')

    expect(wrapper.findAll('.wx-upload__file')).toHaveLength(0)
    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('shows progress for a file that is on its way', () => {
    const files: UploadFile[] = [
      { id: '1', name: 'a.png', size: 10, type: 'image/png', status: 'uploading', progress: 40 },
    ]
    const wrapper = mount(WxUpload, { props: { modelValue: files } })

    expect(wrapper.find('.wx-upload__file-progress').exists()).toBe(true)
  })

  it('shows what went wrong instead of the size', () => {
    const files: UploadFile[] = [
      {
        id: '1',
        name: 'a.png',
        size: 10,
        type: 'image/png',
        status: 'error',
        progress: 0,
        error: 'Server said no',
      },
    ]
    const wrapper = mount(WxUpload, { props: { modelValue: files } })

    expect(wrapper.get('.wx-upload__file-error').text()).toBe('Server said no')
    expect(wrapper.find('.wx-upload__file-size').exists()).toBe(false)
  })

  it('adds nothing at all while disabled', async () => {
    const wrapper = mount(WxUpload, { props: { disabled: true } })
    await pick(wrapper, [file('a.png')])

    expect(wrapper.emitted('add')).toBeUndefined()
  })
})
