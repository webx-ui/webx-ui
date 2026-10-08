import { disableAutoUnmount, enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterAll, afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import {
  adminKey,
  adminMessages,
  adminTypes,
  createI18n,
  i18nKey,
  type AdminContext,
} from '@webx-ui/module-admin'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import type * as core from '@webx-ui/core'
import { confirm, localesKey } from '@webx-ui/core'
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

function detail(revision: string, title = 'About', slug = 'about'): PageDetail {
  return {
    page: { ...about, title },
    ancestors: [],
    values: { title, slug, blocks: [], is_home: false },
    revision,
    address_prefix: { en: '' },
    preview_url: 'https://example.test/_preview/page/2?token=x',
  }
}

/* Only the field the tests type into: what the screen holds is the server's business, and a
   second copy of the real description here would be a copy that drifts. */
let screen: ScreenNode[] = [{ id: 'title', type: 'wx-input', name: 'title' }]

/** What the editor's heartbeat hears: by default, that nothing moved and nobody else is here. */
let heartbeat: Record<string, unknown> = { revision: 'r1', changed: null, editors: [], heartbeat: 20 }

async function panel(first = detail('r1')) {
  const get = vi.fn().mockResolvedValue({ data: first })
  const put = vi.fn().mockResolvedValue({ data: detail('r2', 'About us') })
  const post = vi.fn((url: string) =>
    Promise.resolve({ data: url.includes('/editing/') ? heartbeat : about }),
  )

  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, put, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: { ...coreTypes, ...adminTypes },
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
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          [localesKey as symbol]: { list: ref([{ code: 'en' }]), active: ref('en') },
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
  heartbeat = { revision: 'r1', changed: null, editors: [], heartbeat: 20 }
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

  it('merges a save refused by an edit of another field, and saves both without asking', async () => {
    const { wrapper, put } = await panel()

    // An agent changed the address while this editor was typing the title.
    put.mockRejectedValueOnce({
      status: 409,
      body: {
        message: 'Somebody changed this page.',
        data: detail('r9', 'About', 'about-us'),
        changed: { author: 'Administrator', author_id: 1, source: 'mcp', at: '2026-10-08T10:00:00+00:00' },
      },
    })

    await type(wrapper, 'TEST About')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()

    expect(put).toHaveBeenCalledTimes(2)
    expect(put.mock.calls[1]?.[1]).toEqual({
      values: { title: 'TEST About', slug: 'about-us', blocks: [], is_home: false },
      revision: 'r9',
    })
    expect(wrapper.find('.wx-alert').exists()).toBe(false)
  })

  it('lists a field both sides changed, with all three versions, and waits for an answer', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 409,
      body: {
        message: 'Somebody changed this page.',
        data: detail('r9', 'Theirs'),
        changed: { author: 'Administrator', author_id: 1, source: 'mcp', at: null },
      },
    })

    await type(wrapper, 'Mine')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()

    const alert = wrapper.find('.wx-alert')

    expect(alert.text()).toContain('Administrator, through an agent')
    expect(alert.text()).toContain('Title')
    expect(alert.text()).toContain('About')
    expect(alert.text()).toContain('Mine')
    expect(alert.text()).toContain('Theirs')
    expect(wrapper.find('input').element.value).toBe('Mine')

    // And nothing is written again on its own: the question stands until it is answered.
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()
    expect(put).toHaveBeenCalledTimes(1)
  })

  it('keeps their change to every other field when mine is kept on the one that clashed', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 409,
      body: { message: 'Somebody changed this page.', data: detail('r9', 'Theirs', 'about-us') },
    })

    await type(wrapper, 'Mine')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()

    // «Yours» is chosen until somebody picks otherwise; the last button saves the choices.
    const save = wrapper.findAll('.wx-alert button').at(-1)
    await save?.trigger('click')
    await flushPromises()

    expect(put.mock.calls[1]?.[1]).toEqual({
      values: { title: 'Mine', slug: 'about-us', blocks: [], is_home: false },
      revision: 'r9',
    })
    expect(wrapper.find('.wx-alert').exists()).toBe(false)
  })

  it('takes their side of a clash when it is chosen', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({
      status: 409,
      body: { message: 'Somebody changed this page.', data: detail('r9', 'Theirs') },
    })

    await type(wrapper, 'Mine')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()

    const [, theirs] = wrapper.findAll('.wx-editing-alerts__choice')
    await theirs?.trigger('click')
    await wrapper.findAll('.wx-alert button').at(-1)?.trigger('click')
    await flushPromises()

    expect(wrapper.find('input').element.value).toBe('Theirs')
  })

  it('says who saved meanwhile, and pulls their edit in before this one is saved over it', async () => {
    const { wrapper, get, put } = await panel()

    heartbeat = {
      revision: 'r5',
      changed: { author: 'Administrator', author_id: 1, source: 'mcp', at: '2026-10-08T10:00:00+00:00' },
      editors: [{ id: 3, name: 'Anna', since: '2026-10-08T09:00:00+00:00', seen_at: '2026-10-08T10:00:00+00:00' }],
      heartbeat: 20,
    }
    get.mockResolvedValue({ data: detail('r5', 'About', 'about-us') })

    // Opening another page and coming back is the quickest way to make the editor ask again.
    await wrapper.vm.$router.push('/pages')
    await flushPromises()
    await wrapper.vm.$router.push('/pages/2')
    await flushPromises()

    const notice = wrapper.find('.wx-alert')

    expect(notice.text()).toContain('Administrator, through an agent changed Slug')

    await notice.find('button').trigger('click')
    await flushPromises()

    expect(wrapper.find('.wx-alert').exists()).toBe(false)

    await type(wrapper, 'TEST About')
    await wrapper.find('.wx-page-editor').trigger('focusout')
    await flushPromises()

    expect(put.mock.calls.at(-1)?.[1]).toEqual({
      values: { title: 'TEST About', slug: 'about-us', blocks: [], is_home: false },
      revision: 'r5',
    })
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

  it('saves an unsaved edit before asking, so the question names the address it will publish at', async () => {
    const { wrapper, put } = await panel()
    const saved = detail('r2', 'About us')
    saved.page = { ...saved.page, status: 'modified', next_path: 'about-us' }
    put.mockResolvedValueOnce({ data: saved })

    await type(wrapper, 'About us')
    await wrapper
      .findAll('button')
      .find((one) => one.text() === 'Publish')
      ?.trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledTimes(1)

    const asked = vi.mocked(confirm).mock.calls.at(-1)?.[0] as { message: string }

    expect(asked.message).toContain('/about-us')
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

/**
 * The address is the panel's shared field: the address of the page above stands in front of the
 * slug, in the language being edited, and a change to a page that is on the site says that the
 * old address will lead to the new one before anything is saved.
 */
describe('the address of a page', () => {
  const home: PageRow = {
    ...about,
    id: 1,
    parent_id: null,
    depth: 0,
    is_home: true,
    slug: '',
    path: '',
  }
  const consultations: PageRow = {
    ...about,
    id: 3,
    title: 'Consultations',
    slug: 'private-consultations',
  }

  function nested(): PageDetail {
    return {
      ...detail('r1'),
      ancestors: [home, consultations],
      address_prefix: { en: 'private-consultations' },
      addresses: { en: 'private-consultations/about' },
    }
  }

  const slugScreen: ScreenNode[] = [{ id: 'slug', type: 'wx-slug', name: 'slug' }]

  it('prints the address of the page above in front of the slug', async () => {
    screen = slugScreen
    const { wrapper } = await panel(nested())
    screen = [{ id: 'title', type: 'wx-input', name: 'title' }]

    expect(wrapper.find('.wx-slug__prefix').text()).toBe('/private-consultations/')
    expect(wrapper.find('input').element.value).toBe('about')
    expect(wrapper.find('.wx-slug .wx-alert').exists()).toBe(false)
  })

  it('says the address is moving once the slug is changed', async () => {
    screen = slugScreen
    const { wrapper } = await panel(nested())
    screen = [{ id: 'title', type: 'wx-input', name: 'title' }]

    await type(wrapper, 'about-us')

    expect(wrapper.find('.wx-slug .wx-alert').text()).toContain('The address is changing')
  })

  it('puts a bare slash in front under the home page', async () => {
    screen = slugScreen
    const { wrapper } = await panel({
      ...detail('r1'),
      ancestors: [home],
      addresses: { en: 'about' },
    })
    screen = [{ id: 'title', type: 'wx-input', name: 'title' }]

    expect(wrapper.find('.wx-slug__prefix').text()).toBe('/')
  })

  it('says there is no address where the page above has none', async () => {
    screen = slugScreen
    const { wrapper } = await panel({ ...nested(), address_prefix: {} })
    screen = [{ id: 'title', type: 'wx-input', name: 'title' }]

    expect(wrapper.find('.wx-slug__prefix').exists()).toBe(false)
    expect(wrapper.find('.wx-slug .wx-alert').text()).toContain('No address in this language')
  })
})
