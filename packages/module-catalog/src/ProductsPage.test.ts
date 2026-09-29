import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import ProductsPage from './ProductsPage.vue'
import type { FacetInfo, ProductRow, ProductsPage as Page } from './types'

function row(id: number, over: Partial<ProductRow> = {}): ProductRow {
  return {
    id,
    name: `Product ${id}`,
    sku: `P-${id}`,
    price: 100 * id,
    old_price: null,
    unit: 'pcs',
    priority: 0,
    is_published: true,
    state: 'published',
    visible: true,
    category: { id: 2, name: 'Lamps', deleted: false },
    image: null,
    url: null,
    created_at: null,
    updated_at: null,
    deleted_at: null,
    ...over,
  }
}

const facets: FacetInfo[] = [
  { key: 'category', code: 'category', kind: 'tree', label: 'Category' },
  { key: 'price', code: 'price', kind: 'range', label: 'Price' },
]

function page(rows: ProductRow[], extra: Partial<Page> = {}): Page {
  return {
    data: rows,
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: rows.length,
    from: 1,
    to: rows.length,
    counts: { no_category: 3 },
    ...extra,
  }
}

async function panel(address: string, answer = page([row(1), row(2, { category: null })])) {
  const get = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/catalog/facets')) return Promise.resolve({ data: facets })
    if (url.endsWith('/catalog/categories')) return Promise.resolve({ data: [] })

    return Promise.resolve(answer)
  })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    http: { get, put: vi.fn(), post: vi.fn(), delete: vi.fn() },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/catalog/products', component: ProductsPage, props: { base: '/catalog' } },
      { path: '/catalog/products/:id', component: { template: '<div />' } },
    ],
  })

  await router.push(address)
  await router.isReady()

  const wrapper = mount(
    { template: '<router-view />' },
    {
      global: {
        plugins: [router],
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          [localesKey as symbol]: {
            list: ref([{ code: 'en', name: 'English' }]),
            active: ref('en'),
          },
        },
      },
    },
  )

  await flushPromises()

  return { wrapper, get, router }
}

const listCalls = (get: ReturnType<typeof vi.fn>) =>
  get.mock.calls.map(([url]) => String(url)).filter((url) => url.includes('/catalog/products'))

describe('WxCatalogProductsPage', () => {
  it('asks for the facets chosen in the address, in the nested form Laravel reads', async () => {
    const { get } = await panel('/catalog/products?f.category=2&f.price=100-500&view=published')

    expect(decodeURIComponent(listCalls(get).at(-1)!)).toBe(
      '/api/cms/catalog/products?state=published&facets[category][]=2&facets[price][min]=100&facets[price][max]=500',
    )
  })

  it('counts the products nobody filed on their own tab, and marks them in the list', async () => {
    const { wrapper } = await panel('/catalog/products')

    const tab = wrapper.findAll('.wx-tabs__tab').find((one) => one.text().includes('No category'))

    expect(tab?.text()).toContain('3')
    expect(wrapper.findAll('tbody tr')[1]!.text()).toContain('No category')
  })

  it('draws the columns the satellites add, with each row’s value under its key', async () => {
    const { wrapper } = await panel(
      '/catalog/products',
      page([row(1, { columns: { stock: 'In stock' } })], {
        columns: [{ key: 'stock', label: 'Stock' }],
      }),
    )

    expect(wrapper.find('thead').text()).toContain('Stock')
    expect(wrapper.find('tbody').text()).toContain('In stock')
  })

  it('says how many rows are picked and lets the pick go', async () => {
    const { wrapper } = await panel('/catalog/products')

    await wrapper.findAll('tbody input[type="checkbox"]')[0]!.setValue(true)

    expect(wrapper.find('.wx-catalog-products__selection').text()).toContain('Selected: 1')

    await wrapper.find('.wx-catalog-products__selection button').trigger('click')

    expect(wrapper.find('.wx-catalog-products__selection').exists()).toBe(false)
  })

  it('opens a product in its editor', async () => {
    const { wrapper, router } = await panel('/catalog/products')

    await wrapper.findAll('tbody tr')[0]!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/catalog/products/1')
  })
})
