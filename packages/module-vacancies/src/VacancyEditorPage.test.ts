import { disableAutoUnmount, enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterAll, afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, adminTypes, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import { coreTypes, type ScreenModel, type ScreenNode } from '@webx-ui/schema'
import VacancyEditorPage from './VacancyEditorPage.vue'
import VacancyHistory from './VacancyHistory.vue'
import type { VacancyDetail, VacancyRow } from './types'

// The editor's autosave pause outlives a test that never unmounts it, and fires into a torn-down
// jsdom: "Element is not defined" from a ref callback, after every test has already passed. The
// switch is global to the test utils, so it is handed back for the next file in a shared worker.
enableAutoUnmount(afterEach)
afterAll(disableAutoUnmount)

const developer: VacancyRow = {
  id: 7,
  title: 'PHP developer',
  slug: 'php-developer',
  path: 'careers/php-developer',
  url: 'https://example.test/careers/php-developer',
  workplace: 'onsite',
  city: 'Kyiv',
  employment_types: ['FULL_TIME'],
  valid_through: '2026-11-30',
  posted_at: '2026-09-28',
  closed: false,
  closed_reason: null,
  status: 'published',
  position: 1,
  categories: [],
  published_at: '2026-09-28T08:00:00+00:00',
  updated_at: '2026-09-28T08:00:00+00:00',
  deleted_at: null,
  revision: 'r1',
}

function detail(revision: string, over: Partial<VacancyRow> = {}, values: ScreenModel = {}) {
  return {
    vacancy: { ...developer, ...over, revision },
    values: {
      title: { en: over.title ?? 'PHP developer' },
      slug: { en: 'php-developer' },
      workplace: 'onsite',
      city: { en: 'Kyiv' },
      salary_min: 3000,
      salary_max: 4500,
      valid_through: '2026-11-30',
      duties: [{ text: { en: 'Write the API' } }],
      ...values,
    },
    revision,
    prefix: 'careers',
    preview_url: null,
  } satisfies VacancyDetail
}

/*
 * What this module's screen asks of the renderer, in the shape the playground's copy of
 * `vacancies.form` has it: the title first (the first input of the form), the city hidden for a
 * remote vacancy, the salary numbers, a day picker, and "Duties" with a translated line inside
 * each row. The history behind a tab.
 */
const screen: ScreenNode[] = [
  {
    id: 'tabs',
    type: 'wx-tabs',
    children: [
      {
        id: 'vacancy',
        type: 'wx-tab',
        label: 'Vacancy',
        children: [
          { id: 'title', type: 'wx-input', name: 'title', localized: true },
          { id: 'workplace', type: 'wx-segmented', name: 'workplace' },
          {
            id: 'city',
            type: 'wx-input',
            name: 'city',
            localized: true,
            visible: { when: 'workplace', not: 'remote' },
          },
          { id: 'salary-min', type: 'wx-input-number', name: 'salary_min', label: 'From' },
          { id: 'salary-max', type: 'wx-input-number', name: 'salary_max', label: 'To' },
          {
            id: 'valid-through',
            type: 'wx-date-picker',
            name: 'valid_through',
            props: { type: 'date', valueFormat: 'yyyy-MM-dd' },
          },
          {
            id: 'duties',
            type: 'wx-repeater',
            name: 'duties',
            props: { collapsed: false, sortable: true },
            children: [{ id: 'duty-text', type: 'wx-input', name: 'text', localized: true }],
          },
        ],
      },
      {
        id: 'history',
        type: 'wx-tab',
        label: 'History',
        children: [{ id: 'versions', type: 'wx-vacancy-history' }],
      },
    ],
  },
]

async function panel(first = detail('r1')) {
  const get = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/versions')) return Promise.resolve({ data: [] })
    if (url === '/api/cms/vacancies/8') return Promise.resolve({ data: detail('c1', { id: 8 }) })

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
      'wx-vacancy-history': { component: VacancyHistory, kind: 'display' },
    },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/vacancies', component: { template: '<div />' } },
      {
        path: '/vacancies/:id(\\d+)',
        component: VacancyEditorPage,
        props: { base: '/vacancies' },
      },
    ],
  })

  await router.push('/vacancies/7')
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

