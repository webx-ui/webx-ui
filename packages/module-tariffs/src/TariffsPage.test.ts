import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory, type Router } from 'vue-router'
import {
  adminKey,
  adminMessages,
  adminTypes,
  createI18n,
  i18nKey,
  type AdminContext,
} from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import type * as Core from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import TariffsPage from './TariffsPage.vue'
import type { TariffDetail, TariffRow, TariffsList } from './types'

const { confirm } = vi.hoisted(() => ({ confirm: vi.fn() }))

/* The dialog itself is the core's; what is checked here is that the form asks at all. */
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof Core>()),
  confirm,
}))

function row(id: number, name: string, over: Partial<TariffRow> = {}): TariffRow {
  return {
    id,
    name,
    badge: null,
    price: null,
    currency: null,
    symbol: null,
    period: null,
    price_text: null,
    featured: false,
    published: true,
    position: id,
    categories: [],
    updated_at: '2026-09-28T09:00:00+00:00',
    deleted_at: null,
    ...over,
  }
}

const list: TariffsList = {
  data: [
    row(1, 'Combo Starter', {
      badge: '30 HOURS / 25$',
      price: 750,
      currency: 'USD',
      symbol: '$',
      period: '/mo',
      categories: [{ id: 3, title: 'For business' }],
    }),
    row(2, 'Combo Growth', { price: 1380.5, currency: 'USD', symbol: '$', featured: true }),
    // Words instead of a number, and not published yet.
    row(3, 'Combo Enterprise', { price_text: 'On request', published: false }),
  ],
  filters: { categories: [{ id: 3, title: 'For business' }] },
}

function detail(id: number, name: string): TariffDetail {
  return {
    tariff: { id, name, published: true, deleted_at: null },
    values: {
      name: { en: name },
      features: [{ text: { en: 'Design' } }, { text: { en: 'SEO' } }],
      published: true,
      categories: [],
    },
  }
}

/* A slice of `tariffs.form`: a name to type, and the list whose lines are refused one by one. */
const screen: ScreenNode[] = [
  { id: 'name', type: 'wx-input', name: 'name', localized: true },
  {
    id: 'features',
    type: 'wx-repeater',
    name: 'features',
    children: [{ id: 'feature-text', type: 'wx-input', name: 'text', localized: true }],
  },
  { id: 'published', type: 'wx-switch', name: 'published' },
]

let router: Router

async function panel(query = '', can = true) {
  const get = vi.fn().mockImplementation((url: string) => {
    const one = /\/tariffs\/(\d+)$/.exec(url)

    return Promise.resolve(one ? { data: detail(Number(one[1]), 'Combo Starter') } : list)
  })
  const post = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/reorder')) return Promise.resolve(undefined)
    if (url.endsWith('/restore')) return Promise.resolve({ data: row(2, 'Combo Growth') })

    return Promise.resolve({ data: detail(9, 'Brand new') })
  })
  const put = vi.fn().mockResolvedValue({ data: detail(1, 'Combo Starter Plus') })
  const i18n = createI18n()
  // What the panel seeds before any screen: the words of the order are the panel's, not ours.
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put, delete: vi.fn().mockResolvedValue(undefined) },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => can,
    types: { ...coreTypes, ...adminTypes },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/tariffs', component: TariffsPage, props: { base: '/tariffs' } },
      { path: '/elsewhere', component: { template: '<div />' } },
    ],
  })

  await router.push(`/tariffs${query}`)
  await router.isReady()

  const wrapper = mount(
    { template: '<router-view />' },
    {
      attachTo: document.body,
      global: {
        plugins: [router],
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          // What a panel always provides; without it a localized field hands back a plain string.
          [localesKey as symbol]: {
            list: ref([{ code: 'en', name: 'English' }]),
            active: ref('en'),
          },
        },
      },
    },
  )

  await flushPromises()

  return { wrapper, get, post, put }
}

/** Pick the first row up with the keyboard and put it one place down — a drag, minus the mouse. */
async function moveFirstDown(wrapper: Awaited<ReturnType<typeof panel>>['wrapper']) {
  const grip = wrapper.findAll('.wx-sortable-list__grip')[0]!

  await grip.trigger('keydown', { key: ' ' })
  await grip.trigger('keydown', { key: 'ArrowDown' })
  await flushPromises()
}

beforeEach(() => {
  confirm.mockReset()
})

afterEach(() => {
  document.body.innerHTML = ''
})

/**
 * The list, its two orders, and the form beside it. Where the form goes on a phone — into the
 * drawer, with its own way back — is a width, and jsdom measures none: that is checked in a
 * browser.
 */
