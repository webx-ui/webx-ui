import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import SeoLinksPage from './SeoLinksPage.vue'

/**
 * Interlinking is there only where the manifest says so (§18.3): the view in the strip, and the
 * list asking the server. Off, nothing is asked — the routes answer 404 there — and the screen
 * says the tool is off instead of showing an error.
 */
function panel(links: boolean) {
  const get = vi.fn().mockResolvedValue({
    data: [
      {
        id: 1,
        donor: {
          url: '/catalog/laptops',
          saved_url: '/catalog/laptops',
          locale: 'en',
          entity_type: 'catalog.category',
          entity_id: 3,
          broken: false,
        },
        heading: null,
        is_active: true,
        links_count: 3,
        broken_count: 1,
        updated_at: null,
      },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
  })

  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get },
    i18n,
    state: {
      manifest: { modules: [{ id: 'seo', title: 'SEO', meta: { links, faq: false } }] },
      user: null,
      status: 'ready',
      error: null,
    },
    can: () => true,
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/:all(.*)', component: { template: '<div />' } }],
  })

  return {
    get,
    wrapper: mount(SeoLinksPage, {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

describe('WxSeoLinksPage', () => {
  it('lists the donors with their links and broken ones counted', async () => {
    const { wrapper, get } = panel(true)

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/seo/links', expect.any(Object))
    expect(wrapper.findAll('[role=tab]').map((tab) => tab.text())).toContain('Interlinking')
    expect(wrapper.text()).toContain('/catalog/laptops')
    // An empty heading is the default one, said so rather than left blank.
    expect(wrapper.text()).toContain('From the settings')
  })

  it('asks nothing and says the tool is off where the site has it off', async () => {
    const { wrapper, get } = panel(false)

    await flushPromises()

    expect(get).not.toHaveBeenCalled()
    expect(wrapper.findAll('[role=tab]').map((tab) => tab.text())).not.toContain('Interlinking')
    expect(wrapper.text()).toContain('Interlinking is turned off on this site.')
  })
})
