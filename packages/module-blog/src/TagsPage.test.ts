import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import TagsPage from './TagsPage.vue'
import type { TagRow, TagsPage as Page } from './types'

function tag(row: Partial<TagRow> & { id: number }): TagRow {
  return {
    title: 'belts',
    titles: { en: 'belts' },
    slug: 'belts',
    path: '/blog/tag/belts',
    url: 'https://example.test/blog/tag/belts',
    noindex: true,
    indexing: 'noindex',
    articles_count: 23,
    ...row,
  } as TagRow
}

/** The server's own shape: the numbers under `meta`, which the API client spreads. */
function body(rows: TagRow[], filters?: Partial<Page['filters']>) {
  return {
    data: rows,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 30,
      total: rows.length,
      from: rows.length > 0 ? 1 : null,
      to: rows.length,
    },
    filters: { total: rows.length, empty: 0, noindex: rows.length, ...filters },
  }
}

function panel(rows: TagRow[], filters?: Partial<Page['filters']>) {
  const get = vi.fn().mockResolvedValue(body(rows, filters))
  const put = vi
    .fn()
    .mockImplementation((_url: string, input: Record<string, unknown>) =>
      Promise.resolve({ data: tag({ id: rows[0]?.id ?? 1, ...input }) }),
    )
  const post = vi.fn().mockResolvedValue({ data: { affected: rows.length } })

  // The real dictionary, because the package's own English is what a panel sees before the
  // server's translations arrive — and a screen that shows keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, put, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
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
    put,
    post,
    router,
    wrapper: mount(TagsPage, {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

/**
 * What the screen asks for and what it draws. Nothing about how it looks: jsdom computes no
 * layout, so the columns that drop out and the bar at the bottom are checked in a browser
 * (CLAUDE.md §4).
 */
describe('WxTagsPage', () => {
  it('says which of the three states of indexing a row is in', async () => {
    const { wrapper } = panel([
      tag({ id: 1, indexing: 'open', noindex: false }),
      tag({ id: 2, slug: 'filters', indexing: 'rule' }),
      tag({ id: 3, slug: 'expo', indexing: 'noindex' }),
    ])

    await flushPromises()

    const text = wrapper.text()

    // Three answers and not two, so that the editor who wrote the rule is not looking at a row
    // that says `noindex` and disagreeing with it (§12).
    expect(text).toContain('indexed')
    expect(text).toContain('indexed — SEO rule')
    expect(text).toContain('noindex')
  })

  it('counts on the tabs what the filters would give, and asks for the one that is pressed', async () => {
    const { wrapper, get } = panel([tag({ id: 1 })], { total: 64, empty: 9, noindex: 61 })

    await flushPromises()
    expect(wrapper.find('.wx-tabs__tab').text()).toContain('64')
    get.mockClear()

    // Reka listens for `mousedown` on a tab, never for `click` (CLAUDE.md §4).
    await wrapper.findAll('.wx-tabs__tab').at(-1)?.trigger('mousedown')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/blog/tags?noindex=1')
  })

  it('leaves the name a name: renaming is a dialog, not a box in the cell', async () => {
    const { wrapper } = panel([tag({ id: 5 })])

    await flushPromises()

    // A name that is quietly an <input> reads as a name, and a stray click on a row is a
    // rename nobody asked for. The cell shows the word; the menu opens the form.
    expect(wrapper.find('.wx-tags__rename').exists()).toBe(false)
    expect(wrapper.find('tbody input[type="text"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('belts')
  })

  it('asks the server for the order the heading was clicked', async () => {
    const { wrapper, get } = panel([tag({ id: 5 })])

    await flushPromises()
    get.mockClear()

    const heading = wrapper.findAll('thead button').find((button) => button.text() !== '')
    await heading?.trigger('click')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/blog/tags?sort=name&per_page=15')
  })

  it('raises the selection bar once something is chosen, and opens the pile at once', async () => {
    const { wrapper, post } = panel([tag({ id: 5 }), tag({ id: 6, slug: 'expo' })])

    await flushPromises()
    expect(wrapper.find('.wx-action-bar').exists()).toBe(false)

    const boxes = wrapper.findAll('.wx-table__body input[type="checkbox"]')
    await boxes[0]?.setValue(true)
    await boxes[1]?.setValue(true)
    await flushPromises()

    const bar = wrapper.get('.wx-action-bar')
    expect(bar.text()).toContain('2 selected')

    // The three that are not merging live behind the bar's own `···`, where the same three
    // words sit on every row. The panel is teleported to the end of the document, so it is
    // looked for there rather than inside the wrapper.
    await bar.get('.wx-actions__menu button').trigger('click')
    await flushPromises()

    const items = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]

    items.find((item) => item.textContent?.trim() === 'Index')?.click()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/blog/tags/mass', { ids: [5, 6], action: 'index' })
  })

  it('will not offer a merge of one, because one tag has nothing to merge into', async () => {
    const { wrapper } = panel([tag({ id: 5 }), tag({ id: 6, slug: 'expo' })])

    await flushPromises()
    await wrapper.findAll('.wx-table__body input[type="checkbox"]')[0]?.setValue(true)
    await flushPromises()

    expect(wrapper.get('.wx-action-bar button').attributes('disabled')).toBeDefined()
  })
})
