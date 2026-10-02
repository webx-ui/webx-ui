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
  { key: 'brand', code: 'brand', kind: 'terms', label: 'Brand' },
]

/* The registry's orders: no price ones, as on a site with prices switched off. */
const sorts = [
  { key: 'default', label: 'Default' },
  { key: 'popular', label: 'Popular' },
  { key: 'new', label: 'Newest' },
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
    if (url.endsWith('/catalog/facets')) return Promise.resolve({ data: facets, meta: { sorts } })
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

    // The reload the facets cause keeps the table's page size: the server pages as it counts.
    expect(decodeURIComponent(listCalls(get).at(-1)!)).toBe(
      '/api/cms/catalog/products?state=published&per_page=15&facets[category][]=2&facets[price][min]=100&facets[price][max]=500',
    )
  })

  it('sends a sort the registry knows, and drops one it does not', async () => {
    const known = await panel('/catalog/products?sort=popular')

    expect(listCalls(known.get).at(-1)).toContain('sort=popular')

    // A price sort on a site without prices would be a 422: it is not asked for at all.
    const unknown = await panel('/catalog/products?sort=price_asc')

    expect(listCalls(unknown.get).at(-1)).not.toContain('sort=')
  })

  it('offers the sorts of the registry and the values the list counted for a terms facet', async () => {
    const { wrapper } = await panel(
      '/catalog/products',
      page([row(1)], {
        facets: {
          brand: {
            key: 'brand',
            kind: 'terms',
            values: [
              { value: 'acme', label: 'Acme', count: 4 },
              { value: 'zeta', label: 'Zeta', count: 1 },
            ],
          },
        },
      }),
    )

    await wrapper.get('.wx-table__filter button').trigger('click')
    await flushPromises()

    const selects = wrapper.findAllComponents({ name: 'WxSelect' })
    const options = (index: number) =>
      (selects[index]!.props('options') as { label: string }[]).map((one) => one.label)

    expect(options(0)).toEqual(['Default', 'Popular', 'Newest'])
    expect(options(1)).toEqual(['Acme (4)', 'Zeta (1)'])
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

  it('draws a satellite’s value by its shape: tags of a tone, a tag, a name muted when off the site', async () => {
    const { wrapper } = await panel(
      '/catalog/products',
      page(
        [
          row(1, {
            columns: {
              labels: [
                { id: 1, name: 'Top', code: 'top', color: 'primary' },
                { id: 2, name: 'Sale', code: 'sale', color: 'danger' },
              ],
              stock: {
                id: 3,
                name: 'On order',
                code: 'on-order',
                color: 'warning',
                purchasable: true,
              },
              brand: { id: 7, name: 'Proseware', visible: false },
            },
          }),
          row(2, { columns: { brand: { id: 1, name: 'Northwind', visible: true } } }),
        ],
        {
          columns: [
            { key: 'labels', label: 'Labels' },
            { key: 'stock', label: 'Stock status' },
            { key: 'brand', label: 'Brand' },
          ],
        },
      ),
    )

    const [first, second] = wrapper.findAll('tbody tr')
    const tags = first!.findAll('.wx-catalog-column-value .wx-badge')

    expect(tags.map((tag) => tag.text())).toEqual(['Top', 'Sale', 'On order'])
    expect(tags[0]!.classes()).toContain('wx-badge--primary')
    expect(tags[1]!.classes()).toContain('wx-badge--danger')
    expect(tags[2]!.classes()).toContain('wx-badge--warning')

    const brand = (tr: typeof first) =>
      tr!
        .findAll('.wx-catalog-column-value')
        .find((cell) => /Proseware|Northwind/.test(cell.text()))

    expect(brand(first)!.find('.wx-text').classes().join(' ')).toContain('muted')
    expect(brand(second)!.find('.wx-text').classes().join(' ')).not.toContain('muted')
    // No labels, no stock: a dash, not «[object Object]» nor an empty cell.
    expect(second!.findAll('.wx-catalog-column-value.is-empty')).toHaveLength(2)
  })

  it('says how many rows are picked and lets the pick go', async () => {
    const { wrapper } = await panel('/catalog/products')

    await wrapper.findAll('tbody input[type="checkbox"]')[0]!.setValue(true)

    expect(wrapper.find('.wx-catalog-products__selection').text()).toContain('Selected: 1')

    const clear = wrapper
      .findAll('.wx-catalog-products__selection button')
      .find((one) => one.text().includes('Clear the selection'))
    await clear!.trigger('click')

    expect(wrapper.find('.wx-catalog-products__selection').exists()).toBe(false)
  })

  it('picks everything the filter finds as the query, not as the ticked rows', async () => {
    const { wrapper } = await panel('/catalog/products?view=unpublished&q=lamp', {
      ...page([row(1), row(2)]),
      total: 40,
    })

    await wrapper.findAll('tbody input[type="checkbox"]')[0]!.setValue(true)

    const all = wrapper
      .findAll('.wx-catalog-products__selection button')
      .find((one) => one.text().includes('Select everything found: 40'))
    await all!.trigger('click')

    expect(wrapper.find('.wx-catalog-products__selection').text()).toContain(
      'Selected everything found: 40',
    )

    const bar = wrapper.findComponent({ name: 'BulkBar' })
    expect(bar.props('count')).toBe(40)
    expect(bar.props('selection')).toEqual({
      query: { q: 'lamp', state: 'unpublished', facets: {} },
    })
  })

  it('says what the engine searched for instead, and asks for the words as typed', async () => {
    const { wrapper, get } = await panel(
      '/catalog/products?q=protectve',
      page([row(1)], { corrected: 'protective' }),
    )

    const notice = wrapper.find('.wx-catalog-products__corrected')
    expect(notice.text()).toContain('Showing results for protective.')

    await notice.find('button').trigger('click')
    await flushPromises()

    expect(notice.text()).toContain('Search instead for protectve')
    expect(listCalls(get).at(-1)).toContain('q=protectve&typed=1')
  })

  it('says over the list when the search index did not answer and the database did', async () => {
    const quiet = await panel('/catalog/products', page([row(1)]))

    expect(quiet.wrapper.find('.wx-catalog-products__notice').exists()).toBe(false)

    const { wrapper } = await panel('/catalog/products', page([row(1)], { fell_back: true }))

    expect(wrapper.find('.wx-catalog-products__notice').text()).toContain(
      'The search index is not answering',
    )
  })

  it('says over the list when the catalogue has outgrown the database engine', async () => {
    const { wrapper } = await panel(
      '/catalog/products',
      page([row(1)], { outgrown: { live: 2400, limit: 2000 } }),
    )

    expect(wrapper.find('.wx-catalog-products__notice').text()).toContain(
      'Live products: 2400, the engine is meant for 2000',
    )
  })

  it('opens a product in its editor', async () => {
    const { wrapper, router } = await panel('/catalog/products')

    await wrapper.findAll('tbody tr')[0]!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/catalog/products/1')
  })
})
