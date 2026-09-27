import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory, type Router } from 'vue-router'
import {
  adminKey,
  adminMessages,
  adminTypes,
  createI18n,
  i18nKey,
  type AdminContext,
} from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import type * as Core from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import PressPage from './PressPage.vue'
import type { OutletDetail, OutletRow, OutletsList } from './types'

const { confirm } = vi.hoisted(() => ({ confirm: vi.fn() }))

/* The dialog itself is the core's; what is checked here is that the form asks at all. */
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof Core>()),
  confirm,
}))

function row(id: number, title: string, over: Partial<OutletRow> = {}): OutletRow {
  return {
    id,
    title,
    logo: null,
    published: true,
    featured: false,
    position: id,
    locales: ['en'],
    articles_count: 1,
    updated_at: '2026-09-27T09:00:00+00:00',
    deleted_at: null,
    ...over,
  }
}

const list: OutletsList = {
  data: [
    row(1, 'Health & Style', {
      logo: { thumb: '/thumbs/health.svg' },
      featured: true,
      articles_count: 3,
      locales: ['ru', 'en'],
    }),
    row(2, 'Kitchen Weekly', { published: false, articles_count: 0, locales: [] }),
    // Published, and yet in no language: its one article is hidden.
    row(3, 'Morning Air', { locales: [] }),
  ],
}

function detail(id: number, title: string): OutletDetail {
  return {
    outlet: { id, title, published: true, deleted_at: null, url: '/press/health-and-style' },
    values: {
      title: { en: title },
      published: true,
      articles: [
        { id: 11, title: { en: 'Interview: a week of plates' }, url: 'https://a.example/1' },
        { id: 12, title: { en: 'Column: breakfast' }, url: 'https://a.example/2' },
      ],
    },
    prefix: 'press',
  }
}

/*
 * A slice of `press.outlet-form`: a name on the first tab and the articles on the second — enough
 * to type, to switch tabs and to see a refusal of one article land under its field.
 */
const screen: ScreenNode[] = [
  {
    id: 'tabs',
    type: 'wx-tabs',
    children: [
      {
        id: 'general',
        type: 'wx-tab',
        label: 'General',
        children: [{ id: 'title', type: 'wx-input', name: 'title', localized: true }],
      },
      {
        id: 'articles-tab',
        type: 'wx-tab',
        label: 'Articles',
        children: [
          {
            id: 'articles',
            type: 'wx-repeater',
            name: 'articles',
            props: { itemLabel: 'title' },
            children: [
              { id: 'article-title', type: 'wx-input', name: 'title', localized: true },
              { id: 'article-url', type: 'wx-input', name: 'url' },
            ],
          },
        ],
      },
    ],
  },
]

let router: Router

