import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, adminTypes, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import RecipeEditorPage from './RecipeEditorPage.vue'
import RecipeHistory from './RecipeHistory.vue'
import type { RecipeDetail, RecipeRow } from './types'

const porridge: RecipeRow = {
  id: 7,
  title: 'Porridge',
  slug: 'porridge',
  path: 'recipes/porridge',
  url: 'https://example.test/recipes/porridge',
  cover: null,
  minutes: 15,
  status: 'published',
  position: 1,
  categories: [],
  published_at: '2026-09-12T08:00:00+00:00',
  updated_at: '2026-09-12T08:00:00+00:00',
  deleted_at: null,
  revision: 'r1',
}

function detail(revision: string, over: Partial<RecipeRow> = {}): RecipeDetail {
  return {
    recipe: { ...porridge, ...over, revision },
    values: {
      title: { en: over.title ?? 'Porridge' },
      slug: { en: 'porridge' },
      'nutrition.calories': { en: '320 kcal' },
      related: [],
    },
    revision,
    prefix: 'recipes',
    preview_url: 'https://example.test/_preview/recipe/7?token=x',
  }
}

/*
 * The settings tab with what is worth checking here — the name, the address, one nutrition field
 * with its literal dotted name and the similar recipes — and the history behind a tab.
 */
const screen: ScreenNode[] = [
  {
    id: 'tabs',
    type: 'wx-tabs',
    children: [
      {
        id: 'settings',
        type: 'wx-tab',
        label: 'Settings',
        children: [
          { id: 'title', type: 'wx-input', name: 'title', localized: true },
          { id: 'slug', type: 'wx-slug', name: 'slug', localized: true },
          { id: 'calories', type: 'wx-input', name: 'nutrition.calories', localized: true },
          { id: 'related', type: 'wx-relations', name: 'related', props: { target: 'recipe' } },
        ],
      },
      {
        id: 'history',
        type: 'wx-tab',
        label: 'History',
        children: [{ id: 'versions', type: 'wx-recipe-history' }],
      },
    ],
  },
]

const candidates = [
  { id: 7, title: 'Porridge', subtitle: null, thumb: null, visible: true },
  { id: 8, title: 'Pancakes', subtitle: null, thumb: null, visible: true },
]

async function panel(first = detail('r1')) {
  const get = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/versions')) {
      return Promise.resolve({
        data: [
          {
            number: 1,
            created_at: '2026-09-12T08:00:00+00:00',
            author: 'Anna',
            source: 'panel',
            comment: null,
            is_pinned: false,
          },
        ],
      })
    }

    if (url.includes('/relations/recipe')) return Promise.resolve({ data: candidates })

    return Promise.resolve({ data: first })
  })

  const put = vi.fn().mockResolvedValue({ data: detail('r2', { title: 'Oat porridge' }) })
  const post = vi.fn().mockResolvedValue({ data: porridge })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, put, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: {
      ...coreTypes,
      ...adminTypes,
      'wx-recipe-history': { component: RecipeHistory, kind: 'display' },
    },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/recipes', component: { template: '<div />' } },
      { path: '/recipes/:id(\\d+)', component: RecipeEditorPage, props: { base: '/recipes' } },
    ],
  })

  await router.push('/recipes/7')
  await router.isReady()

  const wrapper = mount(
    { template: '<router-view />' },
    {
      global: {
        plugins: [router],
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          // What a panel always provides; without it a localized field hands back a plain string.
          [localesKey as symbol]: {
            list: ref([{ code: 'en', name: 'English' }]),
            active: ref('en'),
          },
        },
      },
    },
  )

  await flushPromises()

  return { wrapper, get, put, post, router }
}

afterEach(() => {
  vi.useRealTimers()
})

/**
 * Saving, the revision, the history and the similar recipes — what this screen owns. The bar at
 * the bottom is layout, checked in a browser.
 */
