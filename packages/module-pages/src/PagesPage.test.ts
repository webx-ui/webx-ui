import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import PagesPage from './PagesPage.vue'
import type { PageLevel, PageRow } from './types'

function page(row: Partial<PageRow> & { id: number }): PageRow {
  return {
    parent_id: 1,
    depth: 1,
    is_home: false,
    title: 'About',
    slug: 'about',
    path: 'about',
    url: 'https://example.test/about',
    status: 'published',
    published_at: '2026-09-01T00:00:00+00:00',
    updated_at: '2026-09-02T00:00:00+00:00',
    edited_by: 'Editor',
    children_count: 0,
    descendants_count: 0,
    deleted_at: null,
    trashed_with: null,
    can: { move: true, delete: true, address: true },
    ...row,
  }
}

const home = page({
  id: 1,
  parent_id: null,
  depth: 0,
  is_home: true,
  title: 'Home',
  slug: '',
  path: '',
  url: 'https://example.test',
  children_count: 2,
  can: { move: false, delete: false, address: false },
})

function panel(level: PageLevel) {
  const get = vi.fn().mockResolvedValue({ data: level })

  // The real dictionary, because the package's own English is what a panel sees before the
  // server's translations arrive — and a screen that shows keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/:all(.*)', component: { template: '<div />' } }],
  })

  return {
    get,
    wrapper: mount(PagesPage, {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

/**
 * What the section asks for and what it draws. Nothing about how it looks — jsdom computes no
 * layout, so the tree, the cards and the 375px width are checked in a browser (§9).
 */
describe('WxPagesPage', () => {
  it('pins the home page above the level of its children', async () => {
    const { wrapper, get } = panel({ home, items: [page({ id: 2 })] })

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/pages')

    const rows = wrapper.findAll('tbody tr')
    expect(rows[0]?.text()).toContain('Home')
    expect(rows[1]?.text()).toContain('About')

    // A live page's address is a link to it; the home page's own is the site's front page.
    const addresses = wrapper.findAll('.wx-pages__address')
    expect(addresses.map((link) => link.attributes('href'))).toEqual([
      'https://example.test',
      'https://example.test/about',
    ])
  })

  it('says when a page has no address in the language the panel is open in', async () => {
    const { wrapper } = panel({ home, items: [page({ id: 3, path: null, url: null })] })

    await flushPromises()

    expect(wrapper.text()).toContain('No address in this language')
  })

  it('draws a draft address as text rather than a link nobody can follow', async () => {
    const { wrapper } = panel({
      home,
      items: [page({ id: 4, status: 'draft', url: 'https://example.test/soon', path: 'soon' })],
    })

    await flushPromises()

    expect(wrapper.text()).toContain('/soon')

    // The home page above it is live and keeps its link; the draft has none.
    expect(wrapper.findAll('.wx-pages__address')).toHaveLength(1)
  })

  it('asks for the bin when the bin is chosen', async () => {
    const { wrapper, get } = panel({ home, items: [] })

    await flushPromises()
    get.mockClear()

    // The views of the list are tabs over the card now; the bin is the last of them.
    await wrapper.findAll('.wx-tabs__tab').at(-1)?.trigger('mousedown')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/pages?trashed=1')
  })

  it('stops promising a click once the rows are the bin', async () => {
    const { wrapper } = panel({ home, items: [page({ id: 2 })] })

    await flushPromises()
    expect(wrapper.get('.wx-table').classes()).toContain('wx-table--clickable')

    await wrapper.findAll('.wx-tabs__tab').at(-1)?.trigger('mousedown')
    await flushPromises()

    // A deleted page has no editor to open, so the row leads nowhere and says so (§13).
    expect(wrapper.get('.wx-table').classes()).not.toContain('wx-table--clickable')
    expect(wrapper.get('.wx-table').classes()).not.toContain('wx-table--hover')
  })

  it('asks before a drop that rewrites more than one address, and not before one that does not', async () => {
    const leaf = page({ id: 2, parent_id: 1, descendants_count: 0 })
    const branch = page({ id: 3, parent_id: 1, title: 'Catalogue', descendants_count: 41 })

    const { wrapper } = panel({ home, items: [leaf, branch] })
    await flushPromises()

    const table = wrapper.findComponent({ name: 'WxTable' })

    // One page landing somewhere else changes one address: a gesture, not a decision (§14.3).
    table.vm.$emit('node-drop', { row: leaf, target: branch, zone: 'inside' })
    await flushPromises()
    expect(document.querySelector('.wx-confirm__message')).toBeNull()

    // A branch takes its forty-one pages with it, and every one of them changes address.
    table.vm.$emit('node-drop', { row: branch, target: leaf, zone: 'inside' })
    await flushPromises()
    expect(document.querySelector('.wx-confirm__message')?.textContent).toContain('42')

    // Left open, the dialog outlives the test and is found by the next one.
    document.querySelector<HTMLButtonElement>('.wx-dialog__foot button')?.click()
    await flushPromises()
  })
})
