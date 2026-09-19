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

  it('renames in place and leaves the address where it was', async () => {
    const { wrapper, put } = panel([tag({ id: 5 })])

    await flushPromises()
    await wrapper.get('.wx-tags__name').trigger('click')
    await flushPromises()

    const input = wrapper.get('.wx-tags__rename input')
    await input.setValue('drive belts')
    await input.trigger('keyup.enter')
    await flushPromises()

    // The title alone: a word spelled three ways before lunch would otherwise leave three
    // aliases behind a decision nobody made.
    expect(put).toHaveBeenCalledWith('/api/cms/blog/tags/5', { title: 'drive belts' })
  })

  it('puts nothing back when Escape is pressed', async () => {
    const { wrapper, put } = panel([tag({ id: 5 })])

    await flushPromises()
    await wrapper.get('.wx-tags__name').trigger('click')
    await flushPromises()

    const input = wrapper.get('.wx-tags__rename input')
    await input.setValue('drive belts')
    await input.trigger('keyup.esc')
    await flushPromises()

    expect(put).not.toHaveBeenCalled()
    expect(wrapper.find('.wx-tags__rename').exists()).toBe(false)
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

    await bar.findAll('button')[1]?.trigger('click')
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
