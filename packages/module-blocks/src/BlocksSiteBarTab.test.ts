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
import type { BlockType, RegionRow, SiteBarBlocks, SiteBarRegion } from './types'

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
  group: 'content',
  kind: 'block',
  is_enabled: true,
  published: { number: 1 },
  allow: null,
  allowed_in: null,
  max_per_entity: null,
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

function region(state: Partial<SiteBarRegion> = {}, words = 'Menu words'): SiteBarBlocks {
  return {
    revision: 'h1',
    editing: 'live',
    tree: [{ key: 'm1', type: 'text', values: { text: words } }],
    outline: [{ key: 'm1', type: 'text', depth: 0, label: words }],
    region: {
      name: 'header',
      title: 'Header',
      published: true,
      fallback: 'components.header',
      draft: { differs: false, by: null, at: null, changes: 0, mine: true },
      ...state,
    },
  }
}

const headerRow = { name: 'header', title: 'Header' } as RegionRow

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

interface Options {
  context?: SiteBarContext
  header?: () => SiteBarBlocks
  rows?: RegionRow[]
  /** What an edit answers, by what it sent. */
  edited?: (body: { ops: { op: string }[] }) => unknown
}

async function open(read: () => SiteBarBlocks = () => content(), options: Options = {}) {
  const parent = framed()
  const i18n = createI18n()
  i18n.defaults('webx-blocks', blocksMessages)

  const client = {
    get: vi
      .fn()
      .mockImplementation((url: string) =>
        Promise.resolve(
          url.endsWith('/catalog')
            ? { data: [text] }
            : url.endsWith('/api/cms/regions')
              ? { data: options.rows ?? [headerRow] }
              : url.endsWith('/content/region/header')
                ? { data: (options.header ?? (() => region()))() }
                : { data: read() },
        ),
      ),
    post: vi.fn().mockImplementation((url: string, body: { ops: { op: string }[] }) =>
      Promise.resolve(
        url.endsWith('/edit')
          ? {
              data: options.edited?.(body) ?? {
                revision: 'r2',
                key: 'k1',
                html: '<!--wx:k1--><p>New</p><!--/wx:k1-->',
              },
            }
          : url.endsWith('/publish')
            ? { data: {} }
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
    props: { context: options.context ?? page },
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
        sources: [{ scope: 'page:12', items: [{ key: 'k1', label: 'Text' }] }],
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
      { wxBar: 1, type: 'highlight', key: 'k1', scope: 'page:12' },
      { wxBar: 1, type: 'highlight', key: null, scope: 'page:12' },
    ])
  })

  it('opens a block with the draft’s values and saves only what changed into the draft', async () => {
    const { wrapper, client, parent, bar } = await open()

    await row(wrapper, 0).trigger('click')
    await flushPromises()

    const input = wrapper.find('input')
    expect((input.element as HTMLInputElement).value).toBe('Draft words')
    expect(sent(parent, 'select')).toEqual([
      { wxBar: 1, type: 'select', key: 'k1', scope: 'page:12' },
    ])

    await input.setValue('New')
    await wrapper
      .findAll('button')
      .find((one) => one.text() === 'Save to draft')!
      .trigger('click')
    await flushPromises()

    expect(client.post).toHaveBeenCalledWith('/api/cms/blocks/content/page/12/edit', {
      revision: 'r1',
      locale: 'en',
      url: '/pricing',
      ops: [{ op: 'set', key: 'k1', values: { text: 'New' } }],
    })
    expect(sent(parent, 'replace')).toEqual([
      {
        wxBar: 1,
        type: 'replace',
        key: 'k1',
        scope: 'page:12',
        html: '<!--wx:k1--><p>New</p><!--/wx:k1-->',
      },
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

/**
 * The header and the footer in the same tab (SB4): a map under the region's name, its own draft
 * with its own buttons, and edits into the region's draft drawn as on the page.
 */
describe('the regions in the blocks tab', () => {
  const both: SiteBarContext = { ...page, sections: ['page', 'blocks', 'regions'] }
  const regionsOnly: SiteBarContext = { ...page, entity: null, sections: ['regions'] }

  afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount())
    document.body.innerHTML = ''
    vi.restoreAllMocks()
  })

  it('maps the record and each region, and tells the page whose blocks are whose', async () => {
    const { wrapper, client, parent } = await open(undefined, { context: both })

    expect(client.get).toHaveBeenCalledWith('/api/cms/regions')
    expect(client.get).toHaveBeenCalledWith('/api/cms/blocks/content/region/header')

    const heads = wrapper.findAll('.wx-blocks-bar__head').map((head) => head.text())
    expect(heads[0]).toContain('Page')
    expect(heads[1]).toContain('Header')
    expect(heads[1]).toContain('On every page')

    expect(sent(parent, 'blocks')).toEqual([
      {
        wxBar: 1,
        type: 'blocks',
        sources: [
          { scope: 'page:12', items: [{ key: 'k1', label: 'Text' }] },
          { scope: 'region:header', title: 'Header', items: [{ key: 'm1', label: 'Text' }] },
        ],
      },
    ])
  })

  it('works on a page without a record for whoever edits the regions', async () => {
    const { wrapper, client } = await open(undefined, { context: regionsOnly })

    expect(client.get).not.toHaveBeenCalledWith('/api/cms/blocks/content/page/12')
    expect(wrapper.findAll('.wx-blocks-bar__group')).toHaveLength(1)
    expect(row(wrapper, 0).attributes('data-scope')).toBe('region:header')
  })

  it('saves a region’s block into the region’s draft, drawn as on this page', async () => {
    const { wrapper, client, parent, bar } = await open(undefined, { context: regionsOnly })

    await row(wrapper, 0).trigger('click')
    await flushPromises()

    expect(client.post).toHaveBeenCalledWith('/api/cms/editing/regions/header')
    expect(wrapper.text()).toContain('Header')

    await wrapper.find('input').setValue('New menu')
    await wrapper
      .findAll('button')
      .find((one) => one.text() === 'Save to draft')!
      .trigger('click')
    await flushPromises()

    expect(client.post).toHaveBeenCalledWith('/api/cms/blocks/content/region/header/edit', {
      revision: 'h1',
      locale: 'en',
      url: '/pricing',
      ops: [{ op: 'set', key: 'm1', values: { text: 'New menu' } }],
    })
    expect(sent(parent, 'replace')).toEqual([
      expect.objectContaining({ key: 'm1', scope: 'region:header' }),
    ])
    // The head of the bar counts the record's draft, not the region's.
    expect(bar.refresh).not.toHaveBeenCalled()
  })

  it('shows the region’s draft over the form the block was saved from, not only over the map', async () => {
    const draft = { differs: true, by: null, at: null, changes: 1, mine: true }
    let saved = false
    const { wrapper } = await open(undefined, {
      context: regionsOnly,
      header: () => (saved ? region({ draft }) : region()),
    })

    await row(wrapper, 0).trigger('click')
    await flushPromises()
    expect(wrapper.find('.wx-alert').exists()).toBe(false)

    saved = true
    await wrapper.find('input').setValue('New menu')
    await wrapper
      .findAll('button')
      .find((one) => one.text() === 'Save to draft')!
      .trigger('click')
    await flushPromises()

    // Still in the form, and the region's notice with its buttons is right there.
    expect(wrapper.find('input').exists()).toBe(true)
    const alert = wrapper.find('.wx-alert')
    expect(alert.text()).toContain('Unpublished changes: 1')
    expect(alert.findAll('button').map((one) => one.text())).toEqual(['Publish', 'Discard'])
  })

  it('publishes the region’s draft with the revision it read, and the page reloads', async () => {
    const draft = { differs: true, by: 'J. Doe', at: null, changes: 2, mine: false }
    const { wrapper, client, parent } = await open(undefined, {
      context: regionsOnly,
      header: () => region({ draft }),
    })

    const alert = wrapper.find('.wx-alert')
    expect(alert.text()).toContain('Unpublished changes: 2')
    expect(alert.text()).toContain('J. Doe has a draft here; your edits go into it')

    await alert
      .findAll('button')
      .find((one) => one.text() === 'Publish')!
      .trigger('click')
    await flushPromises()

    expect(client.post).toHaveBeenCalledWith('/api/cms/regions/header/publish', { revision: 'h1' })
    expect(sent(parent, 'reload')).toHaveLength(1)
  })

  it('says why a region was not published', async () => {
    const draft = { differs: true, by: null, at: null, changes: 1, mine: true }
    const { wrapper, client, parent } = await open(undefined, {
      context: regionsOnly,
      header: () => region({ draft }),
    })

    client.post.mockImplementation((url: string) =>
      url.endsWith('/publish')
        ? Promise.reject(
            new HttpError('Not published', 422, { blocks: ['Menu (m1): the menu is gone'] }),
          )
        : Promise.resolve({ data: { editors: [] } }),
    )

    await wrapper
      .find('.wx-alert')
      .findAll('button')
      .find((one) => one.text() === 'Publish')!
      .trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Menu (m1): the menu is gone')
    expect(sent(parent, 'reload')).toHaveLength(0)
  })

  it('points a region the site does not show yet to the panel instead of a map', async () => {
    const { wrapper } = await open(undefined, {
      context: regionsOnly,
      header: () => region({ published: false }),
    })

    expect(wrapper.findAll('.wx-blocks-bar__row')).toHaveLength(0)
    expect(wrapper.text()).toContain('The site shows the markup from code here')
    expect(wrapper.find('a[href="/cms/regions/header"]').exists()).toBe(true)
  })

  it('opens a region’s block picked on the page by its tree', async () => {
    const { wrapper, parent } = await open(undefined, { context: both })

    window.dispatchEvent(
      new MessageEvent('message', {
        data: { wxBar: 1, type: 'select', key: 'm1', scope: 'region:header' },
        origin: window.location.origin,
        source: parent,
      }),
    )
    await flushPromises()

    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Menu words')
  })

  it('says a refusal about the content around the block is the panel’s to fix', async () => {
    const { wrapper, client } = await open()

    await row(wrapper, 0).trigger('click')
    await flushPromises()
    await wrapper.find('input').setValue('New')

    client.post.mockImplementation((url: string) =>
      url.endsWith('/edit')
        ? Promise.reject(
            new HttpError('Invalid', 422, { 'blocks.k9': ['«Text» cannot go inside «Columns»'] }),
          )
        : Promise.resolve({ data: { editors: [] } }),
    )

    await wrapper
      .findAll('button')
      .find((one) => one.text() === 'Save to draft')!
      .trigger('click')
    await flushPromises()

    const alert = wrapper.find('.wx-blocks-bar__refused')
    expect(alert.text()).toContain('Not saved: what stands around this block')
    expect(alert.text()).toContain('«Text» cannot go inside «Columns»')
    expect(alert.find('a[href="/cms/pages/12"]').exists()).toBe(true)
  })
})

/** Open a row's `···` and press the line that says `label`. */
async function act(wrapper: VueWrapper, index: number, label: string): Promise<HTMLElement> {
  await wrapper
    .findAll('.wx-blocks-bar__item')
    [index]!.get('.wx-actions__menu button')
    .trigger('click')
  await flushPromises()

  const line = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')].find((item) =>
    item.textContent?.includes(label),
  )!

  return line
}