describe('WxTariffsPage', () => {
  it('asks for every tariff and shows each price on one line, the featured one starred', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/tariffs')

    const [starter, growth, enterprise] = wrapper.findAll('.wx-tariff-row')

    expect(starter!.get('.wx-tariff-row__price').text()).toBe('$750 /mo')
    expect(starter!.text()).toContain('30 HOURS / 25$')
    expect(starter!.text()).toContain('For business')
    expect(starter!.find('.wx-tariff-row__star').exists()).toBe(false)
    expect(starter!.text()).not.toContain('Not published')

    // Cents when there are any; the star carries its word for a screen reader.
    expect(growth!.get('.wx-tariff-row__price').text()).toBe('$1,380.50')
    expect(growth!.get('.wx-tariff-row__star').attributes('aria-label')).toBe('Recommended')

    // No number: the words stand where the price would.
    expect(enterprise!.get('.wx-tariff-row__price').text()).toBe('On request')
    expect(enterprise!.text()).toContain('Not published')
  })

  it('writes the whole order when nothing narrows the list, and says cards', async () => {
    const { wrapper, post } = await panel()

    expect(wrapper.get('.wx-tariffs__note').text()).toContain('order of the cards on the site')

    await moveFirstDown(wrapper)

    expect(post).toHaveBeenCalledWith('/api/cms/tariffs/reorder', { ids: [2, 1, 3] })
  })

  it("writes one group's order when the list is narrowed to it, and says group", async () => {
    const { wrapper, get, post } = await panel('?category=3')

    expect(get).toHaveBeenCalledWith('/api/cms/tariffs?category=3')
    expect(wrapper.get('.wx-tariffs__note').text()).toContain('inside this group')

    await moveFirstDown(wrapper)

    expect(post).toHaveBeenCalledWith('/api/cms/tariffs/reorder', { ids: [2, 1, 3], category: 3 })
  })

  it('offers no grips over a search: the gaps are rows nobody can see', async () => {
    const { wrapper, get } = await panel('?q=combo')

    expect(get).toHaveBeenCalledWith('/api/cms/tariffs?search=combo')
    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
  })

  it('opens a new tariff as a form, and makes it on the first save', async () => {
    const { wrapper, post } = await panel('?category=3')

    await wrapper.get('.wx-tariffs__new').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.tariff).toBe('new')
    expect(post).not.toHaveBeenCalledWith('/api/cms/tariffs', expect.anything())

    await wrapper.get('.wx-tariff input').setValue('Brand new')
    await wrapper.get('.wx-tariff .wx-action-bar button').trigger('click')
    await flushPromises()

    // In the group the list was narrowed to; no currency — the server gives the first one.
    expect(post).toHaveBeenCalledWith('/api/cms/tariffs', {
      values: expect.objectContaining({
        name: { en: 'Brand new' },
        price: null,
        features: [],
        button_link: null,
        featured: false,
        published: false,
        categories: [3],
      }),
    })
    expect(post.mock.calls[0]![1].values).not.toHaveProperty('currency')
    expect(router.currentRoute.value.query.tariff).toBe('9')
  })

  it('saves with Ctrl+S', async () => {
    const { wrapper, put } = await panel('?tariff=1')

    await wrapper.get('.wx-tariff input').setValue('Combo Starter Plus')
    await wrapper.get('.wx-tariff').trigger('keydown', { key: 's', ctrlKey: true })
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/tariffs/1', {
      values: expect.objectContaining({ name: { en: 'Combo Starter Plus' } }),
    })
  })

  it('puts the refusal of a line of the list under that line', async () => {
    const { wrapper, put } = await panel('?tariff=1')

    put.mockRejectedValueOnce({
      status: 422,
      body: { errors: { 'features.1.text': ['Too long.'] } },
    })
    await wrapper.get('.wx-tariff input').setValue('x')
    await wrapper.get('.wx-tariff .wx-action-bar button').trigger('click')
    await flushPromises()

    const lines = wrapper
      .findAll('.wx-repeater .wx-form-item')
      .filter((item) => item.find('input').exists())
    const refused = lines.filter((item) => item.text().includes('Too long.'))

    expect(refused).toHaveLength(1)
    expect((refused[0]!.get('input').element as HTMLInputElement).value).toBe('SEO')
  })

  it('asks before another tariff takes the place of unsaved words', async () => {
    const { wrapper } = await panel('?tariff=1')

    confirm.mockResolvedValueOnce(false)
    await wrapper.get('.wx-tariff input').setValue('Half-written')
    await wrapper.findAll('.wx-tariff-row')[1]!.trigger('click')
    await flushPromises()

    expect(confirm).toHaveBeenCalledOnce()
    expect(router.currentRoute.value.query.tariff).toBe('1')
  })

  it('brings a tariff back from the bin, and the bin has no new line', async () => {
    const { wrapper, post } = await panel('?view=trashed')

    expect(wrapper.find('.wx-tariffs__new').exists()).toBe(false)

    await wrapper.findAll('.wx-actions__menu button')[0]!.trigger('click')
    await flushPromises()
    const items = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]

    expect(items.map((item) => item.textContent?.trim())).toEqual(['Restore'])
    items[0]!.click()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/tariffs/1/restore', {})
  })

  it('has no new line and no grips for somebody who only reads', async () => {
    const { wrapper } = await panel('', false)

    expect(wrapper.find('.wx-tariffs__new').exists()).toBe(false)
    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
  })
})
