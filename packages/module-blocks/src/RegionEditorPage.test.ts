import { disableAutoUnmount, enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterAll, afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, inject, type PropType } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import type * as core from '@webx-ui/core'
import RegionEditorPage from './RegionEditorPage.vue'
import { blocksPreviewKey, blocksTopKey } from './preview'
import type { BlockNode, RegionDetail } from './types'

// The questions before publishing and taking off are the core's dialogs, mounted outside the
// tree; answered "yes" here so the test is about what the editor sends, not about the dialog.
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof core>()),
  confirm: vi.fn().mockResolvedValue(true),
}))

// The autosave pause outlives a test that never unmounts the editor (§4 of the brief).
enableAutoUnmount(afterEach)
afterAll(disableAutoUnmount)

beforeEach(() => {
  localStorage.clear()
})

afterEach(() => {
  vi.useRealTimers()
})

const block: BlockNode = { key: 'a1', type: 'text', values: {} }

function detail(extra: Partial<RegionDetail> = {}): RegionDetail {
  return {
    name: 'header',
    id: 1,
    title: 'Header',
    description: null,
    allow: ['text'],
    max: 3,
    published: true,
    published_at: '2026-09-28T00:00:00+00:00',
    has_draft: false,
    count: 1,
    fallback: 'components.header',
    updated_at: '2026-09-28T00:00:00+00:00',
    blocks: [block],
    revision: 'r1',
    preview_url: 'https://example.test/_preview/region/header?token=x',
    can_adopt: false,
    ...extra,
  }
}

/**
 * Stands in for the constructor: a button that adds a block, one that writes into the first,
 * and what the editor provides to it written out — the preview address and the top level — so the test can read both.
 */
const FakeBlocks = defineComponent({
  props: { modelValue: { type: Array as PropType<BlockNode[]>, default: () => [] } },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const preview = inject(blocksPreviewKey, null)
    const top = inject(blocksTopKey, null)

    return () =>
      h('div', { class: 'fake-blocks' }, [
        h('button', {
          class: 'fake-add',
          onClick: () =>
            emit('update:modelValue', [
              ...props.modelValue,
              { key: `k${props.modelValue.length}`, type: 'text', values: {} },
            ]),
        }),
        h('button', {
          class: 'fake-edit',
          onClick: () =>
            emit('update:modelValue', [
              { ...props.modelValue[0]!, values: { text: 'Mine' } },
              ...props.modelValue.slice(1),
            ]),
        }),
        h('span', { class: 'fake-url' }, preview?.url.value ?? ''),
        h('span', { class: 'fake-top' }, JSON.stringify(top?.value ?? null)),
      ])
  },
})

const screen: ScreenNode[] = [{ id: 'blocks', type: 'wx-blocks', name: 'blocks' }]

async function panel(first = detail()) {
  const get = vi.fn().mockResolvedValue({ data: first })
  const put = vi.fn().mockResolvedValue({ data: detail({ has_draft: true, revision: 'r2' }) })
  const post = vi.fn().mockResolvedValue({ data: detail() })
  const del = vi.fn().mockResolvedValue({ data: detail() })

  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, put, post, delete: del },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: { ...coreTypes, 'wx-blocks': { component: FakeBlocks, kind: 'field' } },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/regions', component: { template: '<div />' } },
      { path: '/regions/:name', component: RegionEditorPage, props: { base: '/regions' } },
    ],
  })

  await router.push('/regions/header')
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

  return { wrapper, get, put, post, del, router }
}

function button(wrapper: Awaited<ReturnType<typeof panel>>['wrapper'], text: string) {
  const found = wrapper.findAll('button').find((one) => one.text().trim() === text)

  if (!found) throw new Error(`No button "${text}"`)

  return found
}

