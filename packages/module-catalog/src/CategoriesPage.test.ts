import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { ref, type Component } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import CategoriesPage from './CategoriesPage.vue'
import DeletedPage from './DeletedPage.vue'
import type { CategoryNode } from './types'

function node(id: number, parent: number | null, name: string, children: CategoryNode[] = []) {
  return {
    id,
    parent_id: parent,
    name,
    slug: name.toLowerCase(),
    depth: parent === null ? 0 : 1,
    is_published: id !== 4,
    visible: id !== 4 && id !== 5,
    products_count: id * 2,
    url: `https://shop.test/${name.toLowerCase()}/`,
    children,
  } satisfies CategoryNode
}

/** Home › Kettles, Lamps; Garden; Hidden › Pots — the last one hidden by its parent. */
function tree(): CategoryNode[] {
  return [
    node(1, null, 'Home', [node(2, 1, 'Kettles'), node(3, 1, 'Lamps')]),
    node(6, null, 'Garden'),
    node(4, null, 'Hidden', [node(5, 4, 'Pots')]),
  ]
}

async function panel(path: string, component: Component, route: string) {
  const get = vi.fn().mockImplementation((url: string) => {
    if (url.includes('/deleted')) {
      return Promise.resolve({
        data: [
          {
            id: 9,
            name: 'Old kettle',
            sku: 'K-9',
            category: { id: 8, name: 'Sale', deleted: true },
            deleted_at: '2026-09-20T10:00:00+00:00',
          },
        ],
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 1,
        from: 1,
        to: 1,
      })
    }

    return Promise.resolve({ data: tree() })
  })
  const post = vi
    .fn()
    .mockImplementation((url: string) =>
      Promise.resolve(
        url.endsWith('/restore')
          ? { data: { id: 9, category: null, name: 'Old kettle' } }
          : { data: tree() },
      ),
    )
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    http: { get, post, put: vi.fn(), delete: vi.fn() },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: route, component, props: { base: '/catalog' } },
      { path: '/:rest(.*)*', component: { template: '<div />' } },
    ],
  })

  await router.push(path)
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

  return { wrapper, get, post, router }
}

describe('WxCatalogCategoriesPage', () => {
  it('draws the whole tree open, with the products under each branch', async () => {
    const { wrapper } = await panel('/catalog/categories', CategoriesPage, '/catalog/categories')

    const names = wrapper.findAll('.wx-catalog-categories__name').map((one) => one.text())

    expect(names).toEqual(['Home', 'Kettles', 'Lamps', 'Garden', 'Hidden', 'Pots'])
    // Pots is published and still off the site: its parent is not.
    expect(
      wrapper
        .findAll('.wx-catalog-categories__name')
        .find((one) => one.text() === 'Pots')!
        .classes(),
    ).toContain('is-hidden')
  })

  it('tells the server where a dropped category went: its parent, and the sibling after it', async () => {
    const { wrapper, post } = await panel(
      '/catalog/categories',
      CategoriesPage,
      '/catalog/categories',
    )
    const home = tree()[0]!

    // Lamps dropped before Kettles: under Home, first — so before Kettles.
    wrapper.findComponent({ name: 'WxTable' }).vm.$emit('node-drop', {
      row: home.children[1],
      target: home.children[0],
      zone: 'before',
      parent: { ...home, children: [home.children[1], home.children[0]] },
      index: 0,
      via: 'pointer',
    })
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/catalog/categories/3/move', {
      parent_id: 1,
      before_id: 2,
    })
  })

  it('moves a category to the end of the top level with no sibling after it', async () => {
    const { wrapper, post } = await panel(
      '/catalog/categories',
      CategoriesPage,
      '/catalog/categories',
    )
    const lamps = tree()[0]!.children[1]!

    wrapper.findComponent({ name: 'WxTable' }).vm.$emit('node-drop', {
      row: lamps,
      target: tree()[2],
      zone: 'after',
      parent: null,
      index: 3,
      via: 'pointer',
    })
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/catalog/categories/3/move', {
      parent_id: null,
      before_id: null,
    })
  })

  it('searches flat: the matches, without the branches around them', async () => {
    const { wrapper } = await panel('/catalog/categories', CategoriesPage, '/catalog/categories')

    await wrapper.find('input[type="search"], .wx-table input').setValue('pot')
    await new Promise((resolve) => setTimeout(resolve, 400))
    await flushPromises()

    expect(wrapper.findAll('.wx-catalog-categories__name').map((one) => one.text())).toEqual([
      'Pots',
    ])
  })
})

describe('WxCatalogDeletedPage', () => {
  it('says before a restore that a product whose category went too comes back bare', async () => {
    const { wrapper } = await panel('/catalog/deleted', DeletedPage, '/catalog/deleted')

    expect(wrapper.find('tbody').text()).toContain('Comes back without a category')
  })

  it('asks for the kind of the open tab, and the search it was opened with', async () => {
    const { get } = await panel(
      '/catalog/deleted?type=categories&q=sale',
      DeletedPage,
      '/catalog/deleted',
    )

    expect(get).toHaveBeenCalledWith(
      expect.stringMatching(/^\/api\/cms\/catalog\/deleted\?type=categories&q=sale(&|$)/),
    )
  })
})
