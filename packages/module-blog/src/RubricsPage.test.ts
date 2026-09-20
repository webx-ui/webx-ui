import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ref, type App } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { connectModals, localesKey, toast } from '@webx-ui/core'
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
  const remove = vi.fn().mockResolvedValue(undefined)

  // The real dictionary, because the package's own English is what a panel sees before the
  // server's translations arrive — and a screen that shows keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put, delete: remove },
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
    remove,
    router,
    wrapper: mount(RubricsPage, {
      global: {
        // Without an app context a dialog opened from code sees none of these provides:
        // `openModal` mounts outside the tree and takes the app the plugin connected (§4).
        plugins: [router, { install: (app: App) => connectModals(app) }],
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          // What the panel always has, and a localized field without it hands its whole map
          // to an input (CLAUDE.md §4).
          [localesKey as symbol]: { list: ref([{ code: 'en' }]), active: ref('en') },
        },
      },
    }),
  }
}

/** Choose a line of a row's `···`. The panel is teleported, so it is clicked in the document. */
async function choose(row: number, label: string): Promise<void> {
  const lines = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]
  const found = lines.find((line) => (line.textContent ?? '').includes(label))

  expect(found, `no “${label}” in the menu of row ${row}`).toBeDefined()
  found!.click()

  await flushPromises()
}

async function openMenu(wrapper: ReturnType<typeof panel>['wrapper'], row: number): Promise<void> {
  await wrapper
    .findAll('.wx-sortable-list__row')
    [row]!.get('.wx-actions__menu button')
    .trigger('click')
  await flushPromises()
}

/*
 * Everything opened from code lives outside the wrapper and outlives the test that opened it:
 * a dialog is only taken away once it is closed, and its panel is teleported to the body
 * rather than into the host, so the host alone is not enough to sweep up.
 */
afterEach(() => {
  for (const node of document.querySelectorAll('.wx-modal-host, .wx-dialog, .wx-dropdown')) {
    node.remove()
  }
})

/**
 * What the screen asks for and what it draws. The drag itself and the four tabs of the dialog
 * need layout, so they are checked in a browser (CLAUDE.md §4).
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

  it('opens the one that was clicked in a dialog, and keeps it in the address', async () => {
    const { wrapper, router } = panel([rubric({ id: 7 })])

    await flushPromises()
    await wrapper.get('.wx-rubric-row').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.rubric).toBe('7')
    expect(document.querySelectorAll('.wx-rubric-dialog').length).toBe(1)
  })

  /**
   * The line stays and refuses.
   *
   * A menu item that is not there does not answer "why can I not delete this", which is the
   * question somebody looking at a full rubric is asking (§6).
   */
  it('refuses to delete a rubric that still holds articles, and says why', async () => {
    const { wrapper, remove } = panel([rubric({ id: 7, articles_count: 42 })])

    await flushPromises()
    const said = vi.spyOn(toast, 'warning')

    await openMenu(wrapper, 0)
    await choose(0, 'Delete')

    // The toaster is the panel's, not this screen's, so what is checked is what was said.
    expect(said).toHaveBeenCalledWith('While it holds articles it cannot go — move them first.')
    expect(remove).not.toHaveBeenCalled()
  })

  it('offers the articles of a rubric only while it has any', async () => {
    const { wrapper } = panel([rubric({ id: 1 }), rubric({ id: 2, articles_count: 3 })])

    await flushPromises()
    await openMenu(wrapper, 0)

    expect(document.body.textContent).not.toContain('Show its articles')

    await openMenu(wrapper, 1)

    expect(document.body.textContent).toContain('Show its articles')
  })

  it('sends the whole order when one is dragged', async () => {
    const { wrapper, post } = panel([rubric({ id: 1 }), rubric({ id: 2, name: 'News' })])

    await flushPromises()
    wrapper.findComponent({ name: 'WxSortableList' }).vm.$emit('move')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/blog/rubrics/reorder', { ids: [1, 2] })
  })
})
