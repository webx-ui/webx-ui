import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ref, type App, type Component } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { connectModals, localesKey } from '@webx-ui/core'
import type { ScreenNode } from '@webx-ui/schema'
import { adminKey, type AdminContext } from '../admin'
import { createI18n, i18nKey } from '../i18n'
import { adminMessages } from '../messages'
import { adminTypes } from '../screenTypes'
import CategoriesField from './CategoriesField.vue'
import CategoriesPage from './CategoriesPage.vue'
import CategoryEditorPage from './CategoryEditorPage.vue'
import { itemOrderMode } from './ordering'
import type { CategoriesOptions, CategoryRow } from './types'

const OPTIONS: CategoriesOptions = {
  api: 'blog/rubrics',
  path: '/blog/rubrics',
  name: 'webx.blog.rubrics',
  module: 'rubrics',
  screen: 'blog.category-form',
  manage: 'blog.taxonomy.manage',
  count: 'articles_count',
  items: (id) => ({ path: '/blog/articles', query: { rubric: String(id) } }),
  words: { new: 'webx-blog::rubric.new' },
}

function row(fields: Partial<CategoryRow> & { id: number; articles_count?: number }): CategoryRow {
  return {
    name: 'Repairs',
    title: { en: 'Repairs' },
    slug: { en: 'repairs' },
    path: 'blog/repairs',
    url: 'https://example.test/blog/repairs',
    is_visible: true,
    position: 0,
    deleted_at: null,
    articles_count: 0,
    ...fields,
  } as CategoryRow
}

/** Two tabs: the words, and a field that lives on the second one — where a 422 has to lead. */
const TREE: ScreenNode[] = [
  {
    id: 'tabs',
    type: 'wx-tabs',
    children: [
      {
        id: 'content',
        type: 'wx-tab',
        label: 'Content',
        children: [
          { id: 'title', type: 'wx-input', name: 'title', label: 'Name', localized: true },
          { id: 'slug', type: 'wx-category-slug', name: 'slug', label: 'Address', localized: true },
          { id: 'project-fields', type: 'wx-card', label: 'More', children: [] },
        ],
      },
      {
        id: 'image',
        type: 'wx-tab',
        label: 'Image',
        children: [{ id: 'cover', type: 'wx-input', name: 'cover', label: 'Cover' }],
      },
    ],
  },
]

function panel(
  component: Component,
  http: Partial<Record<'get' | 'post' | 'put' | 'delete', unknown>>,
  path = '/',
) {
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: {
      get: vi.fn(),
      post: vi.fn().mockResolvedValue(undefined),
      put: vi.fn(),
      delete: vi.fn().mockResolvedValue(undefined),
      ...http,
    },
    i18n,
    types: adminTypes,
    state: {
      manifest: { modules: [{ id: 'rubrics', title: 'Rubrics' }] },
      user: null,
      status: 'ready',
      error: null,
    },
    can: () => true,
    loadScreen: vi.fn().mockResolvedValue(TREE),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/blog/rubrics', component: { template: '<div />' } },
      { path: '/blog/rubrics/:id(\\d+)', component: { template: '<div />' } },
      { path: '/:all(.*)', component: { template: '<div />' } },
    ],
  })

  return {
    admin,
    router,
    mount: async () => {
      await router.push(path)

      const wrapper = mount(component, {
        props: { options: OPTIONS },
        global: {
          plugins: [router, { install: (app: App) => connectModals(app) }],
          provide: {
            [adminKey as symbol]: admin,
            [i18nKey as symbol]: i18n,
            [localesKey as symbol]: { list: ref([{ code: 'en' }]), active: ref('en') },
          },
        },
      })

      await flushPromises()

      return wrapper
    },
  }
}

afterEach(() => {
  for (const node of document.querySelectorAll('.wx-modal-host, .wx-dialog, .wx-dropdown')) {
    node.remove()
  }
})

describe('the list of categories', () => {
  it('asks the module’s path and links every row to its page', async () => {
    const get = vi.fn().mockResolvedValue({
      data: [row({ id: 1 }), row({ id: 2, name: 'News', path: null, is_visible: false })],
      prefix: 'blog',
    })
    const { mount: open } = panel(CategoriesPage, { get })
    const wrapper = await open()

    expect(get).toHaveBeenCalledWith('/api/cms/blog/rubrics')

    const links = wrapper.findAll('a.wx-category-row')
    expect(links.map((link) => link.attributes('href'))).toEqual([
      '/blog/rubrics/1',
      '/blog/rubrics/2',
    ])
    expect(links[1]!.classes()).toContain('is-hidden')
    expect(wrapper.text()).toContain('/blog/repairs')
    expect(wrapper.text()).toContain('No address in this language')
  })

  it('says the number under the module’s own key', async () => {
    const get = vi.fn().mockResolvedValue({ data: [row({ id: 1, articles_count: 4 })], prefix: '' })
    const { mount: open } = panel(CategoriesPage, { get })
    const wrapper = await open()

    expect(wrapper.find('.wx-category-row__count').text()).toBe('Entries: 4')
  })

  it('takes the module’s word over the panel’s, and the panel’s where the module has none', async () => {
    const get = vi.fn().mockResolvedValue({ data: [], prefix: '' })
    const { admin, mount: open } = panel(CategoriesPage, { get })
    admin.i18n.defaults('webx-blog', { rubric: { new: 'New rubric' } })
    const wrapper = await open()

    expect(wrapper.text()).toContain('New rubric')
    expect(wrapper.text()).toContain('No categories yet.')
  })
})

