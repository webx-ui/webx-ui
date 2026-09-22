import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { localesKey } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { ref } from 'vue'
import MenusPage from './MenusPage.vue'
import type { MenuItemRow, MenuRow } from './types'

function menu(row: Partial<MenuRow> & { key: string }): MenuRow {
  return {
    id: 1,
    title: 'Header',
    declared: true,
    items_count: 0,
    variants: ['link'],
    cache: { enabled: true, built_at: null },
    can: { rename: false, delete: false },
    ...row,
  }
}

function item(row: Partial<MenuItemRow> & { id: number }): MenuItemRow {
  return {
    parent_id: null,
    depth: 0,
    title: { en: 'About' },
    label: 'About',
    target: 'url',
    entity_type: null,
    entity_id: null,
    url: '/about',
    hash: null,
    href: '/about',
    variant: 'link',
    is_heading: false,
    new_tab: false,
    rel: [],
    locales: [],
    visible: true,
    available: true,
    resolved: null,
    children: [],
    ...row,
  }
}

function panel(menus: MenuRow[], items: MenuItemRow[] = [], can: () => boolean = () => true) {
  const get = vi
    .fn()
    .mockImplementation((path: string) =>
      Promise.resolve({ data: path.endsWith('/items') ? items : menus }),
    )
  const post = vi.fn().mockResolvedValue({ data: {} })

  // The real dictionary: this package's own English is what a panel shows before the server's
  // translations arrive, and a screen of keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can,
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/:all(.*)', component: { template: '<div />' } },
    ],
  })

  return {
    get,
    post,
    router,
    wrapper: mount(MenusPage, {
      global: {
        plugins: [router],
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          // What the panel always provides; without it a localized field hands its model
          // straight through and prints `[object Object]` (CLAUDE.md §4).
          [localesKey as symbol]: { list: ref([{ code: 'en' }]), active: ref('en') },
        },
      },
    }),
  }
}

/**
 * What the section asks for and what it draws. Nothing about how it looks: jsdom computes no
 * layout, so the two panes, the drawer and the 375px screen are checked in a browser.
 */
describe('WxMenusPage', () => {
  it('lists the declared menus with the state of their cache', async () => {
    const { wrapper, get } = panel([
      menu({ key: 'header', items_count: 7, cache: { enabled: true, built_at: null } }),
      menu({ key: 'footer', id: null, title: 'Footer' }),
    ])

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/menus')
    expect(wrapper.text()).toContain('Header')
    expect(wrapper.text()).toContain('footer')
    expect(wrapper.text()).toContain('Cache not built')
  })

  it('opens the first menu by itself, and its tree comes with it', async () => {
    const { wrapper, get } = panel(
      [menu({ key: 'header' })],
      [item({ id: 1, label: 'About', href: '/about' })],
    )

    await flushPromises()
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/menus/header/items')
    expect(wrapper.text()).toContain('About')
    expect(wrapper.text()).toContain('/about')
  })

  it('draws a heading and a hidden item as what they are, not as addresses', async () => {
    const { wrapper } = panel(
      [menu({ key: 'header' })],
      [
        item({ id: 1, label: 'Services', is_heading: true, target: 'none', href: null }),
        item({ id: 2, label: 'Draft', visible: false, available: false }),
      ],
    )

    await flushPromises()
    await flushPromises()

    expect(wrapper.text()).toContain('Heading')
    expect(wrapper.text()).toContain('Hidden')
  })

  it('resets the cache of one menu and reads the mark again', async () => {
    const { wrapper, get, post } = panel([
      menu({ key: 'header', cache: { enabled: true, built_at: '2026-09-22T08:10:00+00:00' } }),
    ])

    await flushPromises()

    await wrapper.get('.wx-menus__row button.wx-action').trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/menus/header/cache/flush', {})

    // The mark under the name is the whole of what the button says, so the list is read again
    // rather than left reading "built today at 08:10".
    expect(get.mock.calls.filter(([path]) => path === '/api/cms/menus')).toHaveLength(2)
  })

  it('offers no reset and no menu to a reader who may only look', async () => {
    const { wrapper } = panel([menu({ key: 'header' })], [], () => false)

    await flushPromises()

    expect(wrapper.find('.wx-menus__row button.wx-action').exists()).toBe(false)
    expect(wrapper.find('.wx-row-menu').exists()).toBe(false)
  })

  it('keeps the chosen menu in the address', async () => {
    const { wrapper, router } = panel([menu({ key: 'header' }), menu({ key: 'footer', id: 2 })])

    await flushPromises()

    await wrapper.findAll('.wx-menus__open')[1]!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.menu).toBe('footer')
  })
})
