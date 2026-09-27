import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import SubmissionList from './SubmissionList.vue'
import type { InboxForm, InboxStatus, SubmissionRow } from './types'

const form: InboxForm = {
  id: 7,
  slug: 'contact',
  title: { en: 'Contact' },
  is_enabled: true,
  options: {},
  position: 0,
  submissions_count: 2,
  unread_count: 1,
  created_at: null,
  updated_at: null,
}

const status: InboxStatus = {
  id: 1,
  key: 'new',
  title: { en: 'New' },
  color: 'primary',
  is_default: true,
  is_spam: false,
  is_closed: false,
  position: 0,
  submissions_count: null,
}

function row(id: number, values: Record<string, string>, read = false): SubmissionRow {
  return {
    id,
    values,
    status,
    assignee: null,
    is_read: read,
    source: 'web',
    files_count: 0,
    created_at: '2026-09-18T08:10:00Z',
  }
}

function panel(rows: SubmissionRow[], can: (permission: string) => boolean = () => true) {
  const get = vi.fn().mockImplementation((path: string) => {
    if (path.endsWith('/statuses')) {
      return Promise.resolve({ data: [status] })
    }

    return Promise.resolve({
      data: rows,
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: rows.length,
        from: 1,
        to: rows.length,
      },
      columns: [
        { key: 'name', label: 'Name', type: 'text' },
        { key: 'email', label: 'Email', type: 'email' },
      ],
      counts: { all: rows.length, unread: 1, statuses: { new: rows.length } },
    })
  })

  const post = vi.fn().mockResolvedValue({ data: { count: 1 } })

  /* The real dictionary, because the package's own English is what a panel sees before the
     server's translations arrive. */
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
    wrapper: mount(SubmissionList, {
      props: { form, base: '/inbox' },
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

/**
 * What the list asks for and what it draws. Nothing about how it looks: jsdom computes no
 * layout, so the columns that drop on a narrow pane and the cards on a phone are checked in a
 * browser (CLAUDE.md §4).
 */
describe('WxInboxSubmissionList', () => {
  it('draws the columns the server sent with the rows', async () => {
    const { wrapper, get } = panel([
      row(1, { name: 'Ada', email: 'ada@example.test' }),
      row(2, { name: 'Grace', email: 'grace@example.test' }, true),
    ])

    await flushPromises()

    expect(get).toHaveBeenCalledWith(
      '/api/cms/inbox/forms/7/submissions',
      expect.objectContaining({ query: expect.objectContaining({ view: 'all' }) }),
    )

    // The headings are the form's own fields, and the cells are read out of `values` by the
    // same key — no second mapping between a column and the row it draws.
    expect(wrapper.text()).toContain('Name')
    expect(wrapper.text()).toContain('Ada')
    expect(wrapper.text()).toContain('grace@example.test')
  })

  it('has a tab per status beside all and unread, with what is in each', async () => {
    const { wrapper } = panel([row(1, { name: 'Ada' })])

    await flushPromises()

    const tabs = wrapper.findAll('.wx-tabs__tab').map((tab) => tab.text())

    expect(tabs[0]).toContain('All')
    expect(tabs[1]).toContain('Unread')
    expect(tabs[2]).toContain('New')
  })

  it('switching a tab puts it in the address and asks again', async () => {
    const { wrapper, router, get } = panel([row(1, { name: 'Ada' })])

    await flushPromises()

    // Reka listens for `mousedown` on a tab, never for `click` (CLAUDE.md §4).
    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await flushPromises()

    expect(router.currentRoute.value.query.view).toBe('unread')
    expect(get).toHaveBeenLastCalledWith(
      '/api/cms/inbox/forms/7/submissions',
      expect.objectContaining({ query: expect.objectContaining({ view: 'unread' }) }),
    )
  })

  it('opens a submission at an address of its own, carrying the filter', async () => {
    const { wrapper, router } = panel([row(1, { name: 'Ada' })])

    await flushPromises()
    await router.replace({ query: { view: 'unread', form: '7' } })
    await flushPromises()

    await wrapper.get('tbody tr').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/inbox/submissions/1')
    // So that the way back lands on the form and the tab the reader was on (§11).
    expect(router.currentRoute.value.query.view).toBe('unread')
    expect(router.currentRoute.value.query.form).toBe('7')
  })

  it('offers nothing to do to a pile when the reader may only read', async () => {
    const { wrapper } = panel(
      [row(1, { name: 'Ada' })],
      (permission) => permission === 'inbox.view',
    )

    await flushPromises()

    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Add by hand')
    // Reading is still reading: the export is behind `inbox.view` like the list itself.
    expect(wrapper.text()).toContain('Export')
  })

  it("turns a page with one request, not the table's and a second one racing it", async () => {
    const { wrapper, get, router } = panel([row(1, { name: 'Ada' })])

    await flushPromises()
    get.mockClear()

    wrapper
      .getComponent({ name: 'WxTable' })
      .vm.$emit('state-change', { page: 2, perPage: 15, sort: null, search: '' })
    await flushPromises()

    expect(router.currentRoute.value.query.page).toBe('2')
    // The address moving is not a new list: the watcher over the tab and the assignee stays
    // quiet, or a second answer without `per_page` could land after the first and win.
    const lists = get.mock.calls.filter(([path]) => String(path).endsWith('/submissions'))
    expect(lists).toHaveLength(1)
    expect(lists[0]?.[1]).toEqual(
      expect.objectContaining({ query: expect.objectContaining({ page: 2, per_page: 15 }) }),
    )
  })
})
