import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { adminKey, type AdminContext } from '../admin'
import { createI18n, i18nKey } from '../i18n'
import { adminMessages } from '../messages'
import { adminTypes } from '../screenTypes'
import { collectionSources, defaultMarkup, normaliseCollection, type CollectionValue } from './api'
import CollectionField from './CollectionField.vue'

const SOURCES = [
  { key: 'faq', title: 'FAQ', categories: 'faq/categories', markup: true, relations: [] },
  { key: 'team', title: 'Team', categories: null, markup: false, relations: [] },
  {
    key: 'recipes',
    title: 'Recipes',
    categories: null,
    markup: false,
    relations: [{ key: 'service', title: 'Services' }],
  },
  {
    key: 'articles',
    title: 'Articles',
    categories: null,
    markup: false,
    relations: [
      { key: 'service', title: 'Services' },
      { key: 'recipe', title: 'Recipes' },
    ],
  },
]

const SERVICES = [
  { id: 7, title: 'Landing page', subtitle: null, thumb: null, visible: true },
  { id: 8, title: 'Company website', subtitle: null, thumb: null, visible: true },
]

function panel() {
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const get = vi.fn((url: string) =>
    Promise.resolve(
      url.endsWith('/collections')
        ? { data: SOURCES }
        : url.includes('/relations/')
          ? { data: SERVICES.filter((one) => !url.includes('ids[]') || url.includes(`=${one.id}`)) }
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
      related: null,
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
    ).toEqual({ categories: [3, 5], related: null, limit: 100, filter: false, markup: false })

    expect(normaliseCollection({ limit: 0 }).limit).toBeNull()
    expect(normaliseCollection({ limit: 2.5 }).limit).toBeNull()
  })

  it('keeps a relation filter only with a target and something chosen of it', () => {
    expect(
      normaliseCollection({ related: { type: 'service', ids: [9, '7', 9, 0] } }).related,
    ).toEqual({ type: 'service', ids: [7, 9] })
    expect(normaliseCollection({ related: { type: 'service', ids: [] } }).related).toBeNull()
    expect(normaliseCollection({ related: { ids: [7] } }).related).toBeNull()
    expect(normaliseCollection({ related: [7] }).related).toBeNull()
  })

  it('reads the targets of a source whether they come named or as bare keys', async () => {
    const { admin, get } = panel()
    get.mockResolvedValueOnce({
      data: [
        { key: 'old', title: 'Old', categories: null, markup: false },
        { ...SOURCES[2], relations: ['service'] },
      ],
    } as never)

    const [old, recipes] = await collectionSources(admin)
    expect(old!.relations).toEqual([])
    expect(recipes!.relations).toEqual([{ key: 'service', title: 'service' }])
  })

  it('marks the whole collection up by default, and a part of it not', () => {
    expect(defaultMarkup(normaliseCollection({}))).toBe(true)
    expect(defaultMarkup(normaliseCollection({ categories: [3] }))).toBe(false)
    expect(defaultMarkup(normaliseCollection({ related: { type: 'service', ids: [7] } }))).toBe(
      false,
    )
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
    expect(last()).toEqual({
      categories: [3, 5],
      related: null,
      limit: null,
      filter: false,
      markup: null,
    })

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

  it('offers no relation filter to a source without relations', async () => {
    const { wrapper } = field('faq')
    await flushPromises()

    expect(wrapper.findComponent({ name: 'WxRelationsField' }).exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Only related to')
  })

  it('narrows to the records related to what is chosen, with one target and no box for it', async () => {
    const { wrapper, get, last } = field('recipes', { related: { type: 'service', ids: [8] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Only related to “Services”')
    expect(wrapper.findComponent({ name: 'WxSelect' }).exists()).toBe(false)
    expect(get).toHaveBeenCalledWith('/api/cms/relations/service?ids[]=8')

    const related = wrapper.findComponent({ name: 'WxRelationsField' })
    expect(related.props()).toMatchObject({ target: 'service', sortable: false })
    expect(wrapper.find('.wx-relations-field__title').text()).toBe('Company website')

    related.vm.$emit('update:modelValue', [8, 7])
    expect(last()).toMatchObject({ related: { type: 'service', ids: [7, 8] } })

    related.vm.$emit('update:modelValue', [])
    expect(last()).toMatchObject({ related: null })
  })

  it('asks which target first when there are several, and starts afresh on another', async () => {
    const { wrapper, last } = field('articles', { related: { type: 'service', ids: [7] } })
    await flushPromises()

    const target = wrapper.findComponent({ name: 'WxSelect' })
    expect(target.props('modelValue')).toBe('service')
    expect(target.props('options')).toEqual([
      { label: 'Services', value: 'service' },
      { label: 'Recipes', value: 'recipe' },
    ])

    target.vm.$emit('update:modelValue', 'recipe')
    expect(last()).toMatchObject({ related: null })

    await wrapper.setProps({ modelValue: last()! })
    // The target stays chosen while nothing of it is: the value cannot say so, the field can.
    expect(wrapper.findComponent({ name: 'WxRelationsField' }).props('target')).toBe('recipe')
  })
})