describe('the page of one category', () => {
  const detail = {
    category: row({ id: 7 }),
    values: { title: { en: 'Repairs' }, slug: { en: 'repairs' }, cover: null },
    prefix: 'blog',
  }

  it('draws the module’s screen with the values and the prefix in front of the slug', async () => {
    const get = vi.fn().mockResolvedValue({ data: detail })
    const { admin, mount: open } = panel(CategoryEditorPage, { get }, '/blog/rubrics/7')
    const wrapper = await open()

    expect(get).toHaveBeenCalledWith('/api/cms/blog/rubrics/7')
    expect(admin.loadScreen).toHaveBeenCalledWith('blog.category-form')
    expect(wrapper.find('.wx-slug__prefix').text()).toBe('/blog/')
    expect(wrapper.find('.wx-screen-head').text()).toContain('Repairs')
  })

  it('does not draw a card that has nothing in it', async () => {
    const get = vi.fn().mockResolvedValue({ data: detail })
    const { mount: open } = panel(CategoryEditorPage, { get }, '/blog/rubrics/7')
    const wrapper = await open()

    expect(wrapper.text()).not.toContain('More')
  })

  it('sends the values and nothing else', async () => {
    const get = vi.fn().mockResolvedValue({ data: detail })
    const put = vi.fn().mockResolvedValue({ data: detail })
    const { mount: open } = panel(CategoryEditorPage, { get, put }, '/blog/rubrics/7')
    const wrapper = await open()

    await wrapper.get('.wx-slug input').setValue('fixes')
    await wrapper.get('.wx-action-bar button').trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/blog/rubrics/7', {
      values: { title: { en: 'Repairs' }, slug: { en: 'fixes' }, cover: null },
    })
  })

  it('opens the tab a refused field is on', async () => {
    const get = vi.fn().mockResolvedValue({ data: detail })
    const put = vi.fn().mockRejectedValue({
      status: 422,
      body: { message: 'Invalid', errors: { cover: ['Not a picture.'] } },
    })
    const { mount: open } = panel(CategoryEditorPage, { get, put }, '/blog/rubrics/7')
    const wrapper = await open()

    await wrapper.get('.wx-slug input').setValue('fixes')
    await wrapper.get('.wx-action-bar button').trigger('click')
    await flushPromises()

    const selected = wrapper
      .findAll('.wx-tabs__tab')
      .find((tab) => tab.attributes('aria-selected') === 'true')
    expect(selected?.text()).toBe('Image')
    expect(wrapper.text()).toContain('Not a picture.')
  })
})

describe('wx-categories', () => {
  function field(value: number[], props: Record<string, unknown> = {}) {
    const get = vi.fn().mockResolvedValue({
      data: [
        row({ id: 1, name: 'Repairs' }),
        row({ id: 2, name: 'News' }),
        row({ id: 3, name: 'Cases' }),
      ],
      prefix: 'blog',
    })
    const i18n = createI18n()
    i18n.defaults('webx-admin', adminMessages)
    const admin = { apiPath: '/api/cms', http: { get }, i18n } as unknown as AdminContext

    return {
      get,
      wrapper: mount(CategoriesField, {
        props: { source: 'blog/rubrics', modelValue: value, ...props },
        global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
      }),
    }
  }

  it('asks the source for what can be chosen and names the chosen ones', async () => {
    const { get, wrapper } = field([2, 1])
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/blog/rubrics')
    expect(wrapper.findAll('.wx-categories-field__name').map((one) => one.text())).toEqual([
      'News',
      'Repairs',
    ])
  })

  it('calls the first one the main one, unless the module has no main one', async () => {
    const { wrapper } = field([2, 1], { mainText: 'Main rubric' })
    await flushPromises()

    const rows = wrapper.findAll('.wx-sortable-list__row')
    expect(rows[0]!.text()).toContain('Main rubric')
    expect(rows[1]!.text()).not.toContain('Main rubric')

    await wrapper.setProps({ main: false })
    expect(wrapper.text()).not.toContain('Main rubric')
  })

  it('takes one out and keeps the order of the rest', async () => {
    const { wrapper } = field([3, 2, 1])
    await flushPromises()

    await wrapper.findAll('.wx-sortable-list__row')[1]!.get('button').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[3, 1]])
  })
})

describe('which order a drag writes', () => {
  it('the whole list with nothing on, one category with only it, nothing with anything else', () => {
    expect(itemOrderMode({})).toBe('all')
    expect(itemOrderMode({ category: 4 })).toBe('category')
    expect(itemOrderMode({ category: 4, q: 'boiler' })).toBe('locked')
    expect(itemOrderMode({ filtered: true })).toBe('locked')
    expect(itemOrderMode({ q: '   ' })).toBe('all')
  })
})
