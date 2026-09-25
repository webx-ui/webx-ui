import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, adminTypes, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import { coreTypes, type ScreenModel, type ScreenNode } from '@webx-ui/schema'
import EventEditorPage from './EventEditorPage.vue'
import EventHistory from './EventHistory.vue'
import type { EventDetail, EventRow } from './types'

const spring: EventRow = {
  id: 7,
  title: 'Spring class',
  slug: 'spring-class',
  path: 'events/spring-class',
  url: 'https://example.test/events/spring-class',
  cover: null,
  starts_at: '2026-10-12T10:00:00+08:00',
  ends_at: null,
  all_day: false,
  when: '12 October 2026, 10:00',
  past: false,
  status: 'published',
  categories: [],
  published_at: '2026-09-12T08:00:00+00:00',
  updated_at: '2026-09-12T08:00:00+00:00',
  deleted_at: null,
  revision: 'r1',
}

function detail(revision: string, over: Partial<EventRow> = {}, values: ScreenModel = {}) {
  return {
    event: { ...spring, ...over, revision },
    values: {
      title: { en: over.title ?? 'Spring class' },
      slug: { en: 'spring-class' },
      starts_at: '2026-10-12T10:00:00+08:00',
      ends_at: null,
      all_day: false,
      attendance: 'offline',
      venue: { en: 'Studio Kitchen' },
      highlights: [{ title: { en: 'Three dishes' }, text: { en: 'From a starter on.' } }],
      ...values,
    },
    revision,
    prefix: 'events',
    preview_url: null,
  } satisfies EventDetail
}

const picker = (id: string, name: string, type: string, allDay: boolean): ScreenNode => ({
  id,
  type: 'wx-date-picker',
  name,
  visible: allDay ? { when: 'all_day', is: true } : { when: 'all_day', not: true },
  props: { type, valueFormat: "yyyy-MM-dd'T'HH:mm:ssXXX" },
})

/*
 * What this module's screen asks of the renderer, in the shape the playground's copy of
 * `events.form` has it: the title first (the first input of the form), the two pickers of a date
 * with `all_day` choosing between them, the place hidden online, and "What to expect" with
 * translated fields inside a row. The history behind a tab.
 */
const screen: ScreenNode[] = [
  {
    id: 'tabs',
    type: 'wx-tabs',
    children: [
      {
        id: 'event',
        type: 'wx-tab',
        label: 'Event',
        children: [
          { id: 'title', type: 'wx-input', name: 'title', localized: true },
          { id: 'all-day', type: 'wx-switch', name: 'all_day' },
          picker('starts-at', 'starts_at', 'datetime', false),
          picker('starts-on', 'starts_at', 'date', true),
          picker('ends-at', 'ends_at', 'datetime', false),
          picker('ends-on', 'ends_at', 'date', true),
          { id: 'attendance', type: 'wx-segmented', name: 'attendance' },
          {
            id: 'venue',
            type: 'wx-input',
            name: 'venue',
            localized: true,
            visible: { when: 'attendance', not: 'online' },
          },
          {
            id: 'highlights',
            type: 'wx-repeater',
            name: 'highlights',
            props: { collapsed: false },
            children: [
              { id: 'highlight-title', type: 'wx-input', name: 'title', localized: true },
              { id: 'highlight-text', type: 'wx-textarea', name: 'text', localized: true },
            ],
          },
        ],
      },
      {
        id: 'history',
        type: 'wx-tab',
        label: 'History',
        children: [{ id: 'versions', type: 'wx-event-history' }],
      },
    ],
  },
]

async function panel(first = detail('r1')) {
  const get = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/versions')) return Promise.resolve({ data: [] })
    if (url === '/api/cms/events/8') return Promise.resolve({ data: detail('c1', { id: 8 }) })

    return Promise.resolve({ data: first })
  })

  const put = vi
    .fn()
    .mockImplementation((_url: string, input: { values: ScreenModel }) =>
      Promise.resolve({ data: detail('r2', {}, input.values) }),
    )
  const post = vi.fn().mockResolvedValue({ data: detail('c1', { id: 8, status: 'draft' }) })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, put, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: {
      ...coreTypes,
      ...adminTypes,
      'wx-event-history': { component: EventHistory, kind: 'display' },
    },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/events', component: { template: '<div />' } },
      { path: '/events/:id(\\d+)', component: EventEditorPage, props: { base: '/events' } },
    ],
  })

  await router.push('/events/7')
  await router.isReady()

  const wrapper = mount(
    { template: '<router-view />' },
    {
      global: {
        plugins: [router],
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          // What a panel always provides; without it a localized field hands back a plain string.
          [localesKey as symbol]: {
            list: ref([{ code: 'en', name: 'English' }]),
            active: ref('en'),
          },
        },
      },
    },
  )

  await flushPromises()

  return { wrapper, get, put, post, router }
}

