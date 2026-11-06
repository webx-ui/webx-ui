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
import CollectionsPage from './CollectionsPage.vue'
import type { CollectionRow } from './types'

const { confirm } = vi.hoisted(() => ({ confirm: vi.fn() }))

vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<object>()),
  confirm,
}))

function row(id: number, key: string, over: Partial<CollectionRow> = {}): CollectionRow {
  return {
    id,
    key,
    title: { en: key },
    source: 'manual',
    preset: null,
    view: 'grid',
    is_enabled: true,
    attention: null,
    places: null,
    products_count: 0,
    updated_at: '2026-11-05T09:00:00+00:00',
    deleted_at: null,
    ...over,
  } as CollectionRow
}

const live = row(1, 'new-arrivals')
const binned = row(2, 'on-sale', { deleted_at: '2026-11-06T09:00:00+00:00' })

let bin: CollectionRow[]
let asked: string[]
let router: Router
let i18n: ReturnType<typeof createI18n>

function page(data: CollectionRow[]) {
  return { data, current_page: 1, last_page: 1, per_page: 25, total: data.length }
}

function context(): AdminContext {
  const http = {
    get: vi.fn(async (url: string) => {
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
    routes: [{ path: '/catalog/collections', component: { template: '<div />' } }],
  })
  await router.push(address)
  await router.isReady()

  const wrapper = mount(CollectionsPage, {
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

describe('CollectionsPage', () => {
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
    const wrapper = await open('/catalog/collections')
    const tabs = wrapper.findAll('.wx-tabs__tab').map((tab) => tab.text())

    expect(tabs).toEqual(['All', 'Bin'])
    expect(asked.every((url) => !url.includes('trashed'))).toBe(true)
    expect(wrapper.text()).toContain('new-arrivals')
  })

  it('opens the bin by its tab, in the address, without the button of a new one', async () => {
    const wrapper = await open('/catalog/collections')

    expect(wrapper.text()).toContain('New collection')

    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await flushPromises()

    expect(router.currentRoute.value.query.view).toBe('trashed')
    expect(asked.at(-1)).toContain('trashed=1')
    expect(wrapper.text()).toContain('on-sale')
    expect(wrapper.text()).not.toContain('New collection')
  })

  it('has no way to the bin among the filters', async () => {
    const wrapper = await open('/catalog/collections')

    await wrapper.get('.wx-table__filter button').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Turned off')
    expect(document.body.textContent).not.toContain('In the bin')
  })

  it('does not open a collection from the bin by its row', async () => {
    const wrapper = await open('/catalog/collections?view=trashed')

    await wrapper.get('.wx-table__row').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/catalog/collections')
  })

  it('brings a collection back from the bin by its menu, and the bin is empty after it', async () => {
    const wrapper = await open('/catalog/collections?view=trashed')

    await wrapper.findAll('.wx-actions__menu button')[0]!.trigger('click')
    await flushPromises()
    const items = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]

    expect(items.map((item) => item.textContent?.trim())).toEqual(['Restore', 'Delete for good'])
    items[0]!.click()
    await flushPromises()

    expect(asked).toContain('POST /api/cms/catalog/collections/2/restore')
    expect(wrapper.text()).toContain('The bin is empty.')
  })

  async function askToPurge() {
    const wrapper = await open('/catalog/collections?view=trashed')

    await wrapper.findAll('.wx-actions__menu button')[0]!.trigger('click')
    await flushPromises()
    ;[...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')][1]!.click()
    await flushPromises()

    return wrapper
  }

  it('deletes a collection for good only after asking, and the bin is empty after it', async () => {
    const wrapper = await askToPurge()

    expect(confirm).toHaveBeenCalledWith(
      expect.objectContaining({
        title: 'Delete «on-sale» for good?',
        confirmText: 'Delete for good',
        tone: 'danger',
      }),
    )
    expect(asked).toContain('DELETE /api/cms/catalog/collections/2/purge')
    expect(wrapper.text()).toContain('The bin is empty.')
  })

  it('keeps the collection when the question is turned down', async () => {
    confirm.mockResolvedValueOnce(false)

    await askToPurge()

    expect(asked.some((call) => call.startsWith('DELETE'))).toBe(false)
  })

  it('offers no way to delete for good outside the bin', async () => {
    const wrapper = await open('/catalog/collections')

    await wrapper.findAll('.wx-actions__menu button')[0]!.trigger('click')
    await flushPromises()
    const items = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]

    expect(items.map((item) => item.textContent?.trim())).toEqual(['Open', 'Delete'])
  })

  it('says the bin is empty in its own words', async () => {
    bin = []

    const wrapper = await open('/catalog/collections?view=trashed')

    expect(wrapper.text()).toContain('The bin is empty.')
  })

  it('keeps the bin in the address when the search changes', async () => {
    const wrapper = await open('/catalog/collections?view=trashed')

    await wrapper.get('input').setValue('sale')
    await vi.waitFor(() => expect(router.currentRoute.value.query.q).toBe('sale'))

    expect(router.currentRoute.value.query.view).toBe('trashed')
  })
})
