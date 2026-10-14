import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import RegionsPage from './RegionsPage.vue'
import type { RegionRow } from './types'

function row(name: string, extra: Partial<RegionRow> = {}): RegionRow {
  return {
    name,
    id: null,
    title: name.charAt(0).toUpperCase() + name.slice(1),
    description: null,
    allow: null,
    max: null,
    published: false,
    published_at: null,
    has_draft: false,
    count: 0,
    fallback: `components.${name}`,
    updated_at: null,
    ...extra,
  }
}

async function panel(rows: RegionRow[]) {
  const get = vi.fn().mockResolvedValue({ data: rows })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: {},
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/:all(.*)', component: { template: '<div />' } }],
  })

  const wrapper = mount(RegionsPage, {
    global: {
      plugins: [router],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
    },
  })

  await flushPromises()

  return { wrapper, get }
}

describe('WxRegionsPage', () => {
  it('draws a card per declared region, linking to its editor', async () => {
    const { wrapper, get } = await panel([
      row('header', { id: 1, published: true, count: 2, description: 'Top of every page.' }),
      row('footer'),
    ])

    expect(get).toHaveBeenCalledWith('/api/cms/regions')

    const cards = wrapper.findAll('.wx-regions__card')

    expect(cards).toHaveLength(2)
    expect(cards[0]!.attributes('href')).toBe('/regions/header')
    expect(cards[0]!.text()).toContain('Top of every page.')
    expect(cards[0]!.text()).toContain('Blocks: 2')
  })

  it('puts the shared blocks in a group of their own, with where each stands', async () => {
    const use = {
      model: 'Page',
      id: 1,
      title: 'About',
      published: true,
      live: true,
      draft: false,
      url: '/about',
    }
    const { wrapper } = await panel([
      row('header', { used_on: null }),
      row('request', { fragment: true, used_on: [use, { ...use, id: 2 }] }),
      row('footer', { used_on: null }),
      row('promo', { fragment: true, used_on: [] }),
    ])

    const sections = wrapper.findAll('.wx-regions__section')

    expect(sections.map((one) => one.get('.wx-regions__heading').text())).toEqual([
      'Header and footer',
      'Shared blocks',
    ])
    expect(sections[0]!.findAll('.wx-regions__name').map((one) => one.text())).toEqual([
      'header',
      'footer',
    ])

    const [request, promo] = sections[1]!.findAll('.wx-regions__card')

    expect(request!.text()).toContain('Pages: 2')
    expect(promo!.text()).toContain('No page places it yet.')
  })

  it('keeps one list without a heading on a site with no shared blocks', async () => {
    const { wrapper } = await panel([row('header'), row('footer')])

    expect(wrapper.findAll('.wx-regions__section')).toHaveLength(1)
    expect(wrapper.find('.wx-regions__heading').exists()).toBe(false)
  })

  it('says whether the site shows the blocks or the code', async () => {
    const { wrapper } = await panel([
      row('header', { id: 1, published: true, has_draft: true }),
      row('footer'),
      row('aside', { id: 3, has_draft: true, fallback: null }),
    ])

    const [header, footer, aside] = wrapper.findAll('.wx-regions__card')

    expect(header!.text()).toContain('On the site')
    expect(header!.text()).toContain('Edits waiting')
    expect(footer!.text()).toContain('Fallback from code')
    expect(footer!.text()).toContain('From code: components.footer')
    // Never on the site, with a draft: still the code, and the draft said beside it.
    expect(aside!.text()).toContain('Fallback from code')
    expect(aside!.text()).toContain('Draft')
    expect(aside!.text()).toContain('Nothing from code')
  })

  it('explains how a region is declared when there is none', async () => {
    const { wrapper } = await panel([])

    expect(wrapper.findAll('.wx-regions__card')).toHaveLength(0)
    expect(wrapper.text()).toContain('No regions are declared on this site.')
  })
})
