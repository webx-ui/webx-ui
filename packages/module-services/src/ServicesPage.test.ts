import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import {
  adminKey,
  adminMessages,
  createI18n,
  i18nKey,
  type AdminContext,
} from '@webx-ui/module-admin'
import ServicesPage from './ServicesPage.vue'
import type { ServiceRow, ServicesList } from './types'

function row(id: number, title: string, over: Partial<ServiceRow> = {}): ServiceRow {
  return {
    id,
    title,
    slug: title.toLowerCase(),
    lead: '',
    path: `services/${title.toLowerCase()}`,
    url: `https://example.test/services/${title.toLowerCase()}`,
    status: 'published',
    position: id,
    published_at: '2026-09-12T08:00:00+00:00',
    updated_at: '2026-09-12T08:00:00+00:00',
    deleted_at: null,
    cover: null,
    categories: [],
    revision: `r${id}`,
    ...over,
  }
}

const list: ServicesList = {
  data: [
    row(1, 'Implants', {
      categories: [
        { id: 3, title: 'Surgery' },
        { id: 4, title: 'Implantology' },
      ],
    }),
    row(2, 'Crowns', { status: 'draft' }),
    row(3, 'Whitening', { status: 'modified' }),
  ],
  filters: {
    categories: [
      { id: 3, title: 'Surgery' },
      { id: 4, title: 'Implantology' },
    ],
  },
}

async function panel(query = '', can = true) {
  const get = vi.fn().mockResolvedValue(list)
  const post = vi.fn().mockResolvedValue(undefined)
  const i18n = createI18n()
  // What the panel seeds before any screen: the words of the order are the panel's, not ours.
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put: vi.fn(), delete: vi.fn() },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => can,
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/services', component: ServicesPage, props: { base: '/services' } },
      { path: '/services/:id(\\d+)', component: { template: '<div />' } },
    ],
  })

  await router.push(`/services${query}`)
  await router.isReady()

  const wrapper = mount(
    { template: '<router-view />' },
    {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    },
  )

  await flushPromises()

  return { wrapper, get, post, router }
}

/** Pick the first row up with the keyboard and put it one place down — a drag, minus the mouse. */
async function moveFirstDown(wrapper: Awaited<ReturnType<typeof panel>>['wrapper']) {
  const grip = wrapper.findAll('.wx-sortable-list__grip')[0]!

  await grip.trigger('keydown', { key: ' ' })
  await grip.trigger('keydown', { key: 'ArrowDown' })
  await flushPromises()
}

/**
 * The list and its order. What a row looks like at 375px — facts under the name rather than
 * beside it — is a container query, and jsdom computes no layout: that is checked in a browser.
 */
describe('WxServicesPage', () => {
  it('asks for the whole catalogue, with no page in the request', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/services')
    expect(wrapper.findAll('.wx-service-row')).toHaveLength(3)
    expect(wrapper.text()).toContain('/services/implants')
  })

  it('marks the first category as the main one', async () => {
    const { wrapper } = await panel()

    const chips = wrapper.findAll('.wx-service-row')[0]!.findAll('.wx-service-row__chips .wx-badge')

    expect(chips.map((chip) => chip.text())).toEqual(['Surgery', 'Implantology'])
    expect(chips[0]!.classes().join(' ')).toContain('primary')
    expect(chips[1]!.classes().join(' ')).not.toContain('primary')
  })

  it('writes the order of the whole list when nothing narrows it', async () => {
    const { wrapper, post } = await panel()

    await moveFirstDown(wrapper)

    expect(post).toHaveBeenCalledWith('/api/cms/services/reorder', { ids: [2, 1, 3] })
    expect(wrapper.text()).toContain('Drag to change the order on the site.')
  })

  it('writes the order of one category, and only that, when the list is narrowed to it', async () => {
    const { wrapper, get, post } = await panel('?category=3')

    expect(get).toHaveBeenCalledWith('/api/cms/services?category=3')

    await moveFirstDown(wrapper)

    expect(post).toHaveBeenCalledWith('/api/cms/services/reorder', { ids: [2, 1, 3], category: 3 })
    expect(wrapper.text()).toContain('inside this category')
  })

  it('offers no grips while a search or a status makes the list a selection', async () => {
    for (const query of ['?q=imp', '?view=draft']) {
      const { wrapper } = await panel(query)

      expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
      expect(wrapper.text()).toContain('Clear the search and the filters')
    }
  })

  it('offers no grips to somebody who may only look', async () => {
    const { wrapper } = await panel('', false)

    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
    expect(wrapper.find('.wx-services__note').exists()).toBe(false)
  })

  it('asks for the bin, and a row there leads nowhere', async () => {
    const { wrapper, get } = await panel('?view=trashed')

    expect(get).toHaveBeenCalledWith('/api/cms/services?trashed=1')
    expect(wrapper.find('a.wx-service-row').exists()).toBe(false)
  })

  it('narrows by status through the view tabs', async () => {
    const { get } = await panel('?view=unpublished')

    expect(get).toHaveBeenCalledWith('/api/cms/services?status=unpublished')
  })
})
