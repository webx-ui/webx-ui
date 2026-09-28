import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory, type Router } from 'vue-router'
import {
  adminKey,
  adminMessages,
  adminTypes,
  createI18n,
  i18nKey,
  type AdminContext,
} from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import type * as Core from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import BannerEditorPage from './BannerEditorPage.vue'
import BannersPage from './BannersPage.vue'
import type { BannerDetail, BannerRow, PlaceRow } from './types'

const { confirm } = vi.hoisted(() => ({ confirm: vi.fn() }))

/* The dialog itself is the core's; what is checked here is that the screens ask at all. */
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof Core>()),
  confirm,
}))

const places: PlaceRow[] = [
  { id: 1, key: 'hero', title: 'Hero', declared: true, layout: 'slider', count: 2 },
  // Declared and never saved into: no row yet (decision 1).
  { id: null, key: 'promo', title: 'Promo', declared: true, layout: 'single', count: 0 },
  {
    id: 2,
    key: 'side',
    title: 'Sidebar',
    titles: { en: 'Sidebar' },
    declared: false,
    layout: 'slider',
    count: 1,
  },
]

function banner(id: number, title: string, over: Partial<BannerRow> = {}): BannerRow {
  return {
    id,
    title,
    thumb: `/thumbs/${id}.jpg`,
    video: false,
    enabled: true,
    position: id,
    updated_at: '2026-09-28T09:00:00+00:00',
    deleted_at: null,
    ...over,
  }
}

const hero: BannerRow[] = [
  banner(1, 'Spring sale', { video: true }),
  banner(2, 'Workshop', { enabled: false, thumb: null }),
]

function detail(id: number, place = 'hero'): BannerDetail {
  return {
    banner: { id, place, title: 'Spring sale', enabled: true, deleted_at: null },
    values: {
      image: { path: 'banners/spring.jpg' },
      image_mobile: null,
      video: null,
      title: { en: 'Spring sale' },
      text: {},
      buttons: [],
      enabled: true,
    },
  }
}

/* A slice of `banners.form`: a title to type is all a test needs to make the form changed. */
const screen: ScreenNode[] = [
  { id: 'title', type: 'wx-input', name: 'title', localized: true },
  { id: 'enabled', type: 'wx-switch', name: 'enabled' },
]

let router: Router

async function panel(path: string, can = true) {
  const get = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/banners/places')) return Promise.resolve({ data: places })
    if (url.includes('/places/hero/banners')) return Promise.resolve({ data: hero })
    if (url.includes('/banners?trashed') || url.includes('/banners')) {
      const one = /\/banners\/(\d+)$/.exec(url)

      if (one) return Promise.resolve({ data: detail(Number(one[1])) })
    }

    return Promise.resolve({ data: [] })
  })
  const post = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/reorder')) return Promise.resolve(undefined)

    return Promise.resolve({ data: detail(9) })
  })
  const put = vi
    .fn()
    .mockImplementation((_url: string, body: { place?: string }) =>
      Promise.resolve({ data: detail(1, body.place ?? 'hero') }),
    )
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put, delete: vi.fn().mockResolvedValue(undefined) },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => can,
    types: { ...coreTypes, ...adminTypes },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/banners', component: BannersPage, props: { base: '/banners' } },
      { path: '/banners/new', component: BannerEditorPage, props: { base: '/banners' } },
      { path: '/banners/:id(\\d+)', component: BannerEditorPage, props: { base: '/banners' } },
      { path: '/elsewhere', component: { template: '<div />' } },
    ],
  })

  await router.push(path)
  await router.isReady()

  const wrapper = mount(
    { template: '<router-view />' },
    {
      attachTo: document.body,
      global: {
        plugins: [router],
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          [localesKey as symbol]: {
            list: ref([{ code: 'en', name: 'English' }]),
            active: ref('en'),
          },
        },
      },
    },
  )

  await flushPromises()

  return { wrapper, get, post, put }
}

beforeEach(() => {
  confirm.mockReset()
})

afterEach(() => {
  document.body.innerHTML = ''
})

/**
 * Where the banners of a place go on a phone — into the drawer, with its own way back — is a
 * width, and jsdom measures none: that is checked in a browser.
 */
