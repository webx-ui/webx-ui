import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { WebxUI } from '@webx-ui/core'
import {
  adminKey,
  createI18n,
  HttpError,
  i18nKey,
  siteBarKey,
  type AdminContext,
  type SiteBarContext,
} from '@webx-ui/module-admin'
import BlocksSiteBarTab from './BlocksSiteBarTab.vue'
import { blocksMessages } from './messages'
import type { BlockType, SiteBarBlocks } from './types'

const page: SiteBarContext = {
  url: '/pricing',
  locale: 'en',
  entity: {
    type: 'page',
    id: 12,
    kind: 'Page',
    label: 'Pricing',
    status: 'published',
    draft: null,
    updatedAt: null,
    edit: '/pages/12',
    editing: 'pages',
    revision: 'r1',
    can: { edit: true, publish: true },
  },
  sections: ['page', 'blocks'],
}

const text = {
  id: 1,
  slug: 'text',
  title: 'Text',
  content: { schema: [{ id: 'text', type: 'wx-input', label: 'Words' }], sample: {} },
} as unknown as BlockType

function content(words = 'Draft words', revision = 'r1'): SiteBarBlocks {
  return {
    revision,
    editing: 'draft',
    tree: [
      { key: 'k1', type: 'text', values: { text: words } },
      { type: 'text', values: { text: 'Old' } } as unknown as SiteBarBlocks['tree'][number],
    ],
    outline: [
      { key: 'k1', type: 'text', depth: 0, label: words },
      { type: 'text', depth: 0, label: 'Old' },
    ],
  }
}

const mounted: VueWrapper[] = []

/** Stands in for the page that frames the panel: in jsdom a window is its own parent. */
function framed(): Window {
  const frame = document.createElement('iframe')
  document.body.append(frame)
  const parent = frame.contentWindow!

  vi.spyOn(parent, 'postMessage').mockImplementation(() => {})
  vi.spyOn(window, 'parent', 'get').mockReturnValue(parent)

  return parent
}

function sent(parent: Window, type: string): unknown[] {
  return vi
    .mocked(parent.postMessage)
    .mock.calls.map(([message]) => message as { type?: string })
    .filter((message) => message.type === type)
}

async function open(read: () => SiteBarBlocks = () => content()) {
  const parent = framed()
  const i18n = createI18n()
  i18n.defaults('webx-blocks', blocksMessages)

  const client = {
    get: vi
      .fn()
      .mockImplementation((url: string) =>
        Promise.resolve(url.endsWith('/catalog') ? { data: [text] } : { data: read() }),
      ),
    post: vi
      .fn()
      .mockImplementation((url: string) =>
        Promise.resolve(
          url.endsWith('/edit')
            ? { data: { revision: 'r2', key: 'k1', html: '<!--wx:k1--><p>New</p><!--/wx:k1-->' } }
            : { data: { editors: [] } },
        ),
      ),
    delete: vi.fn().mockResolvedValue(undefined),
  }

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: client,
    i18n,
    modules: [],
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const bar = { show: vi.fn(), refresh: vi.fn().mockResolvedValue(undefined) }

  const wrapper = mount(BlocksSiteBarTab, {
    props: { context: page },
    attachTo: document.body,
    global: {
      plugins: [WebxUI],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: i18n,
        [siteBarKey as symbol]: bar,
      },
    },
  })

  mounted.push(wrapper)
  await flushPromises()

  return { wrapper, client, parent, bar }
}

function row(wrapper: VueWrapper, index: number) {
  return wrapper.findAll('.wx-blocks-bar__row')[index]!
}

/**
 * «Blocks» in the panel on the site (WEBX_UI_SITE_BAR.md, §8–§9). How the frames sit on the
 * page is the browser's to check; here — what the tab reads, what it writes and what it tells
 * the page.
 */
