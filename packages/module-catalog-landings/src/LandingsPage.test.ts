import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory, type Router } from 'vue-router'
import {
  adminKey,
  adminMessages,
  createI18n,
  i18nKey,
  type AdminContext,
} from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import LandingsPage from './LandingsPage.vue'
import type { LandingRow } from './types'

const { confirm } = vi.hoisted(() => ({ confirm: vi.fn() }))

vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<object>()),
  confirm,
}))

function row(id: number, slug: string, over: Partial<LandingRow> = {}): LandingRow {
  return {
    id,
    category_id: null,
    category: null,
    name: { en: slug },
    slug: { en: slug },
    url: null,
    filters: {},
    chips: [],
    products_count: 3,
    counted_at: null,
    is_published: true,
    on_category: false,
    position: 0,
    attention: null,
    deleted_at: null,
    updated_at: '2026-11-05T09:00:00+00:00',
    ...over,
  } as LandingRow
}

const live = row(1, 'red-laptops')
const binned = row(2, 'blue-laptops', { deleted_at: '2026-11-06T09:00:00+00:00' })

let bin: LandingRow[]
let asked: string[]
let router: Router
let i18n: ReturnType<typeof createI18n>

function page(data: LandingRow[]) {
  return { data, current_page: 1, last_page: 1, per_page: 25, total: data.length }
}

function context(): AdminContext {
  const http = {
    get: vi.fn(async (url: string) => {
      // The base filter's tree is another question, and this list does not care for its answer.
      if (!url.includes('/catalog/landings')) return { data: [] }

      asked.push(url)

      return page(url.includes('trashed=1') ? bin : [live])
    }),
    post: vi.fn(async (url: string) => {
      asked.push(`POST ${url}`)
      bin = []

      return { data: binned }
    }),
    delete: vi.fn(async (url: string) => {
      asked.push(`DELETE ${url}`)
      bin = []
    }),
  }

  return {
    apiPath: '/api/cms',
    http,
    i18n,
    can: () => true,
    state: { manifest: null, user: null, status: 'ready', error: null },
  } as unknown as AdminContext
}

async function open(address: string) {
  i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/catalog/landings', component: { template: '<div />' } },
      { path: '/catalog/landings/:id', component: { template: '<div />' } },
    ],
  })
  await router.push(address)
  await router.isReady()

  const wrapper = mount(LandingsPage, {
    attachTo: document.body,
    global: {
      plugins: [router],
      provide: {
        [adminKey as symbol]: context(),
        [i18nKey as symbol]: i18n,
        [localesKey as symbol]: {
          list: ref([{ code: 'en', name: 'English' }]),
          active: ref('en'),
        },
      },
    },
  })

  await flushPromises()

  return wrapper
}

describe('LandingsPage', () => {
  beforeEach(() => {
    bin = [binned]
    asked = []
    confirm.mockReset()
    confirm.mockResolvedValue(true)
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('draws «All» and «Bin» over the list and asks for the live ones first', async () => {
    const wrapper = await open('/catalog/landings')
    const tabs = wrapper.findAll('.wx-tabs__tab').map((tab) => tab.text())

    expect(tabs).toEqual(['All', 'Bin'])
    expect(asked.every((url) => !url.includes('trashed'))).toBe(true)
    expect(wrapper.text()).toContain('red-laptops')
  })

  it('opens the bin by its tab, in the address, without the buttons that make landings', async () => {
    const wrapper = await open('/catalog/landings')

    expect(wrapper.text()).toContain('New landing')
    expect(wrapper.text()).toContain('Create in bulk')

    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await flushPromises()

    expect(router.currentRoute.value.query.view).toBe('trashed')
    expect(asked.at(-1)).toContain('trashed=1')
    expect(wrapper.text()).toContain('blue-laptops')
    expect(wrapper.text()).not.toContain('New landing')
    expect(wrapper.text()).not.toContain('Create in bulk')
  })

  it('has no way to the bin among the filters', async () => {
    const wrapper = await open('/catalog/landings')

    await wrapper.get('.wx-table__filter button').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Needs attention')
    expect(document.body.textContent).not.toContain('In the bin')
  })

  it('names the column of the bin by when a landing was thrown away', async () => {
    const wrapper = await open('/catalog/landings?view=trashed')

    expect(wrapper.text()).toContain('Deleted')
    expect(wrapper.text()).not.toContain('Updated')
  })

  it('does not open a landing from the bin by its row', async () => {
    const wrapper = await open('/catalog/landings?view=trashed')

    await wrapper.get('.wx-table__row').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/catalog/landings')
  })

  it('brings a landing back from the bin by its menu, and the bin is empty after it', async () => {
    const wrapper = await open('/catalog/landings?view=trashed')

    await wrapper.findAll('.wx-actions__menu button')[0]!.trigger('click')
    await flushPromises()
    const items = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]

    expect(items.map((item) => item.textContent?.trim())).toEqual(['Restore', 'Delete for good'])
    items[0]!.click()
    await flushPromises()

    expect(asked).toContain('POST /api/cms/catalog/landings/2/restore')
    expect(wrapper.text()).toContain('The bin is empty.')
  })

  async function askToPurge() {
    const wrapper = await open('/catalog/landings?view=trashed')

    await wrapper.findAll('.wx-actions__menu button')[0]!.trigger('click')
    await flushPromises()
    ;[...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')][1]!.click()
    await flushPromises()

    return wrapper
  }

  it('deletes a landing for good only after asking, and the bin is empty after it', async () => {
    const wrapper = await askToPurge()

    expect(confirm).toHaveBeenCalledWith(
      expect.objectContaining({
        title: 'Delete «blue-laptops» for good?',
        confirmText: 'Delete for good',
        tone: 'danger',
      }),
    )
    expect(asked).toContain('DELETE /api/cms/catalog/landings/2/purge')
    expect(wrapper.text()).toContain('The bin is empty.')
  })

  it('keeps the landing when the question is turned down', async () => {
    confirm.mockResolvedValueOnce(false)

    await askToPurge()

    expect(asked.some((call) => call.startsWith('DELETE'))).toBe(false)
  })

  it('offers no way to delete for good outside the bin', async () => {
    const wrapper = await open('/catalog/landings')

    await wrapper.findAll('.wx-actions__menu button')[0]!.trigger('click')
    await flushPromises()
    const items = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]

    expect(items.map((item) => item.textContent?.trim())).toEqual(['Open', 'Unpublish', 'Delete'])
  })

  it('says the bin is empty in its own words', async () => {
    bin = []

    const wrapper = await open('/catalog/landings?view=trashed')

    expect(wrapper.text()).toContain('The bin is empty.')
  })

  it('keeps the bin in the address when the search changes', async () => {
    const wrapper = await open('/catalog/landings?view=trashed')

    await wrapper.get('input').setValue('blue')
    await vi.waitFor(() => expect(router.currentRoute.value.query.q).toBe('blue'))

    expect(router.currentRoute.value.query.view).toBe('trashed')
  })
})