describe('WxRecipeEditorPage', () => {
  it('writes the draft after a pause, carrying the revision it read', async () => {
    vi.useFakeTimers()
    const { wrapper, put } = await panel()

    await wrapper.find('input').setValue('Oat porridge')
    await nextTick()
    expect(put).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1500)
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/recipes/7', {
      values: expect.objectContaining({ title: { en: 'Oat porridge' } }),
      revision: 'r1',
    })
  })

  it('keeps a dotted name literal: the nutrition field is one key, not a path', async () => {
    const { wrapper, put } = await panel()
    const calories = wrapper.findAll('input')[2]!

    expect((calories.element as HTMLInputElement).value).toBe('320 kcal')

    await calories.setValue('350 kcal')
    await wrapper.find('.wx-recipe-editor').trigger('focusout')
    await flushPromises()

    const sent = put.mock.calls[0]?.[1] as { values: Record<string, unknown> }

    expect(sent.values['nutrition.calories']).toEqual({ en: '350 kcal' })
    expect(sent.values.nutrition).toBeUndefined()
  })

  it('never offers the recipe itself among the similar ones', async () => {
    const { wrapper, get } = await panel()

    const box = wrapper.findComponent({ name: 'WxAutocomplete' })
    box.vm.$emit('search', 'p')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/relations/recipe', { query: { q: 'p' } })
    expect(box.props('options').map((one: { value: string }) => one.value)).toEqual(['Pancakes'])
  })

  it('puts a conflict on the screen instead of one version over the other', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 409,
      body: { message: 'Somebody changed this recipe.', data: detail('r9') },
    })

    await wrapper.find('input').setValue('Mine')
    await wrapper.find('.wx-recipe-editor').trigger('focusout')
    await flushPromises()

    expect(wrapper.text()).toContain('Somebody changed this recipe.')

    // Keeping mine writes over theirs with their revision.
    await wrapper.findAll('.wx-alert button').at(-1)?.trigger('click')
    await flushPromises()

    expect(put.mock.calls[1]?.[1]).toMatchObject({ revision: 'r9' })
  })

  it('prints the prefix of the recipes in front of the slug', async () => {
    const { wrapper } = await panel()

    expect(wrapper.find('.wx-slug__prefix').text()).toBe('/recipes/')
  })

  it('shows a 422 under the address, where the registry refused it', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 422,
      body: { errors: { 'slug.en': ['This address is already taken by "Breakfasts".'] } },
    })

    await wrapper.find('input').setValue('Breakfasts')
    await wrapper.find('.wx-recipe-editor').trigger('focusout')
    await flushPromises()

    expect(wrapper.text()).toContain('already taken by "Breakfasts"')
  })

  it('opens the history on mousedown, which is the event the tabs listen for', async () => {
    const { wrapper, get } = await panel()
    const tabs = wrapper.findAll('.wx-tabs__tab')

    expect(get).not.toHaveBeenCalledWith('/api/cms/recipes/7/versions')

    await tabs[1]!.trigger('mousedown')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/recipes/7/versions')
    expect(wrapper.find('.wx-recipe-history').text()).toContain('Anna')
  })

  it('publishes after asking, and reads the recipe again', async () => {
    const { wrapper, post, get } = await panel(detail('r1', { status: 'draft' }))

    await wrapper.findAll('.wx-action-bar button').at(-1)?.trigger('click')
    await flushPromises()

    // The question is a dialog mounted outside the tree; its confirm button is in the document.
    const confirm = [...document.querySelectorAll('button')].find(
      (button) => button.textContent?.trim() === 'Publish' && !wrapper.element.contains(button),
    )
    confirm?.click()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/recipes/7/publish', {})
    expect(get.mock.calls.filter(([url]) => url === '/api/cms/recipes/7')).toHaveLength(2)
  })

  it('leaves without asking when the save is already on its way', async () => {
    const { wrapper, put, router } = await panel()

    let answer: (value: unknown) => void = () => {}
    put.mockImplementationOnce(() => new Promise((resolve) => (answer = resolve)))

    await wrapper.find('input').setValue('Oat porridge')
    await wrapper.find('.wx-recipe-editor').trigger('focusout')
    expect(put).toHaveBeenCalledTimes(1)

    // Leaving while that request is out used to skip the save, read `dirty` and ask.
    const leaving = router.push('/recipes')
    await flushPromises()

    answer({ data: detail('r2', { title: 'Oat porridge' }) })
    await leaving
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/recipes')
    expect(put).toHaveBeenCalledTimes(1)
  })
})