function fromPage(parent: Window, data: Record<string, unknown>): void {
  window.dispatchEvent(
    new MessageEvent('message', {
      data: { wxBar: 1, ...data },
      origin: window.location.origin,
      source: parent,
    }),
  )
}

/**
 * The structure from the site (SB5): blocks added, duplicated, moved, hidden and deleted into the
 * draft by the agent's operations, the page told what to do with the answer.
 */
describe('the structure of the blocks from the site', () => {
  afterEach(async () => {
    // The picker and the confirm take their hosts away on a timer: gone before the document is.
    await vi.waitFor(() => expect(document.querySelector('.wx-modal-host')).toBeNull())
    mounted.splice(0).forEach((wrapper) => wrapper.unmount())
    document.body.innerHTML = ''
    vi.restoreAllMocks()
  })

  const added = {
    revision: 'r2',
    key: 'n1',
    html: '<!--wx:n1--><p></p><!--/wx:n1-->',
    steps: [
      {
        do: 'place',
        key: 'n1',
        html: '<!--wx:n1--><p></p><!--/wx:n1-->',
        after: ['k1'],
        before: [],
        top: true,
      },
    ],
    assets: { hash: 'abcdef0123456789', css: '/blocks/abcdef0123456789.css', js: null },
  }

  function withNew(): SiteBarBlocks {
    const read = content()

    return {
      ...read,
      tree: [...read.tree, { key: 'n1', type: 'text', values: {} }],
      outline: [...read.outline, { key: 'n1', type: 'text', depth: 0 }],
    }
  }

  it('adds a block below another through the constructor’s picker and opens its form', async () => {
    let reads = 0
    const { wrapper, client, parent } = await open(() => (reads++ > 0 ? withNew() : content()), {
      edited: () => added,
    })

    // The page says which sets of styles it has; the edit sends them on.
    fromPage(parent, { type: 'bundles', hashes: ['1111222233334444'] })
    ;(await act(wrapper, 0, 'Add below')).click()
    await flushPromises()

    document.querySelector<HTMLElement>('.wx-block-picker__card')!.click()
    await flushPromises()

    expect(client.post).toHaveBeenCalledWith('/api/cms/blocks/content/page/12/edit', {
      revision: 'r1',
      locale: 'en',
      url: '/pricing',
      bundles: ['1111222233334444'],
      ops: [{ op: 'add', type: 'text', values: {}, after: 'k1' }],
    })

    // The styles first, then the block put in after its neighbour, then chosen.
    const calls = vi
      .mocked(parent.postMessage)
      .mock.calls.map(([message]) => message as { type: string })
    const types = calls.map((message) => message.type)
    expect(types.indexOf('assets')).toBeLessThan(types.indexOf('insert'))
    expect(sent(parent, 'insert')).toEqual([
      {
        wxBar: 1,
        type: 'insert',
        key: 'n1',
        scope: 'page:12',
        html: '<!--wx:n1--><p></p><!--/wx:n1-->',
        after: ['k1'],
        before: [],
        top: true,
      },
    ])
    expect(sent(parent, 'select')).toContainEqual({
      wxBar: 1,
      type: 'select',
      key: 'n1',
      scope: 'page:12',
    })
    expect(wrapper.find('.wx-blocks-bar__form input').exists()).toBe(true)
  })

  it('opens the picker from «Add below» on the page', async () => {
    const { parent, bar } = await open()

    fromPage(parent, { type: 'add', key: 'k1', scope: 'page:12' })
    await flushPromises()

    expect(bar.show).toHaveBeenCalledWith('blocks')
    expect(document.querySelector('.wx-block-picker__card')).not.toBeNull()

    // Shut again, so the dialog is gone before the document is.
    const foot = [...document.querySelectorAll<HTMLButtonElement>('.wx-dialog__foot button')]
    foot[foot.length - 1]!.click()
    await flushPromises()
  })

  it('greys what cannot be done right now and says why', async () => {
    const full = { ...headerRow, allow: null, max: 1 } as RegionRow
    const { wrapper } = await open(undefined, {
      context: { ...page, entity: null, sections: ['regions'] },
      rows: [full],
    })

    // The only block of the header: first, last, and the header takes no more.
    const up = await act(wrapper, 0, 'Move up')
    expect(up.hasAttribute('disabled')).toBe(true)
    expect(up.textContent).toContain('Already first')
    const lines = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]
    const duplicate = lines.find((line) => line.textContent?.includes('Duplicate'))!
    expect(duplicate.hasAttribute('disabled')).toBe(true)
    expect(duplicate.textContent).toContain('No room for more blocks here. Most it takes: 1')

    const add = wrapper.findAll('.wx-blocks-bar__add button')[0]!
    expect(add.attributes('disabled')).toBeDefined()
    expect(wrapper.find('.wx-blocks-bar__add').text()).toContain('Most it takes: 1')
  })

  it('moves a block down past its neighbour, and hides it', async () => {
    const two = (): SiteBarBlocks => ({
      ...content(),
      tree: [
        { key: 'k1', type: 'text', values: { text: 'One' } },
        { key: 'k2', type: 'text', values: { text: 'Two' } },
      ],
      outline: [
        { key: 'k1', type: 'text', depth: 0, label: 'One' },
        { key: 'k2', type: 'text', depth: 0, label: 'Two' },
      ],
    })
    const { wrapper, client, parent } = await open(two, {
      edited: (body) => ({
        revision: 'r2',
        key: 'k1',
        html: '',
        steps:
          body.ops[0]!.op === 'move'
            ? [
                {
                  do: 'move',
                  key: 'k1',
                  html: '<!--wx:k1--><!--/wx:k1-->',
                  after: ['k2'],
                  before: [],
                  top: true,
                },
              ]
            : [{ do: 'remove', key: 'k1' }],
      }),
    })

    ;(await act(wrapper, 0, 'Move down')).click()
    await flushPromises()

    expect(client.post).toHaveBeenLastCalledWith(
      '/api/cms/blocks/content/page/12/edit',
      expect.objectContaining({ ops: [{ op: 'move', key: 'k1', after: 'k2' }] }),
    )
    expect(sent(parent, 'move')).toEqual([expect.objectContaining({ key: 'k1', after: ['k2'] })])

    ;(await act(wrapper, 0, 'Hide')).click()
    await flushPromises()

    expect(client.post).toHaveBeenLastCalledWith(
      '/api/cms/blocks/content/page/12/edit',
      expect.objectContaining({ ops: [{ op: 'hide', key: 'k1' }] }),
    )
    expect(sent(parent, 'remove')).toEqual([
      { wxBar: 1, type: 'remove', key: 'k1', scope: 'page:12' },
    ])
  })

  it('deletes a block only once it is confirmed', async () => {
    const { wrapper, client } = await open(undefined, {
      edited: () => ({ revision: 'r2', key: 'k1', html: '', steps: [{ do: 'remove', key: 'k1' }] }),
    })
    const edits = () => client.post.mock.calls.filter(([url]) => String(url).endsWith('/edit'))

    ;(await act(wrapper, 0, 'Delete')).click()
    await flushPromises()

    expect(document.querySelector('.wx-confirm__message')?.textContent).toContain(
      'It goes from the draft',
    )
    const cancel = [...document.querySelectorAll<HTMLButtonElement>('.wx-dialog__foot button')][0]!
    cancel.click()
    await flushPromises()
    expect(edits()).toHaveLength(0)

    ;(await act(wrapper, 0, 'Delete')).click()
    await flushPromises()
    const buttons = [...document.querySelectorAll<HTMLButtonElement>('.wx-dialog__foot button')]
    buttons[buttons.length - 1]!.click()
    await flushPromises()

    expect(edits()).toHaveLength(1)
    expect(edits()[0]![1]).toEqual(expect.objectContaining({ ops: [{ op: 'remove', key: 'k1' }] }))
  })

  it('offers the actions over the form of a block too', async () => {
    const { wrapper } = await open()

    await row(wrapper, 0).trigger('click')
    await flushPromises()

    await wrapper.get('.wx-blocks-bar__name .wx-actions__menu button').trigger('click')
    await flushPromises()

    const labels = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')].map((item) =>
      item.textContent?.trim(),
    )
    expect(labels).toEqual(expect.arrayContaining(['Add below', 'Duplicate', 'Hide', 'Delete']))
  })
})
