import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, adminTypes, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import ServiceEditorPage from './ServiceEditorPage.vue'
import ServiceHistory from './ServiceHistory.vue'
import type { ServiceDetail, ServiceRow } from './types'

const implants: ServiceRow = {
  id: 7,
  title: 'Implants',
  slug: 'implants',
  lead: '',
  path: 'services/implants',
  url: 'https://example.test/services/implants',
  status: 'published',
  position: 1,
  published_at: '2026-09-12T08:00:00+00:00',
  updated_at: '2026-09-12T08:00:00+00:00',
  deleted_at: null,
  cover: null,
  categories: [],
  revision: 'r1',
}

function detail(revision: string, over: Partial<ServiceRow> = {}): ServiceDetail {
  return {
    service: { ...implants, ...over, revision },
    values: { title: { en: over.title ?? 'Implants' }, slug: { en: 'implants' }, blocks: [] },
    revision,
    prefix: 'services',
    preview_url: 'https://example.test/_preview/service/7?token=x',
  }
}

/* The settings tab with the two fields worth checking here, and the history behind a tab. */
const screen: ScreenNode[] = [
  {
    id: 'tabs',
    type: 'wx-tabs',
    children: [
      {
        id: 'settings',
        type: 'wx-tab',
        label: 'Settings',
        children: [
          { id: 'title', type: 'wx-input', name: 'title', localized: true },
          { id: 'slug', type: 'wx-slug', name: 'slug', localized: true },
        ],
      },
      {
        id: 'history',
        type: 'wx-tab',
        label: 'History',
        children: [{ id: 'versions', type: 'wx-service-history' }],
      },
    ],
  },
]

async function panel(first = detail('r1')) {
  const get = vi.fn().mockImplementation((url: string) =>
    Promise.resolve(
      url.endsWith('/versions')
        ? {
            data: [
              {
                number: 1,
                created_at: '2026-09-12T08:00:00+00:00',
                author: 'Anna',
                source: 'panel',
                comment: null,
                is_pinned: false,
              },
            ],
          }
        : { data: first },
    ),
  )

  const put = vi.fn().mockResolvedValue({ data: detail('r2', { title: 'Dental implants' }) })
  const post = vi.fn().mockResolvedValue({ data: implants })
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
      'wx-service-history': { component: ServiceHistory, kind: 'display' },
    },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/services', component: { template: '<div />' } },
      { path: '/services/:id(\\d+)', component: ServiceEditorPage, props: { base: '/services' } },
    ],
  })

  await router.push('/services/7')
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

afterEach(() => {
  vi.useRealTimers()
})

/**
 * Saving, the revision and the history — what this screen owns. The height of the constructor
 * and the bar at the bottom are layout, checked in a browser.
 */
describe('WxServiceEditorPage', () => {
  it('writes the draft after a pause, carrying the revision it read', async () => {
    vi.useFakeTimers()
    const { wrapper, put } = await panel()

    await wrapper.find('input').setValue('Dental implants')
    await nextTick()
    expect(put).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1500)
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/services/7', {
      values: expect.objectContaining({ title: { en: 'Dental implants' } }),
      revision: 'r1',
    })
  })

  it('puts a conflict on the screen instead of one version over the other', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 409,
      body: { message: 'Somebody changed this service.', data: detail('r9') },
    })

    await wrapper.find('input').setValue('Mine')
    await wrapper.find('.wx-service-editor').trigger('focusout')
    await flushPromises()

    expect(wrapper.text()).toContain('Somebody changed this service.')

    // Keeping mine writes over theirs with their revision.
    await wrapper.findAll('.wx-alert button').at(-1)?.trigger('click')
    await flushPromises()

    expect(put.mock.calls[1]?.[1]).toMatchObject({ revision: 'r9' })
  })

  it('prints the prefix of the catalogue in front of the slug, and warns before it moves', async () => {
    const { wrapper } = await panel()

    expect(wrapper.find('.wx-slug__prefix').text()).toBe('/services/')
    expect(wrapper.find('.wx-slug .wx-alert').exists()).toBe(false)

    await wrapper.get('.wx-slug input').setValue('dental-implants')
    await nextTick()

    expect(wrapper.find('.wx-slug .wx-alert').text()).toContain('The address is changing')
  })

  it('shows a 422 under the address, where the registry refused it', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 422,
      body: { errors: { 'slug.en': ['This address is already taken by "Implantology".'] } },
    })

    await wrapper.find('input').setValue('Implantology')
    await wrapper.find('.wx-service-editor').trigger('focusout')
    await flushPromises()

    expect(wrapper.text()).toContain('already taken by "Implantology"')
  })

  it('opens the history on mousedown, which is the event the tabs listen for', async () => {
    const { wrapper, get } = await panel()
    const tabs = wrapper.findAll('.wx-tabs__tab')

    expect(get).not.toHaveBeenCalledWith('/api/cms/services/7/versions')

    await tabs[1]!.trigger('mousedown')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/services/7/versions')
    expect(wrapper.find('.wx-service-history').text()).toContain('Anna')
  })

  it('publishes after asking, and reads the service again', async () => {
    const { wrapper, post, get } = await panel(detail('r1', { status: 'draft' }))

    await wrapper.findAll('.wx-action-bar button').at(-1)?.trigger('click')
    await flushPromises()

    // The question is a dialog mounted outside the tree; its confirm button is in the document.
    const confirm = [...document.querySelectorAll('button')].find(
      (button) => button.textContent?.trim() === 'Publish' && !wrapper.element.contains(button),
    )
    confirm?.click()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/services/7/publish', {})
    expect(get.mock.calls.filter(([url]) => url === '/api/cms/services/7')).toHaveLength(2)
  })

  it('leaves without asking when the save is already on its way', async () => {
    const { wrapper, put, router } = await panel()

    let answer: (value: unknown) => void = () => {}
    put.mockImplementationOnce(() => new Promise((resolve) => (answer = resolve)))

    await wrapper.find('input').setValue('Dental implants')
    await wrapper.find('.wx-service-editor').trigger('focusout')
    expect(put).toHaveBeenCalledTimes(1)

    // Leaving while that request is out used to skip the save, read `dirty` and ask.
    const leaving = router.push('/services')
    await flushPromises()

    answer({ data: detail('r2', { title: 'Dental implants' }) })
    await leaving
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/services')
    expect(put).toHaveBeenCalledTimes(1)
  })
})
