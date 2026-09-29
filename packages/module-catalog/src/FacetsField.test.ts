import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { provideCatalogCategoryEditor } from './editor'
import FacetsField from './FacetsField.vue'
import type { CategoryRow, FacetInfo, FacetSetting } from './types'

const registry: FacetInfo[] = [
  { key: 'category', code: 'category', kind: 'tree', label: 'Category' },
  { key: 'price', code: 'price', kind: 'range', label: 'Price' },
  { key: 'brand', code: 'brand', kind: 'terms', label: 'Brand' },
]

function category(id: number, parent: number | null, name: string): CategoryRow {
  return {
    id,
    parent_id: parent,
    name,
    slug: name.toLowerCase(),
    depth: 0,
    is_published: true,
    visible: true,
    products_count: 0,
    url: null,
    created_at: null,
    updated_at: null,
    deleted_at: null,
  }
}

/** A tree of three: Computers (its own setting) › Laptops (none) › Gaming (the one edited). */
const tree: Record<number, { category: CategoryRow; values: { facets: FacetSetting[] | null } }> = {
  1: {
    category: category(1, null, 'Computers'),
    values: {
      facets: [
        { key: 'price', visible: true },
        { key: 'category', visible: false },
      ],
    },
  },
  2: { category: category(2, 1, 'Laptops'), values: { facets: null } },
}

function field(value: FacetSetting[] | null, parent: number | null = 2) {
  const get = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/facets')) return Promise.resolve({ data: registry })

    const id = Number(url.split('/').pop())

    return Promise.resolve({ data: tree[id] })
  })
  const i18n = createI18n()
  const admin = { apiPath: '/api/cms', http: { get }, i18n } as unknown as AdminContext
  const model = ref(value)

  const Host = defineComponent({
    setup() {
      provideCatalogCategoryEditor({
        category: ref(category(3, parent, 'Gaming')),
        locked: ref(false),
      })

      return () =>
        h(FacetsField, {
          modelValue: model.value,
          'onUpdate:modelValue': (next: FacetSetting[] | null) => (model.value = next),
        })
    },
  })

  const wrapper = mount(Host, {
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, model, get }
}

describe('WxCatalogFacetsField', () => {
  it('says whose setting a category inherits, walking up past the ones without', async () => {
    const { wrapper, get } = field(null)

    await flushPromises()

    expect(wrapper.text()).toContain('As in “Computers”')
    // Only what a visitor sees, in its order.
    expect(wrapper.text()).toContain('Price')
    expect(get).toHaveBeenCalledWith('/api/cms/catalog/categories/2')
    expect(get).toHaveBeenCalledWith('/api/cms/catalog/categories/1')
  })

  it('falls back on every facet in the registry’s order at the top of the tree', async () => {
    const { wrapper } = field(null, null)

    await flushPromises()

    expect(wrapper.text()).toContain('All filters, in their default order')
    expect(wrapper.text()).toContain('Category, Price, Brand')
  })

  it('starts its own setting from the inherited one, a facet added since switched off', async () => {
    const { wrapper, model } = field(null)

    await flushPromises()
    await wrapper.find('input[role="switch"]').setValue(true)
    await flushPromises()

    expect(model.value).toEqual([
      { key: 'price', visible: true },
      { key: 'category', visible: false },
      { key: 'brand', visible: false },
    ])
  })

  it('goes back to inheriting with null', async () => {
    const { wrapper, model } = field([{ key: 'brand', visible: true }])

    await flushPromises()
    await wrapper.find('input[role="switch"]').setValue(false)

    expect(model.value).toBeNull()
  })

  it('keeps a facet in its own setting hidden when the registry learned of it later', async () => {
    const { wrapper } = field([{ key: 'brand', visible: true }])

    await flushPromises()

    const names = wrapper.findAll('.wx-catalog-facets__name')

    expect(names.map((name) => name.text())).toEqual(['Brand', 'Category', 'Price'])
    expect(names.map((name) => name.classes('is-off'))).toEqual([false, true, true])
  })
})
