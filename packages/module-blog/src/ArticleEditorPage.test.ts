import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import ArticleEditorPage from './ArticleEditorPage.vue'
import ArticleHistory from './ArticleHistory.vue'
import type { ArticleDetail, ArticleRow } from './types'

const belts: ArticleRow = {
  id: 4,
  title: 'Signs of wear',
  slug: 'signs-of-wear',
  lead: '',
  path: 'blog/signs-of-wear',
  url: 'https://example.test/blog/signs-of-wear',
  status: 'published',
  pinned: false,
  published_at: '2026-09-12T08:00:00+00:00',
  updated_at: '2026-09-12T08:00:00+00:00',
  deleted_at: null,
  author: { id: 1, name: 'Anna' },
  cover: null,
  rubrics: [],
  tags: [],
  revision: 'r1',
} as ArticleRow

function detail(
  revision: string,
  over: Partial<ArticleRow> = {},
  published?: string,
): ArticleDetail {
  return {
    article: { ...belts, ...over, revision },
    values: {
      title: { en: (over.title as string | undefined) ?? 'Signs of wear' },
      slug: { en: 'signs-of-wear' },
      blocks: [],
      published_at: published ?? '2026-09-12 08:00:00',
      rubrics: [],
      tags: [],
      related: [],
    },
    revision,
    prefix: 'blog',
    preview_url: 'https://example.test/_preview/article/4?token=x',
    options: { rubrics: [{ id: 3, title: 'Repairs' }], authors: [{ id: 1, title: 'Anna' }] },
    related: [],
  }
}

/*
 * Two tabs and one field: what the screen really holds is the server's business, and a second
 * copy of the real description here would be a copy that drifts. The tabs are here because the
 * way they are switched is the thing worth pinning down (see the test below).
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
        children: [{ id: 'title', type: 'wx-input', name: 'title', localized: true }],
      },
      {
        id: 'history',
        type: 'wx-tab',
        label: 'History',
        children: [{ id: 'versions', type: 'wx-article-history' }],
      },
    ],
  },
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

    return Promise.resolve({ data: first })
  })

  const put = vi.fn().mockResolvedValue({ data: detail('r2', { title: 'Seven signs of wear' }) })
  const post = vi.fn().mockResolvedValue({ data: belts })

  // The real dictionary, because the package's own English is what a panel sees before the
  // server's translations arrive — and a screen that shows keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, put, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: { ...coreTypes, 'wx-article-history': { component: ArticleHistory, kind: 'display' } },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/blog/articles', component: { template: '<div />' } },
      {
        path: '/blog/articles/:id(\\d+)',
        component: ArticleEditorPage,
        props: { base: '/blog' },
      },
    ],
  })

  await router.push('/blog/articles/4')
  await router.isReady()

  // Through a `<router-view />` rather than mounted on its own: the guard on leaving is
  // registered against the matched record, and a component with no record behind it registers
  // nothing at all.
  const wrapper = mount(
    { template: '<router-view />' },
    {
      global: {
        plugins: [router],
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          // What a panel always provides. Without it `useLocalized` switches off and a
          // localized field hands back the plain string it was given, so a test that types a
          // title would be checking a shape the panel never produces (CLAUDE.md §4).
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

/** Type into the one field the screen has, the way a person would. */
async function type(wrapper: Awaited<ReturnType<typeof panel>>['wrapper'], text: string) {
  await wrapper.find('input').setValue(text)
  await nextTick()
}

afterEach(() => {
  vi.useRealTimers()
})

/**
 * The saving and the publishing, which is what this screen owns. What it looks like — whether
 * the constructor fills the height, where the bar sits — is checked in a browser: jsdom
 * computes no layout and would pass either way (§10).
 */
