import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { adminKey, type AdminContext } from '../admin'
import { createI18n, i18nKey } from '../i18n'
import { adminMessages } from '../messages'
import { adminTypes } from '../screenTypes'
import { collectionSources, defaultMarkup, normaliseCollection, type CollectionValue } from './api'
import CollectionField from './CollectionField.vue'

const SOURCES = [
  { key: 'faq', title: 'FAQ', categories: 'faq/categories', markup: true },
  { key: 'team', title: 'Team', categories: null, markup: false },
]

function panel() {
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const get = vi.fn((url: string) =>
    Promise.resolve(
      url.endsWith('/collections')
        ? { data: SOURCES }
        : {
            data: [
              { id: 3, name: 'Payment' },
              { id: 5, name: 'Delivery' },
            ],
            prefix: null,
          },
    ),
  )

  const admin = { apiPath: '/api/cms', http: { get }, i18n } as unknown as AdminContext

  return { admin, get, i18n }
}

function field(source: string, value: Partial<CollectionValue> | null = null) {
  const { admin, get, i18n } = panel()

  const wrapper = mount(CollectionField, {
    props: { source, modelValue: value as CollectionValue | null },
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  const last = () =>
    (wrapper.emitted('update:modelValue')?.at(-1) as [CollectionValue] | undefined)?.[0]

  return { wrapper, get, last }
}

describe('the value of wx-collection', () => {
  it('comes to the four keys the server writes, whatever arrived', () => {
    expect(normaliseCollection(null)).toEqual({
      categories: [],
      limit: null,
      filter: false,
      markup: null,
    })

    expect(
      normaliseCollection({
        categories: [5, '3', 5, -1, 'x'],
        limit: 500,
        filter: 1,
        markup: false,
      }),
    ).toEqual({ categories: [3, 5], limit: 100, filter: false, markup: false })

    expect(normaliseCollection({ limit: 0 }).limit).toBeNull()
    expect(normaliseCollection({ limit: 2.5 }).limit).toBeNull()
  })

  it('marks the whole collection up by default, and a part of it not', () => {
    expect(defaultMarkup(normaliseCollection({}))).toBe(true)
    expect(defaultMarkup(normaliseCollection({ categories: [3] }))).toBe(false)
  })

  it('asks for the sources once per panel, and again after a failure', async () => {
    const { admin, get } = panel()

    await Promise.all([collectionSources(admin), collectionSources(admin)])
    expect(get).toHaveBeenCalledTimes(1)

    const failing = panel()
    failing.get.mockRejectedValueOnce(new Error('offline'))

    await expect(collectionSources(failing.admin)).rejects.toThrow('offline')
    await expect(collectionSources(failing.admin)).resolves.toEqual(SOURCES)
  })
})

describe('WxCollectionField', () => {
  it('is the panel type wx-collection, drawn across the whole form', () => {
    expect(adminTypes['wx-collection']).toMatchObject({
      component: CollectionField,
      kind: 'field',
      wide: true,
    })
  })

  it('offers the categories of its source, and none of them means all', async () => {
    const { wrapper, get } = field('faq')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/collections')
    expect(get).toHaveBeenCalledWith('/api/cms/faq/categories')
    expect(wrapper.text()).toContain('Shows records from “FAQ”.')

    const select = wrapper.findComponent({ name: 'WxSelect' })
    expect(select.props('options')).toEqual([
      { label: 'Payment', value: 3 },
      { label: 'Delivery', value: 5 },
    ])
    expect(select.props('placeholder')).toBe('All categories')
  })

  it('writes the whole value on every change', async () => {
    const { wrapper, last } = field('faq')
    await flushPromises()

    wrapper.findComponent({ name: 'WxSelect' }).vm.$emit('update:modelValue', [5, 3])
    expect(last()).toEqual({ categories: [3, 5], limit: null, filter: false, markup: null })

    wrapper.findComponent({ name: 'WxInputNumber' }).vm.$emit('update:modelValue', 6)
    expect(last()).toMatchObject({ limit: 6 })
  })

  it('keeps a chosen category that is gone, by number', async () => {
    const { wrapper } = field('faq', { categories: [3, 9] })
    await flushPromises()

    const select = wrapper.findComponent({ name: 'WxSelect' })
    expect(select.props('options')).toContainEqual({ label: '#9', value: 9 })
    // "All categories" beside a chosen one would say two things at once.
    expect(select.props('placeholder')).toBe('')
  })

  it('shows the markup the rule gives until somebody sets it, and leads back to the rule', async () => {
    const { wrapper, last } = field('faq', { categories: [3] })
    await flushPromises()

    const switches = () => wrapper.findAllComponents({ name: 'WxSwitch' })
    const markup = () => switches().at(-1)!

    expect(markup().props('modelValue')).toBe(false)
    expect(wrapper.text()).toContain('By default it is off')
    expect(wrapper.text()).not.toContain('Back to the default')

    markup().vm.$emit('update:modelValue', true)
    expect(last()).toMatchObject({ categories: [3], markup: true })

    await wrapper.setProps({ modelValue: last()! })
    expect(markup().props('modelValue')).toBe(true)

    const reset = wrapper.findAll('button').find((one) => one.text() === 'Back to the default')!
    await reset.trigger('click')
    expect(last()).toMatchObject({ markup: null })
  })

  it('says it is on by default for the whole collection', async () => {
    const { wrapper } = field('faq')
    await flushPromises()

    expect(wrapper.findAllComponents({ name: 'WxSwitch' }).at(-1)!.props('modelValue')).toBe(true)
    expect(wrapper.text()).toContain('By default it is on')
  })

  it('draws neither categories, filter nor markup for a source that has none of them', async () => {
    const { wrapper, get } = field('team')
    await flushPromises()

    expect(get).toHaveBeenCalledTimes(1)
    expect(wrapper.findComponent({ name: 'WxSelect' }).exists()).toBe(false)
    expect(wrapper.findAllComponents({ name: 'WxSwitch' })).toHaveLength(0)
    expect(wrapper.findComponent({ name: 'WxInputNumber' }).exists()).toBe(true)
  })

  it('says so when the source is not there for this administrator', async () => {
    const { wrapper } = field('reviews')
    await flushPromises()

    expect(wrapper.text()).toContain('Records from “reviews” cannot be chosen here')
    expect(wrapper.findComponent({ name: 'WxInputNumber' }).exists()).toBe(false)
  })
})
