import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { WxSelect } from '@webx-ui/core'
import type * as core from '@webx-ui/core'
import FragmentField from './FragmentField.vue'
import { blocksFragmentKey, type BlocksFragmentHost } from './preview'
import type { BlockNode, RegionRow } from './types'

// The question before «Make it this page's own» is the core's dialog, mounted outside the tree.
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof core>()),
  confirm: vi.fn().mockResolvedValue(true),
}))

function row(name: string, extra: Partial<RegionRow> = {}): RegionRow {
  return {
    name,
    id: 1,
    title: name.charAt(0).toUpperCase() + name.slice(1),
    description: null,
    allow: null,
    max: null,
    fragment: true,
    published: true,
    published_at: null,
    has_draft: false,
    count: 2,
    fallback: null,
    updated_at: null,
    ...extra,
  }
}

const copies: BlockNode[] = [
  { key: 'n1', type: 'text', values: { body: 'Ask us' } },
  { key: 'n2', type: 'text', values: { body: 'Today' } },
]

async function field(
  value: string | null,
  options: { rows?: RegionRow[]; own?: string | null; can?: boolean; host?: boolean } = {},
) {
  const get = vi.fn().mockResolvedValue({
    data: options.rows ?? [row('request'), row('promo', { published: false, count: 0 })],
  })
  const post = vi.fn().mockResolvedValue({ data: { blocks: copies } })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => options.can ?? true,
    types: {},
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory('/cms'),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/regions/:name', name: 'webx.regions.edit', component: { template: '<div />' } },
    ],
  })

  const detach = vi.fn()
  const host: BlocksFragmentHost = {
    selected: ref('f1'),
    own: ref(options.own ?? null),
    detach,
  }

  const wrapper = mount(FragmentField, {
    props: { modelValue: value },
    global: {
      plugins: [router],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: i18n,
        ...(options.host === false ? {} : { [blocksFragmentKey as symbol]: host }),
      },
    },
  })

  await flushPromises()

  return { wrapper, get, post, detach }
}

function button(wrapper: Awaited<ReturnType<typeof field>>['wrapper'], text: string) {
  return wrapper.findAll('a, button').find((one) => one.text().trim() === text) ?? null
}

describe('WxFragmentField', () => {
  it('offers the shared blocks of the site, never the one being edited', async () => {
    const { wrapper, get } = await field(null, { own: 'promo' })

    expect(get).toHaveBeenCalledWith('/api/cms/blocks/fragments')
    expect(wrapper.getComponent(WxSelect).props('options')).toEqual([
      { label: 'Request', value: 'request' },
    ])

    wrapper.getComponent(WxSelect).vm.$emit('update:modelValue', 'request')

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['request'])
  })

  it('says what the site shows, and where the shared block is edited', async () => {
    const { wrapper } = await field('request')

    expect(wrapper.text()).toContain('On the site')
    expect(wrapper.text()).toContain('Editing it changes it on every page where it stands.')

    const edit = button(wrapper, 'Edit for all pages')

    expect(edit?.attributes('href')).toBe('/cms/regions/request')
    expect(edit?.attributes('target')).toBe('_blank')
  })

  it('does not lead to the regions whoever may not edit them', async () => {
    const { wrapper } = await field('request', { can: false })

    expect(button(wrapper, 'Edit for all pages')).toBeNull()
  })

  it('puts the published copy in its place, after asking', async () => {
    const { wrapper, post, detach } = await field('request')

    await button(wrapper, 'Make it this page’s own')!.trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/regions/request/copy', {})
    expect(detach).toHaveBeenCalledWith('f1', copies)
  })

  it('has nothing to copy before the shared block is published, and says so', async () => {
    const { wrapper } = await field('promo')

    expect(wrapper.text()).toContain('Not published: the site shows its fallback')
    expect(button(wrapper, 'Make it this page’s own')!.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('there is nothing to copy')
  })

  it('names a shared block the site no longer declares', async () => {
    const { wrapper } = await field('gone')

    expect(wrapper.text()).toContain('“gone” is not a shared block of the site any more')
  })

  it('offers no way out outside a constructor', async () => {
    const { wrapper } = await field('request', { host: false })

    expect(button(wrapper, 'Make it this page’s own')).toBeNull()
  })
})