describe('WxRegionEditorPage', () => {
  it('reads the region by name and hands the constructor its top level', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/regions/header')
    expect(JSON.parse(wrapper.get('.fake-top').text())).toEqual({
      root: 'region:header',
      label: 'Header',
      allow: ['text'],
      max: 3,
    })
  })

  it('previews the home page until another page is chosen, and remembers the choice', async () => {
    const { wrapper } = await panel()

    expect(wrapper.get('.fake-url').text()).toBe(
      'https://example.test/_preview/region/header?token=x&at=%2F',
    )

    wrapper.unmount()
    localStorage.setItem('webx.regions.at.header', JSON.stringify({ path: '/about', link: null }))

    const again = await panel()

    expect(again.wrapper.get('.fake-url').text()).toContain('&at=%2Fabout')
  })

  it('writes the draft after a pause, carrying the revision it read', async () => {
    vi.useFakeTimers()
    const { wrapper, put } = await panel()

    await wrapper.get('.fake-add').trigger('click')
    expect(put).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1500)
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/regions/header', {
      blocks: [block, { key: 'k1', type: 'text', values: {} }],
      revision: 'r1',
    })
  })

  it('merges a save refused over another block, and saves both trees as one', async () => {
    const { wrapper, put } = await panel()
    const theirs = { key: 'z9', type: 'text', values: {} }

    put.mockRejectedValueOnce({
      status: 409,
      body: {
        message: 'Somebody changed this region.',
        revision: 'r9',
        data: detail({ revision: 'r9', blocks: [block, theirs] }),
        changed: { author: 'Anna', author_id: 2, source: 'mcp', at: null },
      },
    })

    await wrapper.get('.fake-add').trigger('click')
    await wrapper.get('.wx-region-editor').trigger('focusout')
    await flushPromises()

    expect(put).toHaveBeenCalledTimes(2)

    const sent = put.mock.calls[1]?.[1] as { blocks: BlockNode[]; revision: string }

    expect(sent.revision).toBe('r9')
    expect(sent.blocks.map((one) => one.key).sort()).toEqual(['a1', 'k1', 'z9'])
    expect(wrapper.find('.wx-editing-alerts').exists()).toBe(false)
  })

  it('asks about a block both sides changed, and writes the answer with their revision', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 409,
      body: {
        message: 'Somebody changed this region.',
        revision: 'r9',
        data: detail({ revision: 'r9', blocks: [{ ...block, values: { text: 'Theirs' } }] }),
      },
    })

    await wrapper.get('.fake-edit').trigger('click')
    await wrapper.get('.wx-region-editor').trigger('focusout')
    await flushPromises()

    expect(put).toHaveBeenCalledTimes(1)

    const alert = wrapper.find('.wx-editing-alerts')

    expect(alert.findAll('.wx-editing-alerts__item')).toHaveLength(1)
    expect(alert.text()).toContain('Theirs')

    // This editor's side is the one chosen until somebody picks; the last button applies it.
    await alert.findAll('button').at(-1)?.trigger('click')
    await flushPromises()

    expect(put.mock.calls[1]?.[1]).toMatchObject({
      revision: 'r9',
      blocks: [{ key: 'a1', values: { text: 'Mine' } }],
    })
    expect(wrapper.find('.wx-editing-alerts').exists()).toBe(false)
  })

  it('publishes, and takes off the site, after asking', async () => {
    const { wrapper, post } = await panel(detail({ has_draft: true }))

    await button(wrapper, 'Publish').trigger('click')
    await flushPromises()
    expect(post).toHaveBeenCalledWith('/api/cms/regions/header/publish', { revision: 'r1' })

    await button(wrapper, 'Take off the site').trigger('click')
    await flushPromises()
    expect(post).toHaveBeenCalledWith('/api/cms/regions/header/unpublish', {})
  })

  it('says what the site prints while the region is empty, and offers to adopt it', async () => {
    const { wrapper, post } = await panel(
      detail({ blocks: [], count: 0, published: false, published_at: null, can_adopt: true }),
    )

    expect(wrapper.text()).toContain('the view components.header')

    post.mockResolvedValueOnce({ data: detail(), block: { id: 9, slug: 'site-header' } })

    await button(wrapper, 'Move the markup into a block').trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/regions/header/adopt', {})
    expect(wrapper.find('.wx-region-editor__empty').exists()).toBe(false)
  })
})
