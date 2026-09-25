import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { VueDraggable } from 'vue-draggable-plus'
import BlocksPage from './BlocksPage.vue'
import type { BlockType, DeclaredComponent } from './types'

function type(slug: string, extra: Partial<BlockType> = {}): BlockType {
  return {
    id: slug.length,
    slug,
    title: slug.charAt(0).toUpperCase() + slug.slice(1),
    description: null,
    icon: null,
    group: 'content',
    sort: 0,
    allow: null,
    allowed_in: null,
    max_per_entity: null,
    is_enabled: true,
    draft: null,
    published: null,
    usage_count: 0,
    thumbnail: null,
    created_at: null,
    updated_at: null,
    ...extra,
  }
}

const recipeCard: DeclaredComponent = {
  slug: 'recipe-card',
  module: 'recipes',
  title: 'Recipe card',
  description: 'One recipe in a list.',
  fallback: 'webx-recipes::partials.card',
  customised: false,
}

function panel(types: BlockType[], declared: DeclaredComponent[], post = vi.fn()) {
  const get = vi.fn().mockResolvedValue({ data: types, declared })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post },
    i18n,
    state: {
      manifest: {
        modules: [
          { id: 'blocks', title: 'Blocks', meta: { groups: ['layout', 'content'] } },
          { id: 'recipes', title: 'Recipes' },
        ],
      },
      user: null,
      status: 'ready',
      error: null,
    },
    can: () => true,
    types: {},
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/:all(.*)', component: { template: '<div />' } }],
  })

  const wrapper = mount(BlocksPage, {
    global: {
      plugins: [router],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      stubs: { BlockThumb: true },
    },
  })

  return { wrapper, router, get, post }
}

afterEach(() => {
  document.body.innerHTML = ''
})

/**
 * The section's two kinds. Only what it draws and asks — the grid, the widths and a card at
 * 375 px are checked in a browser, since jsdom lays nothing out (CLAUDE.md §4).
 */
describe('WxBlocksPage with components', () => {
  it('draws the blocks and the components as two groups, each named', async () => {
    const { wrapper } = panel(
      [
        type('hero'),
        type('badge', {
          kind: 'component',
          used_by: [{ id: 14, slug: 'teaser', title: 'Teaser' }],
        }),
      ],
      [],
    )

    await flushPromises()

    const titles = wrapper.findAll('.wx-blocks-page__group-title').map((title) => title.text())

    expect(titles).toEqual(['Blocks', 'Components'])
    // A component's line counts the blocks calling it, not pages.
    expect(wrapper.text()).toContain('badge · in one block')
  })

  it('shows a declared place the site has not customised, with the module that draws it', async () => {
    const { wrapper } = panel([type('hero')], [recipeCard])

    await flushPromises()

    const card = wrapper.get('.wx-block-declared')

    expect(card.text()).toContain('Recipe card')
    expect(card.text()).toContain('Standard view · module Recipes')
    expect(card.find('button').text()).toBe('Customise')
  })

  it('does not show the place again once it is a type; the type says whose it was', async () => {
    const { wrapper } = panel(
      [type('recipe-card', { kind: 'component' })],
      [{ ...recipeCard, customised: true }],
    )

    await flushPromises()

    expect(wrapper.find('.wx-block-declared').exists()).toBe(false)
    expect(wrapper.text()).toContain('recipe-card · not called yet · module Recipes')
  })

  it('customises the place and opens the draft it made', async () => {
    const post = vi
      .fn()
      .mockResolvedValue({ data: type('recipe-card', { id: 15, kind: 'component' }) })
    const { wrapper, router } = panel([type('hero')], [recipeCard], post)

    await flushPromises()
    await wrapper.get('.wx-block-declared button').trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/blocks/components/recipe-card/customise', {})
    expect(router.currentRoute.value.path).toBe('/blocks/15')
  })

  it('opens the type that is already there when the place was customised elsewhere', async () => {
    const post = vi.fn().mockRejectedValue({ status: 409, body: { message: 'Taken.', id: 21 } })
    const { wrapper, router } = panel([type('hero')], [recipeCard], post)

    await flushPromises()
    await wrapper.get('.wx-block-declared button').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/blocks/21')
  })

  it('offers the components as a view of their own', async () => {
    const { wrapper } = panel([type('hero'), type('badge', { kind: 'component' })], [])

    await flushPromises()

    const labels = wrapper.findAll('.wx-tabs__tab').map((tab) => tab.text())

    expect(labels).toContain('Components')
  })

  /*
   * A group dragged into a new order sends only that group: the server puts it in the places
   * it held, and the other groups stay where they were. jsdom has no pointer to drag with, so
   * the drop is the list the sortable hands back.
   */
  it('sends a dragged group in its new order and shows it at once', async () => {
    const post = vi.fn().mockResolvedValue({ data: {} })
    const hero = type('hero', { id: 1, group: 'layout' })
    const text = type('text', { id: 2 })
    const quote = type('quote', { id: 3 })
    const cta = type('cta', { id: 4 })
    const extra = [5, 6].map((id) => type(`more-${id}`, { id }))
    const { wrapper } = panel([hero, text, quote, cta, ...extra], [], post)

    await flushPromises()

    const content = wrapper.findAllComponents(VueDraggable)[1]!
    content.vm.$emit('update:modelValue', [quote, text, cta, ...extra])
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/blocks/reorder', { ids: [3, 2, 4, 5, 6] })
    expect(wrapper.findAll('.wx-block-card__title').map((title) => title.text())).toEqual([
      'Hero',
      'Quote',
      'Text',
      'Cta',
      'More-5',
      'More-6',
    ])
  })

  it('does not drag while searching', async () => {
    const { wrapper } = panel([type('hero', { id: 1 }), type('text', { id: 2 })], [])

    await flushPromises()
    await wrapper.get('.wx-blocks-page__search input').setValue('he')

    expect(wrapper.findComponent(VueDraggable).props('disabled')).toBe(true)
  })
})