describe('WxArticleEditorPage', () => {
  it('writes the draft after a pause, carrying the revision it read', async () => {
    vi.useFakeTimers()
    const { wrapper, put } = await panel()

    await type(wrapper, 'Seven signs of wear')
    expect(put).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1500)
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/blog/articles/4', {
      values: expect.objectContaining({ title: { en: 'Seven signs of wear' } }),
      revision: 'r1',
    })
  })

  it('writes at once when a field is left rather than waiting the pause out', async () => {
    const { wrapper, put } = await panel()

    await type(wrapper, 'Seven signs of wear')
    await wrapper.find('.wx-article-editor').trigger('focusout')
    await flushPromises()

    expect(put).toHaveBeenCalledTimes(1)
  })

  it('sends the revision that came back, not the one it started with', async () => {
    const { wrapper, put } = await panel()

    await type(wrapper, 'Seven signs of wear')
    await wrapper.find('.wx-article-editor').trigger('focusout')
    await flushPromises()

    await type(wrapper, 'Seven signs of wear, really')
    await wrapper.find('.wx-article-editor').trigger('focusout')
    await flushPromises()

    expect(put.mock.calls[1]?.[1]).toMatchObject({ revision: 'r2' })
  })

  it('puts a conflict on the screen instead of one version over the other', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 409,
      body: { message: 'Somebody changed this article.', data: detail('r9', { title: 'Theirs' }) },
    })

    await type(wrapper, 'Mine')
    await wrapper.find('.wx-article-editor').trigger('focusout')
    await flushPromises()

    expect(wrapper.text()).toContain('Somebody changed this article.')
    expect(wrapper.find('input').element.value).toBe('Mine')

    // And nothing is written again on its own: the question stands until it is answered.
    await wrapper.find('.wx-article-editor').trigger('focusout')
    await flushPromises()
    expect(put).toHaveBeenCalledTimes(1)
  })

  it('writes over the other version, with its revision, when mine is kept', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 409,
      body: { message: 'Somebody changed this article.', data: detail('r9', { title: 'Theirs' }) },
    })

    await type(wrapper, 'Mine')
    await wrapper.find('.wx-article-editor').trigger('focusout')
    await flushPromises()

    const keep = wrapper.findAll('.wx-alert button').at(-1)
    await keep?.trigger('click')
    await flushPromises()

    expect(put.mock.calls[1]?.[1]).toMatchObject({ revision: 'r9' })
    expect(wrapper.text()).not.toContain('Somebody changed this article.')
  })

  /*
   * Reka listens for `mousedown` and `focus` on a tab, never for `click`, so `trigger('click')`
   * here passes silently and switches nothing (CLAUDE.md §4). The tab is worth a test of its
   * own because the history is behind it and only asks the server once it is opened.
   */
  it('opens the history on mousedown, which is the event the tabs listen for', async () => {
    const { wrapper, get } = await panel()

    const tabs = wrapper.findAll('.wx-tabs__tab')

    expect(tabs).toHaveLength(2)
    expect(get).not.toHaveBeenCalledWith('/api/cms/blog/articles/4/versions')

    await tabs[1]!.trigger('mousedown')
    await flushPromises()

    expect(tabs[1]!.attributes('data-state')).toBe('active')
    expect(get).toHaveBeenCalledWith('/api/cms/blog/articles/4/versions')
    expect(wrapper.find('.wx-article-history').text()).toContain('Anna')
  })

  it('hands the constructor a preview of the draft', async () => {
    const { wrapper } = await panel()

    expect(wrapper.find('a[href*="_preview"]').exists()).toBe(true)
  })

  it('leaves without asking when the save is already on its way', async () => {
    const { wrapper, put, router } = await panel()

    let answer: (value: unknown) => void = () => {}
    put.mockImplementationOnce(() => new Promise((resolve) => (answer = resolve)))

    await type(wrapper, 'Seven signs of wear')
    await wrapper.find('.wx-article-editor').trigger('focusout')
    expect(put).toHaveBeenCalledTimes(1)

    // Leaving while that request is out used to skip the save, read `dirty` and ask.
    const leaving = router.push('/blog/articles')
    await flushPromises()

    answer({ data: detail('r2', { title: 'Seven signs of wear' }) })
    await leaving
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/blog/articles')
    expect(put).toHaveBeenCalledTimes(1)
  })
})
