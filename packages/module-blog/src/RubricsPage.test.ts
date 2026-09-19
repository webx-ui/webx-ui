import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import RubricsPage from './RubricsPage.vue'
import type { RubricRow } from './types'

function rubric(row: Partial<RubricRow> & { id: number }): RubricRow {
  return {
    name: 'Repairs',
    title: { en: 'Repairs' },
    slug: { en: 'repairs' },
    lead: {},
    path: 'blog/repairs',
    url: 'https://example.test/blog/repairs',
    cover: null,
    is_visible: true,
    position: 0,
    articles_count: 0,
    seo: {},
    ...row,
  } as RubricRow
}

function panel(rows: RubricRow[]) {
  const get = vi.fn().mockResolvedValue({ data: rows, prefix: 'blog' })
  const post = vi.fn().mockResolvedValue({ data: rows[0] })
  const put = vi.fn().mockResolvedValue({ data: rows[0] })

  // The real dictionary, because the package's own English is what a panel sees before the
  // server's translations arrive — and a screen that shows keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put },
    i18n,
    types: {},
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
    post,
    put,
    router,
    wrapper: mount(RubricsPage, {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

/**
 * What the screen asks for and what it draws. The two panes side by side, the drawer they
 * become and the drag itself need layout, so they are checked in a browser (CLAUDE.md §4).
 */
describe('WxRubricsPage', () => {
  it('lists the rubrics in the order of the menu, with the address under each name', async () => {
    const { wrapper, get } = panel([
      rubric({ id: 1 }),
      rubric({ id: 2, name: 'News', slug: { en: 'news' }, path: 'blog/news' }),
    ])

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/blog/rubrics')
    expect(wrapper.findAll('.wx-rubric-row').map((row) => row.text())).toEqual([
      'Repairs/blog/repairs',
      'News/blog/news',
    ])
  })

  it('opens the one that was chosen and keeps it in the address', async () => {
    const { wrapper, router } = panel([rubric({ id: 7 })])

    await flushPromises()
    await wrapper.get('.wx-rubric-row').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.rubric).toBe('7')
    expect(wrapper.find('.wx-rubric-form').exists()).toBe(true)
  })

  /**
   * The button is there and out of reach, with the reason beside it.
   *
   * A button that disappears does not answer "why can I not delete this", which is the question
   * somebody looking at a full rubric is asking (§6).
   */
  it('keeps the delete of a full rubric out of reach and says why', async () => {
    const { wrapper } = panel([rubric({ id: 7, articles_count: 42 })])

    await flushPromises()
    await wrapper.get('.wx-rubric-row').trigger('click')
    await flushPromises()

    const foot = wrapper.get('.wx-rubric-form__foot')

    expect(foot.get('button').attributes('disabled')).toBeDefined()
    expect(foot.text()).toContain('move them first')
  })

  it('sends the whole order when one is dragged', async () => {
    const { wrapper, post } = panel([rubric({ id: 1 }), rubric({ id: 2, name: 'News' })])

    await flushPromises()
    wrapper.findComponent({ name: 'WxSortableList' }).vm.$emit('move')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/blog/rubrics/reorder', { ids: [1, 2] })
  })
})
