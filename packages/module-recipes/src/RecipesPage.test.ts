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
import RecipesPage from './RecipesPage.vue'
import type { RecipeRow, RecipesList } from './types'

function row(id: number, title: string, over: Partial<RecipeRow> = {}): RecipeRow {
  return {
    id,
    title,
    slug: title.toLowerCase(),
    path: `recipes/${title.toLowerCase()}`,
    url: `https://example.test/recipes/${title.toLowerCase()}`,
    cover: null,
    minutes: null,
    status: 'published',
    position: id,
    categories: [],
    published_at: '2026-09-12T08:00:00+00:00',
    updated_at: '2026-09-12T08:00:00+00:00',
    deleted_at: null,
    ...over,
  }
}

function list(services: RecipesList['filters']['services'] = [{ id: 9, title: 'Nutrition' }]) {
  return {
    data: [
      row(1, 'Porridge', {
        minutes: 75,
        categories: [
          { id: 3, title: 'Breakfasts' },
          { id: 4, title: 'Quick' },
        ],
      }),
      row(2, 'Soup', { status: 'draft', minutes: 45 }),
      row(3, 'Salad', { status: 'modified', cover: { thumb: '/salad.jpg' } }),
    ],
    filters: {
      categories: [
        { id: 3, title: 'Breakfasts' },
        { id: 4, title: 'Quick' },
      ],
      nutrients: [{ id: 5, title: 'Iron' }],
      services,
    },
  } satisfies RecipesList
}

async function panel(query = '', can = true, answer: RecipesList = list()) {
  const get = vi.fn().mockResolvedValue(answer)
  const post = vi.fn().mockResolvedValue(undefined)
  const i18n = createI18n()
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
      { path: '/recipes', component: RecipesPage, props: { base: '/recipes' } },
      { path: '/recipes/:id(\\d+)', component: { template: '<div />' } },
    ],
  })

  await router.push(`/recipes${query}`)
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
 * The list and its one order. What a row looks like at 375px is a container query, and jsdom
 * computes no layout: that is checked in a browser.
 */
describe('WxRecipesPage', () => {
  it('asks for every recipe, with no page in the request', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/recipes')
    expect(wrapper.findAll('.wx-recipe-row')).toHaveLength(3)
    expect(wrapper.text()).toContain('/recipes/porridge')
  })

  it('says how long a recipe takes, in hours past the hour', async () => {
    const { wrapper } = await panel()
    const times = wrapper.findAll('.wx-recipe-row__time').map((one) => one.text())

    expect(times).toEqual(['1 h 15 min', '45 min'])
  })

  it('marks the first category as the main one', async () => {
    const { wrapper } = await panel()

    const chips = wrapper.findAll('.wx-recipe-row')[0]!.findAll('.wx-recipe-row__chips .wx-badge')

    expect(chips.map((chip) => chip.text())).toEqual(['Breakfasts', 'Quick'])
    expect(chips[0]!.classes().join(' ')).toContain('primary')
  })

  it('writes the one order when nothing narrows the list', async () => {
    const { wrapper, post } = await panel()

    await moveFirstDown(wrapper)

    // No `category`, ever: a recipe has no order inside one (decision 4).
    expect(post).toHaveBeenCalledWith('/api/cms/recipes/reorder', { ids: [2, 1, 3] })
    expect(wrapper.text()).toContain('It is the one order recipes have.')
  })

  it('offers no grips inside a category either, and says why', async () => {
    for (const query of ['?category=3', '?nutrient=5', '?service=9', '?q=por', '?view=draft']) {
      const { wrapper } = await panel(query)

      expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
      expect(wrapper.text()).toContain('the same in every category')
    }
  })

  it('narrows by each filter through the address', async () => {
    const { get } = await panel('?category=3&nutrient=5&service=9&view=unpublished')

    expect(get).toHaveBeenCalledWith(
      '/api/cms/recipes?category=3&nutrient=5&service=9&status=unpublished',
    )
  })

  it('has no service filter on a site without services', async () => {
    const { wrapper } = await panel('', true, list(null))

    expect(wrapper.findAll('.wx-recipes__bar .wx-select')).toHaveLength(2)
  })

  it('offers no grips to somebody who may only look', async () => {
    const { wrapper } = await panel('', false)

    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
    expect(wrapper.find('.wx-recipes__note').exists()).toBe(false)
  })

  it('asks for the bin, and a row there leads nowhere', async () => {
    const { wrapper, get } = await panel('?view=trashed')

    expect(get).toHaveBeenCalledWith('/api/cms/recipes?trashed=1')
    expect(wrapper.find('a.wx-recipe-row').exists()).toBe(false)
  })
})
