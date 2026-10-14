import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
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
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import AuthorsPage from './AuthorsPage.vue'
import type { AuthorDetail, AuthorRow } from './authors'

function row(id: number, name: string, over: Partial<AuthorRow> = {}): AuthorRow {
  return {
    id,
    name,
    user: { id: id + 100, name: `Login ${id}` },
    is_public: true,
    open: true,
    url: `/blog/author/${id}`,
    photo: null,
    articles_count: 2,
    ...over,
  }
}

const rows: AuthorRow[] = [
  row(1, 'Anna Writer'),
  row(2, 'Oleg Quiet', { is_public: false, open: false, articles_count: 0 }),
]

function detail(id: number, name: string): AuthorDetail {
  return {
    author: row(id, name),
    values: {
      title: { en: name },
      slug: { en: 'anna' },
      role: {},
      bio: {},
      photo: null,
      is_public: true,
    },
  }
}

/* A slice of `blog.author-form`: a name to type and the switch of the page. */
const screen: ScreenNode[] = [
  { id: 'title', type: 'wx-input', name: 'title', localized: true },
  { id: 'is-public', type: 'wx-switch', name: 'is_public' },
]

let router: Router

async function panel(query = '') {
  const get = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/accounts')) {
      return Promise.resolve({ data: [{ id: 7, name: 'New Login', articles_count: 3 }] })
    }

    const one = /\/authors\/(\d+)$/.exec(url)

    return Promise.resolve(one ? { data: detail(Number(one[1]), 'Anna Writer') } : { data: rows })
  })
  const post = vi.fn().mockResolvedValue({ data: detail(9, 'New Login') })
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put: vi.fn(), delete: vi.fn().mockResolvedValue(undefined) },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: { ...coreTypes, ...adminTypes },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/blog/authors', component: AuthorsPage, props: { base: '/blog' } }],
  })

  await router.push(`/blog/authors${query}`)
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
          [localesKey as symbol]: { list: ref([{ code: 'en' }]), active: ref('en') },
        },
      },
    },
  )

  await flushPromises()

  return { wrapper, get, post }
}

afterEach(() => {
  document.body.innerHTML = ''
})

/**
 * The list of profiles and the form beside it (§19.5 of the blog spec). Where the form goes on a
 * phone is a width, and jsdom measures none: that is checked in a browser.
 */
describe('WxAuthorsPage', () => {
  it('lists the profiles with their account, their articles and whether the page answers', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/blog/authors')

    const [anna, oleg] = wrapper.findAll('.wx-author-row')

    expect(anna!.text()).toContain('Anna Writer')
    expect(anna!.text()).toContain('Login 1')
    expect(anna!.text()).toContain('Articles: 2')
    expect(anna!.text()).toContain('Page on the site')
    expect(oleg!.text()).toContain('No page')
  })

  it('opens the profile named in the address', async () => {
    const { wrapper, get } = await panel('?author=1')

    expect(get).toHaveBeenCalledWith('/api/cms/blog/authors/1')
    expect(wrapper.find('.wx-author').exists()).toBe(true)
  })

  it('makes a new profile for the account the article editor asked for', async () => {
    const { wrapper, get, post } = await panel('?author=new&account=7')

    expect(get).toHaveBeenCalledWith('/api/cms/blog/authors/accounts')

    // The account's name is the first guess at the name for readers.
    const name = wrapper.get('.wx-author .wx-screen-host input[type="text"]')
    expect((name.element as HTMLInputElement).value).toBe('New Login')

    await wrapper
      .findAll('.wx-author button')
      .find((button) => button.text() === 'Save')!
      .trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/blog/authors', {
      user_id: 7,
      values: expect.objectContaining({ title: { en: 'New Login' } }),
    })
    expect(router.currentRoute.value.query.author).toBe('9')
    expect(router.currentRoute.value.query.account).toBeUndefined()
  })

  it('asks for the account before it asks the server', async () => {
    const { wrapper, post } = await panel('?author=new')

    await wrapper
      .findAll('.wx-author button')
      .find((button) => button.text() === 'Save')!
      .trigger('click')
    await flushPromises()

    expect(post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Choose the account this profile is for.')
  })
})
