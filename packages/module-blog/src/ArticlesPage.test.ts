import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import ArticlesPage from './ArticlesPage.vue'
import type { ArticleRow, ArticlesPage as Page } from './types'

function article(row: Partial<ArticleRow> & { id: number }): ArticleRow {
  return {
    title: 'How to choose a belt',
    slug: 'how-to-choose-a-belt',
    lead: '',
    path: 'blog/how-to-choose-a-belt',
    url: 'https://example.test/blog/how-to-choose-a-belt',
    status: 'published',
    pinned: false,
    published_at: '2026-09-12T08:00:00+00:00',
    updated_at: '2026-09-12T08:00:00+00:00',
    deleted_at: null,
    author: { id: 1, name: 'Anna' },
    cover: null,
    rubrics: [{ id: 3, title: 'Repairs', slug: 'repairs' }],
    tags: [],
    revision: 'abc123',
    ...row,
  } as ArticleRow
}

/** The server's own shape: the numbers under `meta`, which the API client spreads. */
function body(rows: ArticleRow[]): {
  data: ArticleRow[]
  meta: Omit<Page, 'data' | 'filters'>
  filters: Page['filters']
} {
  return {
    data: rows,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: rows.length,
      from: rows.length > 0 ? 1 : null,
      to: rows.length,
    },
    filters: {
      rubrics: [{ id: 3, title: 'Repairs' }],
      tags: [{ id: 7, title: 'Belts' }],
      authors: [{ id: 1, title: 'Anna' }],
    },
  } as never
}

function panel(rows: ArticleRow[]) {
  const get = vi.fn().mockResolvedValue(body(rows))

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
    routes: [
      // The root as a route of its own: without it the catch-all is the current route, and
      // replacing only the query asks vue-router to rebuild a path whose parameter is missing.
      { path: '/', component: { template: '<div />' } },
      { path: '/:all(.*)', component: { template: '<div />' } },
    ],
  })

  return {
    get,
    router,
    wrapper: mount(ArticlesPage, {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

/**
 * What the section asks for and what it draws. Nothing about how it looks — jsdom computes no
 * layout, so the card at 375px, the widths and the columns that drop out are checked in a
 * browser (CLAUDE.md §4).
 */
describe('WxArticlesPage', () => {
  it('draws a page of articles with the address under each title', async () => {
    const { wrapper, get } = panel([article({ id: 2 })])

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/blog/articles')
    expect(wrapper.text()).toContain('How to choose a belt')
    expect(wrapper.get('.wx-articles__address').text()).toBe('/blog/how-to-choose-a-belt')
  })

  it('says so where an article has no address in this language', async () => {
    const { wrapper } = panel([article({ id: 3, path: null, url: null })])

    await flushPromises()

    expect(wrapper.text()).toContain('The address appears when it is published')
  })

  it('says that a live article has edits waiting beside the state, not instead of it', async () => {
    const { wrapper } = panel([article({ id: 4, status: 'modified' })])

    await flushPromises()

    const badges = wrapper.findAll('.wx-articles__state .wx-badge')
    expect(badges.map((badge) => badge.text())).toEqual(['Live', 'edits'])
  })

  it('asks for the bin when the bin is chosen, and stops promising a click', async () => {
    const { wrapper, get } = panel([article({ id: 2 })])

    await flushPromises()
    expect(wrapper.get('.wx-table').classes()).toContain('wx-table--clickable')
    get.mockClear()

    // Reka listens for `mousedown` on a tab, never for `click` (CLAUDE.md §4).
    await wrapper.findAll('.wx-tabs__tab').at(-1)?.trigger('mousedown')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/blog/articles?trashed=1')

    // An article in the bin has no editor to open, so the row leads nowhere and says so.
    expect(wrapper.get('.wx-table').classes()).not.toContain('wx-table--clickable')
  })

  it('narrows by a rubric through the address, so coming back lands on the same list', async () => {
    const { wrapper, get, router } = panel([article({ id: 2 })])

    await flushPromises()
    get.mockClear()

    wrapper.findAllComponents({ name: 'WxSelect' })[0]?.vm.$emit('update:modelValue', 3)
    await flushPromises()

    expect(router.currentRoute.value.query.rubric).toBe('3')
    expect(get).toHaveBeenCalledWith('/api/cms/blog/articles?rubric=3')
  })
})
