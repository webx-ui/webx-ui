import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import BlockEditorPage from './BlockEditorPage.vue'
import type { BlockType } from './types'

const card: BlockType = {
  id: 15,
  slug: 'recipe-card',
  kind: 'component',
  title: 'Recipe card',
  description: null,
  icon: null,
  group: 'content',
  sort: 0,
  allow: null,
  allowed_in: null,
  max_per_entity: null,
  is_enabled: true,
  draft: {
    number: 1,
    source: 'panel',
    comment: null,
    author_id: null,
    author: null,
    created_at: null,
  },
  published: null,
  usage_count: 0,
  uses: [],
  used_by: [{ id: 14, slug: 'recipe-teaser', title: 'Teaser' }],
  declared: {
    slug: 'recipe-card',
    module: 'recipes',
    title: 'Recipe card',
    description: null,
    fallback: 'webx-recipes::partials.card',
    customised: true,
  },
  shape: {
    'recipes.card': {
      fields: [{ name: 'title', type: 'string', description: 'The name.' }],
    },
  },
  thumbnail: null,
  created_at: null,
  updated_at: null,
  content: {
    schema: [
      { id: 'card', type: 'wx-data', label: 'Recipe', props: { shape: 'recipes.card' } },
      { id: 'aside', type: 'wx-slot', label: 'Aside' },
    ],
    template: '<li>{{ $card["title"] }}</li>',
    styles: '',
    script: null,
    sample: { card: { title: 'Porridge' } },
  },
}

/** The editor's heavy parts stood in: CodeMirror and the frame are not what is tested here. */
const stubs = { WxCodeEditor: true, BlockStage: true, WxScreenRenderer: true }

async function editor(type: BlockType = card) {
  const get = vi.fn((url: string) => {
    if (url === '/api/cms/blocks') return Promise.resolve({ data: [type], declared: [] })
    if (url.endsWith('/usage') || url.endsWith('/versions')) return Promise.resolve({ data: [] })

    return Promise.resolve({ data: type })
  })
  const post = vi.fn().mockResolvedValue({
    data: { html: '', styles: '', script: null, runtime: '', version: 1 },
  })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    http: { get, post, put: vi.fn(), delete: vi.fn() },
    i18n,
    state: {
      manifest: {
        modules: [
          { id: 'blocks', meta: { groups: ['content'] } },
          { id: 'recipes', title: 'Recipes' },
        ],
      },
    },
    can: () => true,
    types: {},
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/blocks/:id', component: BlockEditorPage }],
  })

  await router.push(`/blocks/${type.id}`)

  const wrapper = mount(BlockEditorPage, {
    attachTo: document.body,
    global: {
      plugins: [router],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      stubs,
    },
  })

  await flushPromises()

  return { wrapper, get }
}

async function openTab(wrapper: Awaited<ReturnType<typeof editor>>['wrapper'], label: string) {
  const tab = wrapper.findAll('.wx-tabs__tab').find((one) => one.text() === label)!

  // Reka switches on mousedown, not on click (CLAUDE.md §4).
  await tab.trigger('mousedown')
  await flushPromises()
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('WxBlockEditorPage for a component', () => {
  it('names the fields "Input data" and says where it is called from', async () => {
    const { wrapper } = await editor()

    const tabs = wrapper.findAll('.wx-tabs__tab').map((tab) => tab.text())

    expect(tabs).toContain('Input data')
    expect(tabs).not.toContain('Fields')
    expect(wrapper.text()).toContain('recipe-card · Component · in one block')
  })

  it('writes out the tag that calls it, and what its structure holds', async () => {
    const { wrapper } = await editor()

    const call = wrapper.get('.wx-block-editor__call')

    expect(call.get('pre').text()).toBe(
      [
        '<x-webx-block type="recipe-card" :card="$card" fallback="webx-recipes::partials.card">',
        '    <x-slot:aside>…</x-slot:aside>',
        '</x-webx-block>',
      ].join('\n'),
    )
    expect(call.text()).toContain('What $card holds')
    expect(call.text()).toContain('The name.')
    expect(call.text()).toContain('Calls from the views of the site are not seen here')
  })

  it('reads the list of types once, for the tag suggestions', async () => {
    const { get } = await editor()

    expect(get).toHaveBeenCalledWith('/api/cms/blocks')
  })

  it('keeps to the settings a component has, and will not delete one that blocks call', async () => {
    const { wrapper } = await editor()

    await openTab(wrapper, 'Settings')

    const labels = wrapper.findAll('.wx-form-item__label').map((label) => label.text())

    expect(labels).toContain('Kind')
    expect(labels).not.toContain('Group')
    expect(labels).not.toContain('May hold')
    expect(labels).not.toContain('Per page')

    const danger = wrapper.get('.wx-block-editor__danger')

    // Deleting a customised place is resetting it, and says so.
    expect(danger.get('button').text()).toBe('Reset to standard')
    expect(danger.get('button').attributes('disabled')).toBeDefined()
    expect(danger.text()).toContain('Blocks call it, so it cannot be deleted: "Teaser".')
  })

  it('shows none of it for a block', async () => {
    const { wrapper } = await editor({
      ...card,
      kind: 'block',
      used_by: [],
      declared: null,
    })

    expect(wrapper.find('.wx-block-editor__call').exists()).toBe(false)
    expect(wrapper.findAll('.wx-tabs__tab').map((tab) => tab.text())).toContain('Fields')
  })
})
