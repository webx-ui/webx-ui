import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import FormField from './FormField.vue'
import { inbox } from './module'
import type { FormCandidate } from './types'

const FORMS: FormCandidate[] = [
  { id: 1, title: 'Feedback', subtitle: 'feedback', slug: 'feedback', thumb: null, visible: true },
  {
    id: 2,
    title: 'Call me back',
    subtitle: 'callback',
    slug: 'callback',
    thumb: null,
    visible: false,
  },
]

function field(value: string | null, fail = false) {
  const i18n = createI18n()

  // What `FormTarget` answers: by title or slug, the exact slug first.
  const get = vi.fn((_url: string, options?: { query?: Record<string, unknown> }) => {
    if (fail) return Promise.reject(new Error('offline'))

    const q = String(options?.query?.q ?? '').toLowerCase()
    const found = FORMS.filter(
      (one) => one.title.toLowerCase().includes(q) || one.slug!.includes(q),
    ).sort((one, two) => Number(two.slug === q) - Number(one.slug === q))

    return Promise.resolve({ data: found })
  })

  const admin = { apiPath: '/api/cms', http: { get }, i18n } as unknown as AdminContext

  const wrapper = mount(FormField, {
    props: { modelValue: value },
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  const last = () => wrapper.emitted('update:modelValue')?.at(-1)?.[0]

  return { wrapper, get, last }
}

describe('WxInboxFormField', () => {
  it('is the type wx-inbox-form the Inbox brings to every screen and block', () => {
    expect(inbox().types?.['wx-inbox-form']).toMatchObject({ component: FormField, kind: 'field' })
  })

  it('names a slug written before the picker by the form that has it', async () => {
    const { wrapper, get } = field('feedback')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/relations/inbox-form', { query: { q: 'feedback' } })
    expect(wrapper.find('.wx-inbox-form-field__title').text()).toBe('Feedback')
    expect(wrapper.find('.wx-inbox-form-field__slug').text()).toBe('feedback')
    expect(wrapper.find('.wx-badge').exists()).toBe(false)
  })

  it('keeps a slug no form has, and says so', async () => {
    const { wrapper, last } = field('contakt')
    await flushPromises()

    expect(wrapper.find('.wx-inbox-form-field__title').text()).toBe('contakt')
    expect(wrapper.find('.wx-badge').text()).toBe('No such form')
    expect(last()).toBeUndefined()
  })

  it('marks a form that is switched off', async () => {
    const { wrapper } = field('callback')
    await flushPromises()

    expect(wrapper.find('.wx-badge').text()).toBe('Switched off')
  })

  it('offers the forms by name and slug, and keeps the slug of the one picked', async () => {
    const { wrapper, last } = field(null)
    await flushPromises()

    expect(wrapper.find('.wx-inbox-form-field__empty').text()).toBe('No form')

    const box = wrapper.findComponent({ name: 'WxAutocomplete' })
    box.vm.$emit('open')
    await flushPromises()

    const options = box.props('options') as { value: string; description: string; id: string }[]
    expect(options.map((one) => [one.value, one.description])).toEqual([
      ['Feedback', 'feedback'],
      ['Call me back', 'callback · Switched off'],
    ])

    box.vm.$emit('select', options[0])
    expect(last()).toBe('feedback')
  })

  it('is emptied by its remove button: no form', async () => {
    const { wrapper, last } = field('feedback')
    await flushPromises()

    await wrapper.get('.wx-inbox-form-field__chosen button').trigger('click')
    expect(last()).toBeNull()
  })

  it('shows the slug as it is when the forms do not come', async () => {
    const { wrapper } = field('feedback', true)
    await flushPromises()

    expect(wrapper.find('.wx-inbox-form-field__title').text()).toBe('feedback')
    expect(wrapper.find('.wx-badge').exists()).toBe(false)
  })
})