const hasValue = (wrapper: Awaited<ReturnType<typeof panel>>['wrapper'], value: string) =>
  wrapper.findAll('input').some((one) => one.element.value === value)

afterEach(() => {
  vi.useRealTimers()
})

/**
 * Saving, the revision, duplicating and what the screen of a vacancy asks of the renderer. The
 * bar at the bottom and the pickers' calendars are layout, checked in a browser.
 */
describe('WxVacancyEditorPage', () => {
  it('writes the draft after a pause, carrying the revision it read', async () => {
    vi.useFakeTimers()
    const { wrapper, put } = await panel()

    await wrapper.find('input').setValue('Senior PHP developer')
    await nextTick()
    expect(put).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1500)
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/vacancies/7', {
      values: expect.objectContaining({
        title: { en: 'Senior PHP developer' },
        // The day travels as the day it is: no moment, no zone.
        valid_through: '2026-11-30',
      }),
      revision: 'r1',
    })
  })

  it('says how long it is open under the name', async () => {
    const { wrapper } = await panel()

    expect(wrapper.text()).toContain('Open until November 30, 2026')
  })

  it('marks a vacancy closed by its date as expired, and says no "until" for it', async () => {
    const { wrapper } = await panel(
      detail('r1', { closed: true, closed_reason: 'expired', valid_through: '2026-09-01' }),
    )

    expect(wrapper.text()).toContain('Expired')
    expect(wrapper.text()).not.toContain('Open until')
  })

  it('hides the city of a remote vacancy', async () => {
    const { wrapper } = await panel()

    expect(hasValue(wrapper, 'Kyiv')).toBe(true)

    await wrapper.findComponent({ name: 'WxSegmented' }).vm.$emit('update:modelValue', 'remote')
    await flushPromises()

    expect(hasValue(wrapper, 'Kyiv')).toBe(false)
  })

  it('keeps the words of "Duties" per language inside each row', async () => {
    const { wrapper, put } = await panel()
    const line = wrapper.findAll('input').find((one) => one.element.value === 'Write the API')!

    await line.setValue('Write and test the API')
    await wrapper.find('.wx-vacancy-editor').trigger('focusout')
    await flushPromises()

    const sent = put.mock.calls[0]?.[1] as { values: ScreenModel }

    expect(sent.values.duties).toEqual([{ text: { en: 'Write and test the API' } }])
  })

  it('shows a 422 on the salary under "To", where the server refused it', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 422,
      body: { errors: { salary_max: ['“To” is less than “From”.'] } },
    })

    await wrapper.find('input').setValue('Mine')
    await wrapper.find('.wx-vacancy-editor').trigger('focusout')
    await flushPromises()

    const items = wrapper.findAll('.wx-form-item')
    const to = items.find((one) => one.text().startsWith('To'))!

    expect(to.text()).toContain('“To” is less than “From”.')
  })

  it('shows a 422 under the line of "Duties" it belongs to', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 422,
      body: { errors: { 'duties.0.text': ['Too long.'] } },
    })

    await wrapper.find('input').setValue('Mine')
    await wrapper.find('.wx-vacancy-editor').trigger('focusout')
    await flushPromises()

    expect(wrapper.find('.wx-repeater').text()).toContain('Too long.')
  })

  it('duplicates from the action bar, saving first, and opens the copy', async () => {
    const { wrapper, put, post, get, router } = await panel()

    await wrapper.find('input').setValue('PHP developer, Lviv')

    const button = wrapper
      .findAll('.wx-action-bar button')
      .find((one) => one.text().includes('Duplicate'))!

    await button.trigger('click')
    await flushPromises()

    // What was typed goes first, so the copy is made from it.
    expect(put).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenCalledWith('/api/cms/vacancies/7/duplicate', {})
    expect(router.currentRoute.value.path).toBe('/vacancies/8')
    expect(get).toHaveBeenCalledWith('/api/cms/vacancies/8')
  })

  it('opens the history on mousedown, which is the event the tabs listen for', async () => {
    const { wrapper, get } = await panel()

    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/vacancies/7/versions')
    expect(wrapper.find('.wx-vacancy-history').text()).toContain('never been published')
  })
})
