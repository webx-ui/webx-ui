import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, WxRowMenu, type AdminContext } from '@webx-ui/module-admin'
import EventsPage from './EventsPage.vue'
import type { EventRow, EventsPage as Page } from './types'

function event(row: Partial<EventRow> & { id: number }): EventRow {
  return {
    title: 'Spring cooking class',
    slug: 'spring-cooking-class',
    path: 'events/spring-cooking-class',
    url: 'https://example.test/events/spring-cooking-class',
    cover: null,
    starts_at: '2026-10-12T10:00:00+08:00',
    ends_at: '2026-10-12T12:30:00+08:00',
    all_day: false,
    when: '12 October 2026, 10:00–12:30',
    past: false,
    status: 'published',
    categories: [{ id: 3, title: 'Cooking classes' }],
    published_at: '2026-09-12T08:00:00+00:00',
    updated_at: '2026-09-12T08:00:00+00:00',
    deleted_at: null,
    revision: 'r1',
    ...row,
  }
}

/** The server's own shape: a Laravel resource collection, the numbers under `meta`. */
function body(rows: EventRow[]) {
  return {
    data: rows,
    links: {},
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: rows.length,
      from: rows.length > 0 ? 1 : null,
      to: rows.length,
    },
    filters: {
      categories: [{ id: 3, title: 'Cooking classes' }],
      services: [{ id: 5, title: 'Catering' }],
    } satisfies Page['filters'],
  }
}

function panel(rows: EventRow[]) {
  const get = vi.fn().mockResolvedValue(body(rows))
  const post = vi.fn().mockResolvedValue({
    data: {
      event: event({ id: 9, status: 'draft', slug: 'spring-cooking-class-2' }),
      values: {},
      revision: 'r9',
      prefix: 'events',
      preview_url: null,
    },
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
    wrapper: mount(EventsPage, {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

/**
 * What the section asks for and what it draws. How it looks — the card at 375px, the columns that
 * drop out, the muted rows — is checked in a browser: jsdom computes no layout (CLAUDE.md §4).
 */
describe('WxEventsPage', () => {
  it('asks for the upcoming events first, and prints when each one is', async () => {
    const { wrapper, get } = panel([event({ id: 2 })])

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/events?when=upcoming')
    expect(wrapper.text()).toContain('Spring cooking class')
    expect(wrapper.get('.wx-events__when').text()).toBe('12 October 2026, 10:00–12:30')
    expect(wrapper.get('.wx-events__address').text()).toBe('/events/spring-cooking-class')
  })

  it('says "no date" for an event whose date is not set and has no words for it', async () => {
    const { wrapper } = panel([event({ id: 3, starts_at: null, ends_at: null, when: '' })])

    await flushPromises()

    expect(wrapper.get('.wx-events__when').text()).toBe('No date')
  })

  it('switches to the past ones on a tab, and to the bin with every date in it', async () => {
    const { wrapper, get, router } = panel([event({ id: 2 })])

    await flushPromises()
    get.mockClear()

    // Reka listens for `mousedown` on a tab, never for `click` (CLAUDE.md §4).
    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await flushPromises()

    expect(router.currentRoute.value.query.view).toBe('past')
    expect(get).toHaveBeenCalledWith('/api/cms/events?when=past')

    await wrapper.findAll('.wx-tabs__tab').at(-1)!.trigger('mousedown')
    await flushPromises()

    expect(get).toHaveBeenLastCalledWith('/api/cms/events?when=all&trashed=1')
    expect(wrapper.get('.wx-table').classes()).not.toContain('wx-table--clickable')
  })

  it('marks the past ones in "all", and only there', async () => {
    const { wrapper, router } = panel([
      event({ id: 2 }),
      event({ id: 3, title: 'Summer dinner', past: true }),
    ])

    await flushPromises()
    expect(wrapper.findAll('.is-past')).toHaveLength(0)

    await router.replace({ query: { view: 'all' } })
    await flushPromises()

    const muted = wrapper.findAll('.is-past')
    expect(muted).toHaveLength(1)
    expect(muted[0]!.text()).toContain('Summer dinner')
  })

  it('narrows by a category through the address, from the first page', async () => {
    const { wrapper, get, router } = panel([event({ id: 2 })])

    await flushPromises()
    get.mockClear()

    // The dropdowns live behind the funnel, and a shut panel has no fields to find.
    await wrapper.get('.wx-table__filter button').trigger('click')
    await flushPromises()

    // State, category, service — in that order.
    wrapper.findAllComponents({ name: 'WxSelect' })[1]?.vm.$emit('update:modelValue', 3)
    await flushPromises()

    expect(router.currentRoute.value.query.category).toBe('3')
    expect(get).toHaveBeenCalledWith('/api/cms/events?when=upcoming&category=3')
  })

  it('duplicates from the row menu and opens the copy', async () => {
    const { wrapper, post, router } = panel([event({ id: 2 })])

    await flushPromises()

    const menu = wrapper.findAllComponents(WxRowMenu)[0]!
    const actions = menu.props('actions') as { key: string; run?: () => void }[]

    actions.find((action) => action.key === 'duplicate')?.run?.()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/events/2/duplicate', {})
    expect(router.currentRoute.value.path).toBe('/events/9')
  })
})
