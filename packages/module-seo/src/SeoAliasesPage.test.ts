import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import SeoAliasesPage from './SeoAliasesPage.vue'

/**
 * The screen renders, and the rows are the registry's.
 *
 * Nothing about how it looks is tested here and nothing can be — jsdom computes no layout. What
 * this pins down is that the page asks the right address and prints what comes back, which is
 * the part that would otherwise first be seen by an editor: this section has no entity with
 * addresses to show until the first content module arrives.
 */
function panel(rows: Record<string, unknown>[]) {
  const get = vi.fn().mockResolvedValue({
    data: rows,
    meta: { current_page: 1, last_page: 1, per_page: 25, total: rows.length, from: 1, to: 1 },
  })

  // The real dictionary, because the package's own English is what a panel sees before the
  // server's translations arrive — and a screen that shows keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/:all(.*)', component: { template: '<div />' } }],
  })

  return {
    get,
    wrapper: mount(SeoAliasesPage, {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

describe('WxSeoAliasesPage', () => {
  it('shows the address a rename left behind and where it leads now', async () => {
    const { wrapper, get } = panel([
      {
        id: 1,
        locale: 'en',
        pattern: '/about',
        target: '/about-us',
        url: 'https://example.test/about',
        target_url: 'https://example.test/about-us',
        entity_type: 'page',
        entity_id: 7,
        created_at: null,
      },
    ])

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/seo/aliases', expect.any(Object))
    expect(wrapper.text()).toContain('/about')
    expect(wrapper.find('a[href="https://example.test/about-us"]').exists()).toBe(true)
  })

  it('says so when the row leads nowhere', async () => {
    // Broken data rather than a redirect loop waiting to happen: `webx:routes:check` reports it,
    // and until somebody fixes it the address is simply gone.
    const { wrapper } = panel([
      {
        id: 2,
        locale: 'en',
        pattern: '/gone',
        target: null,
        url: 'https://example.test/gone',
        target_url: null,
        entity_type: 'page',
        entity_id: 8,
        created_at: null,
      },
    ])

    await flushPromises()

    expect(wrapper.findAll('a')).toHaveLength(0)
    expect(wrapper.text()).toContain('Leads nowhere')
  })
})
