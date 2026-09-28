import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { toast } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, WxRowMenu, type AdminContext } from '@webx-ui/module-admin'
import VacanciesPage from './VacanciesPage.vue'
import type { VacanciesList, VacancyRow } from './types'

function vacancy(row: Partial<VacancyRow> & { id: number }): VacancyRow {
  return {
    title: 'Senior PHP developer',
    slug: 'senior-php-developer',
    path: 'careers/senior-php-developer',
    url: 'https://example.test/careers/senior-php-developer',
    workplace: 'onsite',
    city: 'Kyiv',
    employment_types: ['FULL_TIME'],
    valid_through: '2026-11-30',
    posted_at: '2026-09-28',
    closed: false,
    closed_reason: null,
    status: 'published',
    position: row.id,
    categories: [{ id: 3, title: 'Development' }],
    published_at: '2026-09-28T08:00:00+00:00',
    updated_at: '2026-09-28T08:00:00+00:00',
    deleted_at: null,
    revision: 'r1',
    ...row,
  }
}

/** The server's own shape (§4.11): every row at once, no `meta`, the filters beside it. */
function body(rows: VacancyRow[]): VacanciesList {
  return { data: rows, filters: { categories: [{ id: 3, title: 'Development' }] } }
}

function panel(rows: VacancyRow[]) {
  const get = vi.fn().mockResolvedValue(body(rows))
  const post = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/duplicate')) {
      return Promise.resolve({
        data: {
          vacancy: vacancy({ id: 9, status: 'draft', slug: 'senior-php-developer-2' }),
          values: {},
          revision: 'r9',
          prefix: 'careers',
          preview_url: null,
        },
      })
    }

    return Promise.resolve({ data: vacancy({ id: 2, closed: true, closed_reason: 'manual' }) })
  })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  // `createWebHistory` reads the address jsdom still has from the test before: start clean.
  window.history.replaceState({}, '', '/')

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
    wrapper: mount(VacanciesPage, {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

const actions = (wrapper: ReturnType<typeof panel>['wrapper'], index = 0) =>
  wrapper.findAllComponents(WxRowMenu)[index]!.props('actions') as {
    key: string
    run?: () => void
  }[]

/**
 * What the section asks for and what it draws. How it looks — the rows at 375px, the muted closed
 * ones, the grips — is checked in a browser: jsdom computes no layout (CLAUDE.md §4).
 */
describe('WxVacanciesPage', () => {
  it('asks for the open vacancies first, and prints where, how and until when', async () => {
    const { wrapper, get } = panel([vacancy({ id: 2 })])

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/vacancies?state=open')
    expect(wrapper.get('.wx-vacancy-row__title').text()).toBe('Senior PHP developer')
    expect(wrapper.get('.wx-vacancy-row__address').text()).toBe('/careers/senior-php-developer')
    expect(wrapper.get('.wx-vacancy-row__where').text()).toBe('Kyiv')
    expect(wrapper.get('.wx-vacancy-row__employment').text()).toBe('Full-time')
    expect(wrapper.get('.wx-vacancy-row__until').text()).toMatch(/^until Nov 30/)
  })

  it('says "Remote" for a remote vacancy and names both for a hybrid one', async () => {
    const { wrapper } = panel([
      vacancy({ id: 2, workplace: 'remote', city: 'Kyiv' }),
      vacancy({ id: 3, workplace: 'hybrid', city: 'Lviv' }),
    ])

    await flushPromises()

    const where = wrapper.findAll('.wx-vacancy-row__where').map((one) => one.text())

    expect(where).toEqual(['Remote', 'Lviv · Hybrid'])
  })

  it('switches to the closed ones on a tab, and to the bin without a state', async () => {
    const { wrapper, get, router } = panel([vacancy({ id: 2 })])

    await flushPromises()
    get.mockClear()

    // Reka listens for `mousedown` on a tab, never for `click` (CLAUDE.md §4).
    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await flushPromises()

    expect(router.currentRoute.value.query.view).toBe('closed')
    expect(get).toHaveBeenCalledWith('/api/cms/vacancies?state=closed')

    await wrapper.findAll('.wx-tabs__tab').at(-1)!.trigger('mousedown')
    await flushPromises()

    expect(get).toHaveBeenLastCalledWith('/api/cms/vacancies?trashed=1')
  })

  it('marks a closed vacancy by why it is closed, and mutes it in "all" only', async () => {
    const { wrapper, router } = panel([
      vacancy({ id: 2 }),
      vacancy({ id: 3, title: 'Sales manager', closed: true, closed_reason: 'expired' }),
      vacancy({ id: 4, title: 'Designer', closed: true, closed_reason: 'manual' }),
    ])

    await flushPromises()

    expect(wrapper.text()).toContain('Expired')
    expect(wrapper.text()).toContain('Closed')
    expect(wrapper.findAll('.wx-vacancy-row.is-closed')).toHaveLength(0)

    await router.replace({ query: { view: 'all' } })
    await flushPromises()

    const muted = wrapper.findAll('.wx-vacancy-row.is-closed').map((one) => one.text())

    expect(muted).toHaveLength(2)
    expect(muted[0]).toContain('Sales manager')
  })

  it('lets the order be dragged on "Open" and "All" only while nothing narrows the list', async () => {
    const { wrapper, router } = panel([vacancy({ id: 2 }), vacancy({ id: 3 })])
    const draggable = () => !wrapper.getComponent({ name: 'WxSortableList' }).props('disabled')
    const hint = () => wrapper.get('.wx-vacancies__note').text()

    await flushPromises()
    expect(draggable()).toBe(true)
    expect(hint()).toContain('Drag a vacancy')

    await router.replace({ query: { view: 'all' } })
    await flushPromises()
    expect(draggable()).toBe(true)

    await router.replace({ query: { view: 'closed' } })
    await flushPromises()
    expect(draggable()).toBe(false)
    expect(hint()).toContain('Open and All')

    await router.replace({ query: { category: '3' } })
    await flushPromises()
    expect(draggable()).toBe(false)
    expect(hint()).toContain('clear the search and the filters')
  })

  it('writes the whole order, with no category', async () => {
    const { wrapper, post } = panel([vacancy({ id: 2 }), vacancy({ id: 3 })])

    await flushPromises()

    const list = wrapper.getComponent({ name: 'WxSortableList' })

    list.vm.$emit('update:modelValue', [vacancy({ id: 3 }), vacancy({ id: 2 })])
    list.vm.$emit('move', { from: 1, to: 0 })
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/vacancies/reorder', { ids: [3, 2] })
  })

  it('narrows by a category from behind the funnel, and says so in a chip', async () => {
    const { wrapper, get, router } = panel([vacancy({ id: 2 })])

    await flushPromises()
    get.mockClear()

    // The dropdowns live behind the funnel, and a shut panel has no fields to find.
    await wrapper.get('.wx-vacancies__funnel button').trigger('click')
    await flushPromises()

    // State, then category.
    wrapper.findAllComponents({ name: 'WxSelect' })[1]?.vm.$emit('update:modelValue', 3)
    await flushPromises()

    expect(router.currentRoute.value.query.category).toBe('3')
    expect(get).toHaveBeenCalledWith('/api/cms/vacancies?state=open&category=3')
    expect(wrapper.get('.wx-vacancies__applied').text()).toContain('Category: Development')
  })

  it('duplicates from the row menu and opens the copy', async () => {
    const { wrapper, post, router } = panel([vacancy({ id: 2 })])

    await flushPromises()

    actions(wrapper)
      .find((action) => action.key === 'duplicate')
      ?.run?.()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/vacancies/2/duplicate', {})
    expect(router.currentRoute.value.path).toBe('/vacancies/9')
  })

  it('offers to close what is on the site, to reopen what was closed by hand, and neither for a draft', async () => {
    const { wrapper } = panel([
      vacancy({ id: 2 }),
      vacancy({ id: 3, closed: true, closed_reason: 'manual' }),
      vacancy({ id: 4, closed: true, closed_reason: 'expired' }),
      vacancy({ id: 5, status: 'draft' }),
    ])

    await flushPromises()

    const keys = (index: number) => actions(wrapper, index).map((action) => action.key)

    expect(keys(0)).toContain('close')
    expect(keys(1)).toContain('reopen')
    // Closed by its date: the date is what reopens it, and that is in the editor.
    expect(keys(2)).not.toContain('reopen')
    expect(keys(2)).not.toContain('close')
    expect(keys(3)).not.toContain('close')
  })

  it('does not close a vacancy with edits waiting, and says why without asking', async () => {
    const { wrapper, post } = panel([vacancy({ id: 2, status: 'modified' })])
    const warning = vi.spyOn(toast, 'warning')

    await flushPromises()

    actions(wrapper)
      .find((action) => action.key === 'close')
      ?.run?.()
    await flushPromises()

    expect(post).not.toHaveBeenCalled()
    expect(warning).toHaveBeenCalledWith(expect.stringContaining('Publish or discard them first'))
  })
})
