import { disableAutoUnmount, enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterAll, afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import {
  adminKey,
  adminMessages,
  createI18n,
  i18nKey,
  type AdminContext,
} from '@webx-ui/module-admin'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import type * as core from '@webx-ui/core'
import { confirm } from '@webx-ui/core'
import PageEditorPage from './PageEditorPage.vue'
import type { PageDetail, PageRow } from './types'

// The question is the thing under test, not the dialog that asks it: the dialog mounts outside
// the app, where the wrapper cannot reach.
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof core>()),
  confirm: vi.fn().mockResolvedValue(false),
}))

// The editor's autosave pause outlives a test that never unmounts it, and fires into a torn-down
// jsdom: "Element is not defined" from a ref callback, after every test has already passed. The
// switch is global to the test utils, so it is handed back for the next file in a shared worker.
enableAutoUnmount(afterEach)
afterAll(disableAutoUnmount)

const about: PageRow = {
  id: 2,
  parent_id: 1,
  depth: 1,
  is_home: false,
  title: 'About',
  slug: 'about',
  path: 'about',
  url: 'https://example.test/about',
  status: 'published',
  published_at: '2026-09-01T00:00:00+00:00',
  updated_at: '2026-09-02T00:00:00+00:00',
  edited_by: 'Editor',
  children_count: 0,
  descendants_count: 0,
  deleted_at: null,
  trashed_with: null,
  can: { move: true, delete: true, address: true },
}

function detail(revision: string, title = 'About'): PageDetail {
  return {
    page: { ...about, title },
    ancestors: [],
    values: { title, slug: 'about', blocks: [], is_home: false },
    revision,
    address_prefix: { en: '' },
    preview_url: 'https://example.test/_preview/page/2?token=x',
  }
}

/* Only the field the tests type into: what the screen holds is the server's business, and a
   second copy of the real description here would be a copy that drifts. */
const screen: ScreenNode[] = [{ id: 'title', type: 'wx-input', name: 'title' }]

async function panel(first = detail('r1')) {
  const get = vi.fn().mockResolvedValue({ data: first })
  const put = vi.fn().mockResolvedValue({ data: detail('r2', 'About us') })
  const post = vi.fn().mockResolvedValue({ data: about })

  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, put, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: coreTypes,
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/pages', component: { template: '<div />' } },
      { path: '/pages/:id(\\d+)', component: PageEditorPage, props: { base: '/pages' } },
    ],
  })

  await router.push('/pages/2')
  await router.isReady()

  // Through a `<router-view />` rather than mounted on its own: the guard on leaving is
  // registered against the matched record, and a component with no record behind it registers
  // nothing at all.
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
 * The saving, which is the whole of what this screen owns. What it looks like — the sticky
 * head, whether the constructor fills the height — is checked in a browser: jsdom computes no
 * layout and would pass either way (§10).
 */
describe('WxPageEditorPage', () => {
  it('writes the draft after a pause, carrying the revision it read', async () => {
    vi.useFakeTimers()
    const { wrapper, put } = await panel()

    await type(wrapper, 'About us')
    expect(put).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1500)
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/pages/2', {
      values: { title: 'About us', slug: 'about', blocks: [], is_home: false },
      revision: 'r1',
    })
  })

  it('writes at once when a field is left rather than waiting the pause out', async () => {
    const { wrapper, put } = await panel()

    await type(wrapper, 'About us')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()

    expect(put).toHaveBeenCalledTimes(1)
  })

  it('sends the revision that came back, not the one it started with', async () => {
    const { wrapper, put } = await panel()

    await type(wrapper, 'About us')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()

    await type(wrapper, 'About us, really')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()

    expect(put.mock.calls[1]?.[1]).toMatchObject({ revision: 'r2' })
  })

  it('puts a conflict on the screen instead of one version over the other', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 409,
      body: { message: 'Somebody changed this page.', data: detail('r9', 'Theirs') },
    })

    await type(wrapper, 'Mine')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()

    expect(wrapper.text()).toContain('Somebody changed this page.')
    expect(wrapper.find('input').element.value).toBe('Mine')

    // And nothing is written again on its own: the question stands until it is answered.
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()
    expect(put).toHaveBeenCalledTimes(1)
  })

  it('writes over the other version, with its revision, when mine is kept', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 409,
      body: { message: 'Somebody changed this page.', data: detail('r9', 'Theirs') },
    })

    await type(wrapper, 'Mine')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()

    const keep = wrapper.findAll('.wx-alert button').at(-1)
    await keep?.trigger('click')
    await flushPromises()

    expect(put.mock.calls[1]?.[1]).toMatchObject({ revision: 'r9', values: { title: 'Mine' } })
    expect(wrapper.text()).not.toContain('Somebody changed this page.')
  })

  it('hands the constructor a preview of the draft', async () => {
    const { wrapper } = await panel()

    expect(wrapper.find('a[href*="_preview"]').exists()).toBe(true)
  })

  it('names the address publishing moves the page to, and says the old one leads there', async () => {
    const renamed = detail('r1')
    renamed.page = { ...renamed.page, status: 'modified', next_path: 'about-us' }

    const { wrapper } = await panel(renamed)
    const button = wrapper.findAll('button').find((one) => one.text() === 'Publish')

    await button?.trigger('click')
    await flushPromises()

    const asked = vi.mocked(confirm).mock.calls.at(-1)?.[0] as { message: string }

    expect(asked.message).toContain('/about-us')
    expect(asked.message).toContain('/about will lead to the new one')
  })

  it('leaves without asking when the save is already on its way', async () => {
    const { wrapper, put, router } = await panel()

    let answer: (value: unknown) => void = () => {}
    put.mockImplementationOnce(() => new Promise((resolve) => (answer = resolve)))

    await type(wrapper, 'About us')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    expect(put).toHaveBeenCalledTimes(1)

    // Leaving while that request is out used to skip the save, read `dirty` and ask.
    const leaving = router.push('/pages')
    await flushPromises()

    answer({ data: detail('r2', 'About us') })
    await leaving
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/pages')
    expect(put).toHaveBeenCalledTimes(1)
  })
})