describe('WxBannersPage', () => {
  it('lists every place, declared ones before a row exists, with the call a template makes', async () => {
    const { wrapper } = await panel('/banners?place=hero')

    const rows = wrapper.findAll('.wx-banners__row')

    expect(rows.map((one) => one.text())).toEqual([
      expect.stringContaining("banners('hero')"),
      expect.stringContaining("banners('promo')"),
      expect.stringContaining("banners('side')"),
    ])
    // Declared places carry the lock and no menu: a template asks for them by key.
    expect(rows[0]!.find('.wx-banners__lock').exists()).toBe(true)
    expect(rows[0]!.find('.wx-row-menu').exists()).toBe(false)
    expect(rows[2]!.find('.wx-banners__lock').exists()).toBe(false)
  })

  it('shows the banners of the open place: a thumbnail, the video, and what is switched off', async () => {
    const { wrapper, get } = await panel('/banners?place=hero')

    expect(get).toHaveBeenCalledWith('/api/cms/banners/places/hero/banners')

    const [sale, workshop] = wrapper.findAll('.wx-banner-row')

    expect(sale!.get('img').attributes('src')).toBe('/thumbs/1.jpg')
    expect(sale!.text()).toContain('Video')
    expect(sale!.classes()).not.toContain('is-off')
    expect(workshop!.find('img').exists()).toBe(false)
    expect(workshop!.classes()).toContain('is-off')
    expect(workshop!.text()).toContain('Off')
    expect(wrapper.text()).toContain("banners('hero')")
  })

  it('writes the order of the place it is looking at', async () => {
    const { wrapper, post } = await panel('/banners?place=hero')
    const grip = wrapper.findAll('.wx-sortable-list__grip')[0]!

    await grip.trigger('keydown', { key: ' ' })
    await grip.trigger('keydown', { key: 'ArrowDown' })
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/banners/places/hero/reorder', { ids: [2, 1] })
  })

  it('opens the bin of the place with nothing to drag', async () => {
    const { wrapper, get } = await panel('/banners?place=hero&view=trashed')

    expect(get).toHaveBeenCalledWith('/api/cms/banners/places/hero/banners?trashed=1')
    expect(wrapper.find('.wx-sortable-list__grip').exists()).toBe(false)
  })
})

describe('WxBannerEditorPage', () => {
  it('makes a new banner in the place it was asked for, then moves to its address', async () => {
    const { wrapper, post } = await panel('/banners/new?place=promo')

    await wrapper.get('input[type="text"]:not([disabled])').setValue('Autumn')
    await wrapper.get('.wx-action-bar .wx-button--primary').trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/banners/places/promo/banners', {
      values: expect.objectContaining({ title: { en: 'Autumn' }, enabled: false }),
    })
    expect(router.currentRoute.value.fullPath).toBe('/banners/9')
  })

  it('sends the place beside the values only when it changed (decision 9)', async () => {
    const { wrapper, put } = await panel('/banners/1')

    wrapper.findComponent({ name: 'WxSelect' }).vm.$emit('update:modelValue', 'side')
    await flushPromises()
    await wrapper.get('.wx-action-bar .wx-button--primary').trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/banners/1', {
      values: expect.any(Object),
      place: 'side',
    })
    // Back leads to where the banner stands now.
    expect(wrapper.get('.wx-screen-head__back').attributes('href')).toContain('place=side')
  })

  it('puts a refusal under the field it names', async () => {
    const { wrapper, put } = await panel('/banners/1')

    put.mockRejectedValueOnce({ body: { errors: { place: ['No such place.'] } } })
    await wrapper.get('input[type="text"]:not([disabled])').setValue('Changed')
    await wrapper.get('.wx-action-bar .wx-button--primary').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('No such place.')
  })

  it('asks before leaving with something unsaved', async () => {
    const { wrapper } = await panel('/banners/1')

    confirm.mockResolvedValue(false)
    await wrapper.get('input[type="text"]:not([disabled])').setValue('Changed')
    await router.push('/elsewhere')
    await flushPromises()

    expect(confirm).toHaveBeenCalled()
    expect(router.currentRoute.value.path).toBe('/banners/1')
  })
})