describe('the blocks tab of the panel on the site', () => {
  afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount())
    document.body.innerHTML = ''
    vi.restoreAllMocks()
  })

  it('shows the record’s blocks as a map and tells the page which it may pick', async () => {
    const { wrapper, client, parent } = await open()

    expect(client.get).toHaveBeenCalledWith('/api/cms/blocks/content/page/12')
    expect(row(wrapper, 0).text()).toContain('Text')
    expect(row(wrapper, 0).text()).toContain('Draft words')
    expect(sent(parent, 'blocks')).toEqual([
      {
        wxBar: 1,
        type: 'blocks',
        entity: { type: 'page', id: 12 },
        items: [{ key: 'k1', label: 'Text' }],
      },
    ])
  })

  it('does not offer a block of old content that has no key', async () => {
    const { wrapper } = await open()

    expect(row(wrapper, 1).attributes('disabled')).toBeDefined()
    expect(row(wrapper, 1).text()).toContain('Open in panel to edit')
  })

  it('frames a block on the page while its row is under the pointer', async () => {
    const { wrapper, parent } = await open()

    await row(wrapper, 0).trigger('mouseenter')
    await row(wrapper, 0).trigger('mouseleave')

    expect(sent(parent, 'highlight')).toEqual([
      { wxBar: 1, type: 'highlight', key: 'k1' },
      { wxBar: 1, type: 'highlight', key: null },
    ])
  })

  it('opens a block with the draft’s values and saves only what changed into the draft', async () => {
    const { wrapper, client, parent, bar } = await open()

    await row(wrapper, 0).trigger('click')
    await flushPromises()

    const input = wrapper.find('input')
    expect((input.element as HTMLInputElement).value).toBe('Draft words')
    expect(sent(parent, 'select')).toEqual([{ wxBar: 1, type: 'select', key: 'k1' }])

    await input.setValue('New')
    await wrapper
      .findAll('button')
      .find((one) => one.text() === 'Save to draft')!
      .trigger('click')
    await flushPromises()

    expect(client.post).toHaveBeenCalledWith('/api/cms/blocks/content/page/12/edit', {
      revision: 'r1',
      locale: 'en',
      ops: [{ op: 'set', key: 'k1', values: { text: 'New' } }],
    })
    expect(sent(parent, 'replace')).toEqual([
      { wxBar: 1, type: 'replace', key: 'k1', html: '<!--wx:k1--><p>New</p><!--/wx:k1-->' },
    ])
    expect(bar.refresh).toHaveBeenCalled()
  })

  it('says it is there where the record’s editor listens while a form is open', async () => {
    const { wrapper, client } = await open()

    await row(wrapper, 0).trigger('click')
    await flushPromises()

    expect(client.post).toHaveBeenCalledWith('/api/cms/editing/pages/12')

    await wrapper.find('.wx-blocks-bar__trail button').trigger('click')

    expect(client.delete).toHaveBeenCalledWith('/api/cms/editing/pages/12')
  })

  it('reads the block again when somebody wrote the page in the meantime', async () => {
    let words = 'Draft words'
    const { wrapper, client } = await open(() => content(words))

    await row(wrapper, 0).trigger('click')
    await flushPromises()
    await wrapper.find('input').setValue('Mine')

    words = 'Theirs'
    client.post.mockImplementation((url: string) =>
      url.endsWith('/edit')
        ? Promise.reject(
            new HttpError('Conflict', 409, {}, null, { changed: { author: 'J. Doe' } }),
          )
        : Promise.resolve({ data: { editors: [] } }),
    )

    await wrapper
      .findAll('button')
      .find((one) => one.text() === 'Save to draft')!
      .trigger('click')
    await flushPromises()

    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Theirs')
  })

  it('opens the form of a block picked on the page', async () => {
    const { wrapper, parent, bar } = await open()

    window.dispatchEvent(
      new MessageEvent('message', {
        data: { wxBar: 1, type: 'select', key: 'k1' },
        origin: window.location.origin,
        source: parent,
      }),
    )
    await flushPromises()

    expect(bar.show).toHaveBeenCalledWith('blocks')
    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Draft words')
  })

  it('does not listen to anybody but the page that frames it', async () => {
    const { wrapper } = await open()

    window.dispatchEvent(
      new MessageEvent('message', {
        data: { wxBar: 1, type: 'select', key: 'k1' },
        origin: 'https://elsewhere.test',
        source: window.parent,
      }),
    )
    await flushPromises()

    expect(wrapper.find('input').exists()).toBe(false)
  })

  it('turns picking on and off on the page, and Esc ends it', async () => {
    const { wrapper, parent } = await open()

    await wrapper.find('.wx-blocks-bar__tools button').trigger('click')
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(sent(parent, 'mode')).toEqual([
      { wxBar: 1, type: 'mode', on: true },
      { wxBar: 1, type: 'mode', on: false },
    ])
  })
})
