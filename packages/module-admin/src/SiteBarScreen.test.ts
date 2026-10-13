import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { WebxUI } from '@webx-ui/core'
import SiteBarScreen from './SiteBarScreen.vue'
import { adminKey, type AdminContext } from './admin'
import { HttpError } from './http'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'
import type { SiteBarContext } from './siteBar'

const page: SiteBarContext = {
  url: '/pricing',
  locale: 'en',
  entity: {
    type: 'page',
    id: 12,
    kind: 'Page',
    label: 'Pricing',
    status: 'published',
    draft: { differs: false, by: null, at: null },
    updatedAt: '2026-10-13T08:00:00+00:00',
    edit: '/pages/12',
    can: { edit: true, publish: false },
  },
  sections: ['page'],
}

// Every screen listens for Esc on the window; one left mounted would answer the next test's.
const mounted: VueWrapper[] = []

async function open(answer: () => Promise<unknown>, url = '/pricing') {
  const get = vi.fn().mockImplementation(answer)
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/_bar', component: SiteBarScreen }],
  })

  await router.push(`/_bar?url=${encodeURIComponent(url)}`)

  const wrapper = mount(SiteBarScreen, {
    global: {
      plugins: [WebxUI, router],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
    },
  })

  mounted.push(wrapper)
  await flushPromises()

  return { wrapper, get }
}

/**
 * The drawer beside a page of the site (WEBX_UI_SITE_BAR.md, §5): what the page is, and the way
 * to it in the panel. How it looks next to the site is the browser's to check; what is worth a
 * test here is what it asks and what it does with the answer.
 */
describe('the panel on the site', () => {
  afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount())
    vi.restoreAllMocks()
  })

  it('asks about the address the page wrote into its frame', async () => {
    const { get } = await open(() => Promise.resolve({ data: page }), '/pricing?utm=mail')

    expect(get).toHaveBeenCalledWith('/api/cms/site-bar/context?url=%2Fpricing%3Futm%3Dmail')
  })

  it('names the record and its state', async () => {
    const { wrapper } = await open(() => Promise.resolve({ data: page }))

    expect(wrapper.text()).toContain('Page · Pricing')
    expect(wrapper.text()).toContain('Published')
    expect(wrapper.text()).toContain('/pricing')
  })

  it('opens the record in the whole window, not inside the frame', async () => {
    const { wrapper } = await open(() => Promise.resolve({ data: page }))

    const links = wrapper.findAll('a[href="/cms/pages/12"]')

    expect(links.length).toBeGreaterThan(0)
    for (const link of links) expect(link.attributes('target')).toBe('_top')
  })

  it('says who left a draft before anybody edits on top of it', async () => {
    const drafted = {
      ...page,
      entity: { ...page.entity!, draft: { differs: true, by: 'J. Doe', at: null } },
    }

    const { wrapper } = await open(() => Promise.resolve({ data: drafted }))

    expect(wrapper.text()).toContain('J. Doe has unpublished changes here')
  })

  it('says when there is no record behind the address', async () => {
    const { wrapper } = await open(() =>
      Promise.resolve({ data: { ...page, entity: null, sections: ['page'] } }),
    )

    expect(wrapper.text()).toContain('This page')
    expect(wrapper.text()).toContain('Nothing in the panel stands behind this address')
    expect(wrapper.find('a[target="_top"]').exists()).toBe(false)
  })

  it('passes the refusal on in the words of the server', async () => {
    const { wrapper } = await open(() =>
      Promise.reject(new HttpError('There is nothing on this page you may edit.', 403)),
    )

    expect(wrapper.text()).toContain('There is nothing on this page you may edit.')
  })

  it('asks the page to shut the drawer, and only the page of its own origin', async () => {
    const post = vi.fn()
    const parent = { postMessage: post } as unknown as Window
    vi.spyOn(window, 'parent', 'get').mockReturnValue(parent)

    const { wrapper } = await open(() => Promise.resolve({ data: page }))

    await wrapper.get('button[aria-label="Close"]').trigger('click')
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(post).toHaveBeenCalledTimes(2)
    expect(post).toHaveBeenCalledWith({ wxBar: 1, type: 'close' }, window.location.origin)
  })
})
