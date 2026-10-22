import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { localesKey, WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import FieldDialog from './FieldDialog.vue'
import type { InboxField, InboxForm } from './types'

const form = { id: 1, slug: 'contact', title: { en: 'Contact' } } as unknown as InboxForm

const phone: InboxField = {
  id: 5,
  form_id: 1,
  name: 'phone',
  key: 'phone',
  type: 'tel',
  title: { en: 'Phone' },
  placeholder: {},
  help: {},
  options: {},
  is_enabled: true,
  is_required: false,
  is_fullsize: true,
  in_table: false,
  position: 0,
}

function dialog(field: InboxField | null, taken: string[] = []) {
  const post = vi
    .fn()
    .mockImplementation((_url: string, body: object) =>
      Promise.resolve({ data: { ...phone, ...body } }),
    )
  const put = vi.fn().mockResolvedValue({ data: phone })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    http: { get: vi.fn(), post, put },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const wrapper = mount(FieldDialog, {
    props: { field, form, taken },
    attachTo: document.body,
    global: {
      plugins: [WebxUI],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: i18n,
        [localesKey as symbol]: { list: ref([{ code: 'ru' }, { code: 'en' }]), active: ref('ru') },
      },
    },
  })

  /** The control inside the form item with this label. */
  const control = (label: string) => {
    const item = wrapper
      .findAllComponents({ name: 'WxFormItem' })
      .find((one) => one.props('label') === label)

    if (item === undefined) throw new Error(`No field labelled ${label}`)

    return item.findComponent({ name: 'WxInput' })
  }

  const labels = () =>
    wrapper
      .findAllComponents({ name: 'WxFormItem' })
      .map((one) => one.props('label'))
      .filter((one): one is string => typeof one === 'string')

  return { wrapper, post, put, control, labels }
}

describe('WxInboxFieldDialog', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('asks for the type, then the label, and keeps the name for last, under “Advanced”', async () => {
    const { labels } = dialog(null)
    await flushPromises()

    const order = labels()
    expect(order.slice(0, 2)).toEqual(['Type', 'Label'])
    expect(order.at(-1)).toBe('Name')
    expect(document.body.textContent).toContain('Advanced')
    // The name is explained by what it is for, not by the row number a nameless field answers to.
    expect(document.body.textContent).not.toMatch(/\bf\d+\b/)
  })

  it('names a new field after its label until somebody writes a name', async () => {
    const { control, post, wrapper } = dialog(null, ['vash_telefon'])
    await flushPromises()

    control('Label').vm.$emit('update:modelValue', { ru: 'Ваш телефон' })
    await flushPromises()

    // Taken by another field of the form: the next one along.
    expect(control('Name').props('modelValue')).toBe('vash_telefon_2')
    expect(document.body.textContent).toContain('{{field.vash_telefon_2}}')

    // Transliterated by the label's language, as the server does it: Russian «Щука» is `schuka`,
    // not the `shchuka` of a table that knows no language.
    control('Label').vm.$emit('update:modelValue', { ru: 'Щука' })
    await flushPromises()

    expect(control('Name').props('modelValue')).toBe('schuka')

    control('Name').vm.$emit('update:modelValue', 'mobile')
    control('Label').vm.$emit('update:modelValue', { ru: 'Мобильный' })
    await flushPromises()

    expect(control('Name').props('modelValue')).toBe('mobile')

    await wrapper
      .findAllComponents({ name: 'WxButton' })
      .find((one) => one.text() === 'Save')!
      .trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith(
      '/api/cms/inbox/forms/1/fields',
      expect.objectContaining({ name: 'mobile' }),
    )
  })

  it('leaves the name of a field that has one, and warns before it is changed', async () => {
    const { control } = dialog(phone)
    await flushPromises()

    control('Label').vm.$emit('update:modelValue', { en: 'Your phone' })
    await flushPromises()
    expect(control('Name').props('modelValue')).toBe('phone')
    expect(document.querySelector('.wx-alert')).toBeNull()

    control('Name').vm.$emit('update:modelValue', 'mobile')
    await flushPromises()

    const warning = document.querySelector('.wx-alert')?.textContent ?? ''
    expect(warning).toContain('{{field.phone}}')
    expect(warning).toContain('{{field.mobile}}')
  })
})
