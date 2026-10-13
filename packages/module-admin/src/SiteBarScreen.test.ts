import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h, markRaw } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { WebxUI } from '@webx-ui/core'
import SiteBarScreen from './SiteBarScreen.vue'
import { adminKey, type AdminContext } from './admin'
import { HttpError } from './http'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'
import type { SiteBarContext } from './siteBar'
import type { AdminModule } from './types'

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

async function open(
  answer: () => Promise<unknown>,
  url = '/pricing',
  modules: AdminModule[] = [],
  locale?: string,
  post = vi.fn().mockResolvedValue({ data: {} }),
) {
  const get = vi.fn().mockImplementation(answer)
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post },
    i18n,
    modules,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/_bar', component: SiteBarScreen }],
  })

  await router.push(
    `/_bar?url=${encodeURIComponent(url)}${locale ? `&locale=${encodeURIComponent(locale)}` : ''}`,
  )

  const wrapper = mount(SiteBarScreen, {
    global: {
      plugins: [WebxUI, router],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
    },
  })

  mounted.push(wrapper)
  await flushPromises()

  return { wrapper, get, post }
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

  it('passes the language of the page along with its address', async () => {
    const { get } = await open(() => Promise.resolve({ data: page }), '/kontakty', [], 'uk')

    // What the script read off `<html lang>`: a page of the site's own code has no other source.
    expect(get).toHaveBeenCalledWith('/api/cms/site-bar/context?url=%2Fkontakty&locale=uk')
  })

  it('draws the tabs modules add, only where the server listed them', async () => {
    const seo: AdminModule = {
      id: 'seo',
      siteBarTabs: [
        {
          name: 'seo',
          label: () => 'SEO',
          component: markRaw(
            defineComponent({
              props: { context: { type: Object, required: true } },
              setup: (props) => () =>
                h('p', { class: 'module-tab' }, (props.context as SiteBarContext).url),
            }),
          ),
        },
      ],
    }

    const listed = await open(
      () => Promise.resolve({ data: { ...page, entity: null, sections: ['seo', 'blocks'] } }),
      '/contacts',
      [seo],
    )

    // The only tab here, so it is the one open — and «blocks», which no module draws, is not there.
    expect(listed.wrapper.findAll('[role="tab"]').map((one) => one.text())).toEqual(['SEO'])
    expect(listed.wrapper.find('.module-tab').text()).toBe('/pricing')

    const unlisted = await open(() => Promise.resolve({ data: page }), '/pricing', [seo])

    expect(unlisted.wrapper.find('.module-tab').exists()).toBe(false)
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

  it('offers to publish or discard a draft, counting what it changes', async () => {
    const drafted = {
      ...page,
      entity: {
        ...page.entity!,
        revision: 'r7',
        draft: { differs: true, by: 'J. Doe', at: null, changes: 3, mine: false },
        can: { edit: true, publish: true },
      },
    }
    const parent = { postMessage: vi.fn() } as unknown as Window
    vi.spyOn(window, 'parent', 'get').mockReturnValue(parent)

    const { wrapper, post } = await open(() => Promise.resolve({ data: drafted }))

    expect(wrapper.text()).toContain('Unpublished changes: 3')
    // Said before the first edit lands in somebody else's draft.
    expect(wrapper.text()).toContain('J. Doe has a draft here; your edits go into it')
    expect(wrapper.text()).toContain('Discard')

    await wrapper
      .findAll('button')
      .find((one) => one.text() === 'Publish')!
      .trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/site-bar/publish', {
      type: 'page',
      id: 12,
      revision: 'r7',
    })
    // The page is the site's again: it loads once more.
    expect(parent.postMessage).toHaveBeenCalledWith(
      { wxBar: 1, type: 'reload' },
      window.location.origin,
    )
  })

  it('keeps to the plain notice where the record may not be published from here', async () => {
    const drafted = {
      ...page,
      entity: {
        ...page.entity!,
        draft: { differs: true, by: 'J. Doe', at: null, changes: 1, mine: false },
      },
    }

    const { wrapper } = await open(() => Promise.resolve({ data: drafted }))

    expect(wrapper.text()).toContain('J. Doe has unpublished changes here')
    expect(wrapper.findAll('button').some((one) => one.text() === 'Publish')).toBe(false)
  })
})
