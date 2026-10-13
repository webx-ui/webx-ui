import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { WebxUI } from '@webx-ui/core'
import {
  adminKey,
  createI18n,
  i18nKey,
  type AdminContext,
  type SiteBarContext,
} from '@webx-ui/module-admin'
import SeoSiteBarTab from './SeoSiteBarTab.vue'
import { seoMessages } from './messages'
import type { SeoSiteBarReport, SeoUrlRule } from './types'

const page: SiteBarContext = {
  url: '/pricing',
  locale: 'en',
  entity: {
    type: 'page',
    id: 12,
    kind: 'Page',
    label: 'Pricing',
    status: 'published',
    draft: null,
    updatedAt: null,
    edit: '/pages/12',
    can: { edit: true, publish: false },
  },
  sections: ['page', 'seo'],
}

function report(overrides: Partial<SeoSiteBarReport> = {}): SeoSiteBarReport {
  return {
    url: '/pricing',
    locale: 'en',
    target: 'entity',
    entity: { type: 'page', id: 12 },
    rule: null,
    fields: {
      title: { value: 'Pricing — Example', source: 'fallback', own: null, overridden: null },
      description: { value: 'Plans', source: 'entity', own: 'Plans', overridden: null },
      h1: { value: null, source: null, own: null, overridden: null },
      robots: { value: null, source: null, own: 'nofollow', overridden: null },
    },
    title: null,
    can: { entity: true, rule: false },
    ...overrides,
  }
}

function rule(overrides: Partial<SeoUrlRule> = {}): SeoUrlRule {
  return {
    id: 7,
    match_type: 'exact',
    pattern: '/pricing',
    priority: 0,
    is_active: true,
    created_at: null,
    updated_at: null,
    title: { de: 'Preise' },
    h1: {},
    description: {},
    keywords: {},
    og_title: {},
    og_description: {},
    og_image: null,
    canonical: null,
    robots: null,
    json_ld: null,
    ...overrides,
  }
}

const mounted: VueWrapper[] = []

function http(answers: { get: (url: string) => unknown }) {
  return {
    get: vi.fn().mockImplementation((url: string) => Promise.resolve({ data: answers.get(url) })),
    put: vi
      .fn()
      .mockImplementation((_url: string, body: unknown) => Promise.resolve({ data: body })),
    post: vi
      .fn()
      .mockImplementation((_url: string, body: unknown) => Promise.resolve({ data: body })),
  }
}

async function open(client: ReturnType<typeof http>, context: SiteBarContext = page) {
  const i18n = createI18n()
  i18n.defaults('webx-seo', seoMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: client,
    i18n,
    modules: [{ id: 'seo', path: '/seo' }],
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const wrapper = mount(SeoSiteBarTab, {
    props: { context },
    global: {
      plugins: [WebxUI],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
    },
  })

  mounted.push(wrapper)
  await flushPromises()

  return wrapper
}

/** Stands in for the page that frames the panel: in jsdom a window is its own parent. */
function framed() {
  const postMessage = vi.fn()
  vi.spyOn(window, 'parent', 'get').mockReturnValue({ postMessage } as unknown as Window)

  return postMessage
}

/**
 * The SEO tab of the panel on the site (WEBX_UI_SITE_BAR.md, §7): where each field came from,
 * and where an edit goes. How it sits in a drawer beside a page is the browser's to check.
 */
describe('the SEO tab on the site', () => {
  afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount())
    vi.restoreAllMocks()
  })

  it('asks about the page in its own language', async () => {
    const client = http({ get: () => report() })

    await open(client)

    expect(client.get).toHaveBeenCalledWith('/api/cms/seo/site-bar', {
      query: { url: '/pricing', locale: 'en' },
    })
  })

  it('says where each field came from and starts from what the record has', async () => {
    const wrapper = await open(http({ get: () => report() }))
    const text = wrapper.text()

    expect(text).toContain('Name of the record')
    expect(text).toContain('This record')
    expect(text).toContain('Not set')
    expect(wrapper.find('textarea').element.value).toBe('Plans')
    expect(text).toContain('Saved into the SEO of this record.')
  })

  it('warns about a field a rule sets above the record, with the way to the rule', async () => {
    const wrapper = await open(
      http({
        get: () =>
          report({
            rule: { id: 7, pattern: '/pricing', match_type: 'exact', exact: true },
            fields: {
              ...report().fields,
              title: { value: 'Ruled', source: 'rule', own: 'Mine', overridden: 'rule' },
            },
          }),
      }),
    )

    expect(wrapper.text()).toContain('The rule /pricing sets this above the record')
    expect(wrapper.find('a[href="/cms/seo?rule=7"]').exists()).toBe(true)
  })

  it('saves one language of the record’s card and keeps the other robots directives', async () => {
    const client = http({ get: () => report() })
    const postMessage = framed()
    const wrapper = await open(client)

    await wrapper.find('input[type="text"], input:not([type])').setValue('Pricing that fits')
    await wrapper.find('input[type="checkbox"]').setValue(true)
    client.get.mockImplementation(() =>
      Promise.resolve({ data: report({ title: 'Pricing that fits — Example' }) }),
    )
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(client.put).toHaveBeenCalledWith('/api/cms/seo/entity/page/12', {
      seo: {
        robots: 'noindex, nofollow',
        title: { en: 'Pricing that fits' },
        description: { en: 'Plans' },
        h1: { en: null },
      },
    })
    expect(postMessage).toHaveBeenCalledWith(
      { wxBar: 1, type: 'title', title: 'Pricing that fits — Example' },
      window.location.origin,
    )
  })

  it('writes a rule for this address as a copy of the mask that covers it', async () => {
    const mask = rule({
      id: 3,
      match_type: 'mask',
      pattern: '/pri*',
      canonical: 'https://x.test/',
      robots: 'nofollow',
    })
    const client = http({
      get: (url) =>
        url.endsWith('/site-bar')
          ? report({
              target: 'rule',
              entity: null,
              rule: { id: 3, pattern: '/pri*', match_type: 'mask', exact: false },
              can: { entity: false, rule: true },
            })
          : mask,
    })
    const wrapper = await open(client)

    expect(wrapper.text()).toContain('The rule /pri* covers this address and others.')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(client.get).toHaveBeenCalledWith('/api/cms/seo/urls/3')
    expect(client.post).toHaveBeenCalledWith(
      '/api/cms/seo/urls',
      expect.objectContaining({
        match_type: 'exact',
        pattern: '/pricing',
        canonical: 'https://x.test/',
        title: { de: 'Preise' },
        description: { en: 'Plans' },
        robots: 'nofollow',
      }),
    )
  })

  it('edits the rule for this address in place', async () => {
    const exact = rule()
    const client = http({
      get: (url) =>
        url.endsWith('/site-bar')
          ? report({
              target: 'rule',
              entity: null,
              rule: { id: 7, pattern: '/pricing', match_type: 'exact', exact: true },
              can: { entity: false, rule: true },
            })
          : exact,
    })
    const wrapper = await open(client)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(client.put).toHaveBeenCalledWith(
      '/api/cms/seo/urls/7',
      expect.objectContaining({
        match_type: 'exact',
        pattern: '/pricing',
        title: { de: 'Preise' },
      }),
    )
    expect(client.post).not.toHaveBeenCalled()
  })
})