const pickerTypes = (wrapper: Awaited<ReturnType<typeof panel>>['wrapper']) =>
  wrapper.findAllComponents({ name: 'WxDatePicker' }).map((one) => one.props('type'))

afterEach(() => {
  vi.useRealTimers()
})

/**
 * Saving, the revision, duplicating and what the screen of an event asks of the renderer. The bar
 * at the bottom and the pickers' calendars are layout, checked in a browser.
 */
describe('WxEventEditorPage', () => {
  it('writes the draft after a pause, carrying the revision it read', async () => {
    vi.useFakeTimers()
    const { wrapper, put } = await panel()

    await wrapper.find('input').setValue('Spring cooking class')
    await nextTick()
    expect(put).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1500)
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/events/7', {
      values: expect.objectContaining({ title: { en: 'Spring cooking class' } }),
      revision: 'r1',
    })
  })

  it('prints when the event is under its name, as the server says it', async () => {
    const { wrapper } = await panel()

    expect(wrapper.text()).toContain('12 October 2026, 10:00')
  })

  it('asks for a date and a time, and for days alone once the event is all day', async () => {
    const { wrapper } = await panel()

    expect(pickerTypes(wrapper)).toEqual(['datetime', 'datetime'])

    await wrapper.findComponent({ name: 'WxSwitch' }).vm.$emit('update:modelValue', true)
    await flushPromises()

    expect(pickerTypes(wrapper)).toEqual(['date', 'date'])
  })

  it('hides the place of an event that is online', async () => {
    const { wrapper } = await panel()

    expect(wrapper.findAll('input').some((one) => one.element.value === 'Studio Kitchen')).toBe(
      true,
    )

    await wrapper.findComponent({ name: 'WxSegmented' }).vm.$emit('update:modelValue', 'online')
    await flushPromises()

    expect(wrapper.findAll('input').some((one) => one.element.value === 'Studio Kitchen')).toBe(
      false,
    )
  })

  it('keeps the words of "What to expect" per language inside each row', async () => {
    const { wrapper, put } = await panel()
    const title = wrapper.findAll('input').find((one) => one.element.value === 'Three dishes')!

    await title.setValue('Four dishes')
    await wrapper.find('.wx-event-editor').trigger('focusout')
    await flushPromises()

    const sent = put.mock.calls[0]?.[1] as { values: ScreenModel }

    expect(sent.values.highlights).toEqual([
      { title: { en: 'Four dishes' }, text: { en: 'From a starter on.' } },
    ])
  })

  it('shows a 422 under the end, where the server refused it', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 422,
      body: { errors: { ends_at: ['The end is before the start.'] } },
    })

    await wrapper.find('input').setValue('Mine')
    await wrapper.find('.wx-event-editor').trigger('focusout')
    await flushPromises()

    expect(wrapper.text()).toContain('The end is before the start.')
  })

  it('duplicates from the action bar, saving first, and opens the copy', async () => {
    const { wrapper, put, post, get, router } = await panel()

    await wrapper.find('input').setValue('Spring class, again')

    const button = wrapper
      .findAll('.wx-action-bar button')
      .find((one) => one.text().includes('Duplicate'))!

    await button.trigger('click')
    await flushPromises()

    // What was typed goes first, so the copy is made from it.
    expect(put).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenCalledWith('/api/cms/events/7/duplicate', {})
    expect(router.currentRoute.value.path).toBe('/events/8')
    expect(get).toHaveBeenCalledWith('/api/cms/events/8')
  })

  it('opens the history on mousedown, which is the event the tabs listen for', async () => {
    const { wrapper, get } = await panel()

    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/events/7/versions')
    expect(wrapper.find('.wx-event-history').text()).toContain('never been published')
  })
})