async function panel(query = '', can = true) {
  const get = vi.fn().mockImplementation((url: string) => {
    const one = /\/press\/(\d+)$/.exec(url)

    return Promise.resolve(one ? { data: detail(Number(one[1]), 'Health & Style') } : list)
  })
  const post = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/reorder')) return Promise.resolve(undefined)
    if (url.endsWith('/restore')) return Promise.resolve({ data: row(2, 'Kitchen Weekly') })

    return Promise.resolve({ data: detail(9, 'Brand new') })
  })
  const put = vi.fn().mockResolvedValue({ data: detail(1, 'Health & Style') })
  const i18n = createI18n()
  // What the panel seeds before any screen: the words of the order are the panel's, not ours.
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put, delete: vi.fn().mockResolvedValue(undefined) },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => can,
    types: { ...coreTypes, ...adminTypes },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/press', component: PressPage, props: { base: '/press' } },
      { path: '/elsewhere', component: { template: '<div />' } },
    ],
  })

  await router.push(`/press${query}`)
  await router.isReady()

  const wrapper = mount(
    { template: '<router-view />' },
    {
      attachTo: document.body,
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

  return { wrapper, get, post, put }
}

type Mounted = Awaited<ReturnType<typeof panel>>['wrapper']

/** Pick the first row up with the keyboard and put it one place down — a drag, minus the mouse. */
async function moveFirstDown(wrapper: Mounted) {
  const grip = wrapper.findAll('.wx-press__list .wx-sortable-list__grip')[0]!

  await grip.trigger('keydown', { key: ' ' })
  await grip.trigger('keydown', { key: 'ArrowDown' })
  await flushPromises()
}

/* Reka switches a tab on mousedown and focus, not on click (CLAUDE.md §4). */
async function openArticles(wrapper: Mounted) {
  await wrapper.findAll('.wx-outlet .wx-tabs__tab')[1]!.trigger('mousedown')
  await flushPromises()
}

beforeEach(() => {
  confirm.mockReset()
})

afterEach(() => {
  document.body.innerHTML = ''
})

/**
 * The list, its order, and the form beside it. Where the form goes on a phone — into the drawer,
 * with its own way back — is a width, and jsdom measures none: that is checked in a browser.
 */
describe('WxPressPage', () => {
  it('asks for every outlet and says what it holds and where it is seen', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/press')

    const [health, draft, unseen] = wrapper.findAll('.wx-outlet-row')
    expect(health!.text()).toContain('Articles: 3')
    expect(health!.get('.wx-outlet-row__logo img').attributes('src')).toBe('/thumbs/health.svg')
    expect(health!.find('.wx-avatar').exists()).toBe(false)
    expect(health!.find('.wx-outlet-row__featured').exists()).toBe(true)
    expect(health!.get('.wx-outlet-row__locales').text()).toBe('ru · en')
    expect(health!.text()).not.toContain('Not published')

    // Nothing in it yet is said in words, not as a zero; no logo is the first letters.
    expect(draft!.text()).toContain('No articles yet')
    expect(draft!.get('.wx-avatar').text()).toBe('KW')
    expect(draft!.text()).toContain('Not published')
    expect(draft!.find('.wx-outlet-row__featured').exists()).toBe(false)

    // Published, seen nowhere: the warning, not the grey badge of a draft.
    expect(unseen!.find('.wx-badge--warning').exists()).toBe(true)
  })

  it('writes the whole order after a drag', async () => {
    const { wrapper, post } = await panel()

    await moveFirstDown(wrapper)

    expect(post).toHaveBeenCalledWith('/api/cms/press/reorder', { ids: [2, 1, 3] })
  })

  it('offers no grips over a search: the gaps are rows nobody can see', async () => {
    const { wrapper, get } = await panel('?q=health')

    expect(get).toHaveBeenCalledWith('/api/cms/press?search=health')
    expect(wrapper.findAll('.wx-press__list .wx-sortable-list__grip')).toHaveLength(0)
  })

  it('opens a new outlet as a form, and makes it with its articles on the first save', async () => {
    const { wrapper, post } = await panel()

    await wrapper.get('.wx-press__new').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.outlet).toBe('new')
    expect(post).not.toHaveBeenCalledWith('/api/cms/press', expect.anything())

    await wrapper.get('.wx-outlet input').setValue('Brand new')
    await openArticles(wrapper)
    await wrapper.get('.wx-outlet .wx-repeater__add').trigger('click')
    await flushPromises()

    const [title, url] = wrapper.findAll('.wx-outlet .wx-repeater__body input')
    await title!.setValue('An interview')
    await url!.setValue('https://brand.example/interview')

    await wrapper.get('.wx-outlet .wx-action-bar button').trigger('click')
    await flushPromises()

    // One request for the outlet and its articles: a row without an id is a new article.
    expect(post).toHaveBeenCalledWith('/api/cms/press', {
      values: {
        title: { en: 'Brand new' },
        slug: {},
        summary: {},
        published: false,
        featured: false,
        articles: [{ title: { en: 'An interview' }, url: 'https://brand.example/interview' }],
      },
    })
    expect(router.currentRoute.value.query.outlet).toBe('9')
  })

  it('folds the articles to "#N · title", and keeps their ids through a save', async () => {
    const { wrapper, put } = await panel('?outlet=1')

    await openArticles(wrapper)

    const heads = wrapper.findAll('.wx-outlet .wx-repeater__head')
    expect(heads.map((head) => head.text())).toEqual([
      '#1 · Interview: a week of plates',
      '#2 · Column: breakfast',
    ])
    expect(heads.map((head) => head.attributes('aria-expanded'))).toEqual(['false', 'false'])

    await heads[1]!.trigger('click')
    await wrapper.findAll('.wx-outlet .wx-repeater__body input')[2]!.setValue('Column: lunch')
    await wrapper.get('.wx-outlet').trigger('keydown', { key: 's', ctrlKey: true })
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/press/1', {
      values: expect.objectContaining({
        articles: [
          { id: 11, title: { en: 'Interview: a week of plates' }, url: 'https://a.example/1' },
          { id: 12, title: { en: 'Column: lunch' }, url: 'https://a.example/2' },
        ],
      }),
    })
  })

  it("puts a refusal of one article under that article's field, and opens it", async () => {
    const { wrapper, put } = await panel('?outlet=1')

    await wrapper.get('.wx-outlet input').setValue('Health & Style, again')
    put.mockRejectedValueOnce({
      status: 422,
      body: { errors: { 'articles.1.title.en': ['At most 255 characters.'] } },
    })
    await wrapper.get('.wx-outlet .wx-action-bar button').trigger('click')
    await flushPromises()
    await openArticles(wrapper)

    const rows = wrapper.findAll('.wx-outlet .wx-repeater__row')
    expect(rows[0]!.find('.wx-form-item__error').exists()).toBe(false)
    expect(rows[1]!.get('.wx-form-item__error').text()).toBe('At most 255 characters.')
    expect(rows[1]!.get('.wx-repeater__head').attributes('aria-expanded')).toBe('true')

    // The outlet's own name, the same field name one level up, is not what was refused.
    await wrapper.findAll('.wx-outlet .wx-tabs__tab')[0]!.trigger('mousedown')
    await flushPromises()
    expect(wrapper.get('.wx-outlet .wx-tab:not([hidden]) .wx-form-item').classes()).not.toContain(
      'is-error',
    )
  })

  it('offers the page of the outlet on the site when it has one', async () => {
    const { wrapper } = await panel('?outlet=1')
    const open = vi.spyOn(window, 'open').mockReturnValue(null)

    const button = wrapper
      .findAll('.wx-outlet .wx-screen-head button')
      .find((one) => one.text().includes('Open on the site'))

    await button!.trigger('click')

    expect(open).toHaveBeenCalledWith('/press/health-and-style', '_blank', 'noopener')
    open.mockRestore()
  })

  it('asks before another outlet takes the place of unsaved words', async () => {
    const { wrapper } = await panel('?outlet=1')

    confirm.mockResolvedValueOnce(false)
    await wrapper.get('.wx-outlet input').setValue('Half-written')
    await wrapper.findAll('.wx-outlet-row')[1]!.trigger('click')
    await flushPromises()

    expect(confirm).toHaveBeenCalledOnce()
    expect(router.currentRoute.value.query.outlet).toBe('1')
  })

  it('brings an outlet back from the bin, and the bin has no new line', async () => {
    const { wrapper, post } = await panel('?view=trashed')

    expect(wrapper.find('.wx-press__new').exists()).toBe(false)

    await wrapper.findAll('.wx-actions__menu button')[0]!.trigger('click')
    await flushPromises()
    const items = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]

    expect(items.map((item) => item.textContent?.trim())).toEqual(['Restore'])
    items[0]!.click()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/press/1/restore', {})
  })

  it('has no new line and no grips for somebody who only reads', async () => {
    const { wrapper } = await panel('', false)

    expect(wrapper.find('.wx-press__new').exists()).toBe(false)
    expect(wrapper.findAll('.wx-press__list .wx-sortable-list__grip')).toHaveLength(0)
  })
})
