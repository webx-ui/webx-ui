import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import SeoUrlsPage from './SeoUrlsPage.vue'

/**
 * The page FAQ is there only where the manifest says so (§18.3, §18.5): the count of questions
 * in the list and the import and export of them. Off, the list is the list it always was.
 */
function panel(faq: boolean, excludedTypes: unknown[] = []) {
  const get = vi.fn().mockImplementation((url: string) =>
    Promise.resolve(
      url.endsWith('/sitemap')
        ? {
            data: {
              enabled: true,
              url: '/sitemap.xml',
              built_at: null,
              files: {},
              total: 0,
              excluded: { noindex: 0, canonical: 0 },
              excluded_types: excludedTypes,
            },
          }
        : {
            data: [
              {
                id: 1,
                match_type: 'exact',
                pattern: '/delivery',
                priority: 0,
                is_active: true,
                title: {},
                created_at: null,
                updated_at: null,
                ...(faq ? { faq_count: 3 } : {}),
              },
            ],
            meta: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
          },
    ),
  )

  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get },
    i18n,
    state: {
      manifest: { modules: [{ id: 'seo', title: 'SEO', meta: { links: false, faq } }] },
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
    wrapper: mount(SeoUrlsPage, {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

describe('WxSeoUrlsPage', () => {
  it('counts the questions of a page where the FAQ is on', async () => {
    const { wrapper } = panel(true)

    await flushPromises()

    const headers = wrapper.findAll('th').map((cell) => cell.text())

    expect(headers).toContain('FAQ')
    expect(wrapper.find('tbody').text()).toContain('3')
  })

  it('has no FAQ column where the FAQ is off', async () => {
    const { wrapper, get } = panel(false)

    await flushPromises()

    expect(wrapper.findAll('th').map((cell) => cell.text())).not.toContain('FAQ')
    expect(get).toHaveBeenCalledWith(
      '/api/cms/seo/urls',
      expect.objectContaining({ query: expect.objectContaining({ has_faq: undefined }) }),
    )
  })

  it('names the address types the sitemap leaves out because they are not pages', async () => {
    const { wrapper } = panel(false, [
      { type: 'event', reason: 'not-a-page', handler: 'EventRedirect', addresses: 6 },
      { type: 'recipe-category', reason: 'not-a-page', handler: 'MealFilter', addresses: 5 },
    ])

    await flushPromises()

    const card = wrapper.find('.wx-seo-sitemap').text()

    expect(card).toContain('redirect instead of showing a page')
    expect(card).toContain('event (6), recipe-category (5)')
  })
})
