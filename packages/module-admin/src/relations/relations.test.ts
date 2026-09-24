import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import { adminKey, type AdminContext } from '../admin'
import { HttpError } from '../http'
import { createI18n, i18nKey } from '../i18n'
import { adminMessages } from '../messages'
import { adminTypes } from '../screenTypes'
import { normaliseRelations, relationOwnerKey, type RelationCandidate } from './api'
import RelationsField from './RelationsField.vue'

const SERVICES: RelationCandidate[] = [
  { id: 1, title: 'Landing page', subtitle: 'Websites', thumb: null, visible: true },
  { id: 2, title: 'Company website', subtitle: null, thumb: '/c.svg', visible: true },
  { id: 3, title: 'Online catalogue', subtitle: null, thumb: null, visible: false },
  { id: 5, title: 'Old tariff', subtitle: null, thumb: null, visible: false, trashed: true },
  { id: 4, title: 'Site maintenance', subtitle: null, thumb: null, visible: true },
]

function panel(refuse = false) {
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const get = vi.fn((url: string, options?: { query?: Record<string, unknown> }) => {
    if (refuse) return Promise.reject(new HttpError('Forbidden', 403))

    const asked = [...url.matchAll(/ids\[\]=(\d+)/g)].map((match) => Number(match[1]))

    if (asked.length > 0) {
      return Promise.resolve({ data: SERVICES.filter((one) => asked.includes(one.id)) })
    }

    const q = String(options?.query?.q ?? '').toLowerCase()

    // The search never offers the bin, as the server does not (§3.7).
    return Promise.resolve({
      data: SERVICES.filter((one) => one.trashed !== true && one.title.toLowerCase().includes(q)),
    })
  })

  const admin = { apiPath: '/api/cms', http: { get }, i18n } as unknown as AdminContext

  return { admin, get, i18n }
}

function field(
  value: number[] | null,
  props: Record<string, unknown> = {},
  options: { refuse?: boolean; owner?: { type: string; id: number } } = {},
) {
  const { admin, get, i18n } = panel(options.refuse)

  // The owner is provided the way a record's editor provides it.
  const owner =
    options.owner === undefined
      ? {}
      : { [relationOwnerKey as symbol]: { type: options.owner.type, id: ref(options.owner.id) } }

  const wrapper = mount(RelationsField, {
    props: { target: 'service', modelValue: value, ...props },
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n, ...owner } },
  })

  const last = () => wrapper.emitted('update:modelValue')?.at(-1)?.[0] as number[] | undefined
  const titles = () => wrapper.findAll('.wx-relations-field__title').map((one) => one.text())

  return { wrapper, get, last, titles }
}

describe('the value of wx-relations', () => {
  it('is ids in the order given, without repeats or anything that is not one', () => {
    expect(normaliseRelations([4, '2', 4, 0, -1, 'x', 1.5, 7])).toEqual([4, 2, 7])
    expect(normaliseRelations(null)).toEqual([])
    expect(normaliseRelations({ 0: 1 })).toEqual([])
  })
})

describe('WxRelationsField', () => {
  it('is the panel type wx-relations, drawn across the whole form', () => {
    expect(adminTypes['wx-relations']).toMatchObject({
      component: RelationsField,
      kind: 'field',
      wide: true,
    })
  })

  it('names the chosen ones in one question, in the order they were chosen', async () => {
    const { get, titles } = field([2, 1])
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/relations/service?ids[]=2&ids[]=1')
    expect(titles()).toEqual(['Company website', 'Landing page'])
  })

  it('marks a chosen one the site does not show, and one the server does not know', async () => {
    const { wrapper } = field([3, 9])
    await flushPromises()

    const rows = wrapper.findAll('.wx-sortable-list__row')
    expect(rows[0]!.text()).toContain('Online catalogue')
    expect(rows[0]!.text()).toContain('Not on the site')
    expect(rows[1]!.text()).toContain('#9')
    expect(rows[1]!.text()).toContain('Not found')
  })

  it('says where a chosen one in the bin is, rather than only that it is hidden', async () => {
    const { wrapper } = field([5])
    await flushPromises()

    const row = wrapper.find('.wx-sortable-list__row')
    expect(row.text()).toContain('In the bin')
    expect(row.text()).not.toContain('Not on the site')
  })

  it('adds what is picked from the search, and offers neither what is chosen nor itself', async () => {
    const { wrapper, get, last } = field([1], {}, { owner: { type: 'service', id: 4 } })
    await flushPromises()

    const box = wrapper.findComponent({ name: 'WxAutocomplete' })
    box.vm.$emit('search', '')
    await flushPromises()

    expect(get).toHaveBeenLastCalledWith('/api/cms/relations/service', { query: { q: '' } })
    expect(box.props('options').map((one: { id: number }) => one.id)).toEqual([2, 3])
    expect(box.props('options')[1]).toMatchObject({ description: 'Not on the site' })

    box.vm.$emit('select', box.props('options')[0])
    expect(last()).toEqual([1, 2])
  })

  it('does not ask again for a name the search already brought', async () => {
    const { wrapper, get, titles } = field([])
    await flushPromises()

    const box = wrapper.findComponent({ name: 'WxAutocomplete' })
    box.vm.$emit('search', 'company')
    await flushPromises()
    box.vm.$emit('select', box.props('options')[0])
    await wrapper.setProps({ modelValue: [2] })
    await flushPromises()

    expect(get).toHaveBeenCalledTimes(1)
    expect(titles()).toEqual(['Company website'])
  })

  it('takes one out and keeps the order of the rest', async () => {
    const { wrapper, last } = field([4, 2, 1])
    await flushPromises()

    await wrapper
      .findAll('.wx-sortable-list__row')[1]!
      .get('button[aria-label="Remove"]')
      .trigger('click')

    expect(last()).toEqual([4, 1])
  })

  it('stops adding at the maximum and says why', async () => {
    const { wrapper } = field([1, 2], { max: 2 })
    await flushPromises()

    const box = wrapper.findComponent({ name: 'WxAutocomplete' })
    expect(box.props('disabled')).toBe(true)
    expect(box.props('placeholder')).toBe('No more than 2 can be chosen.')
  })

  it('is only a list for an administrator who may not see the target', async () => {
    const { wrapper, titles } = field([2, 1], {}, { refuse: true })
    await flushPromises()

    expect(titles()).toEqual(['#2', '#1'])
    expect(wrapper.text()).toContain('You cannot see these records')
    expect(wrapper.findComponent({ name: 'WxAutocomplete' }).exists()).toBe(false)
    expect(wrapper.find('button[aria-label="Remove"]').exists()).toBe(false)
    expect(wrapper.findComponent({ name: 'WxSortableList' }).props('disabled')).toBe(true)
  })
})
