import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import BlockImportDialog from './BlockImportDialog.vue'
import BlockExportDialog from './BlockExportDialog.vue'

function context(http: Record<string, unknown>) {
  const i18n = createI18n()
  const admin = {
    apiPath: '/api/cms',
    http,
    i18n,
    state: { manifest: { modules: [{ id: 'blocks', meta: {} }] } },
    can: () => true,
  } as unknown as AdminContext

  return { global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } } }
}

const pack = {
  format: 'webx-blocks',
  format_version: 1,
  blocks: [{ slug: 'badge' }, { slug: 'hero' }],
}

function row(slug: string, status: string, extra: Record<string, unknown> = {}) {
  return {
    slug,
    title: slug,
    kind: 'block',
    name: 'pack.json',
    status,
    writes: status !== 'unchanged',
    version: null,
    published: null,
    from: null,
    draft: false,
    diff: null,
    error: null,
    ...extra,
  }
}

/* The picker is a hidden input: the test hands it a file the way the browser would. */
async function choose(contents: string): Promise<void> {
  const input = document.body.querySelector<HTMLInputElement>('input[type=file]')!
  const file = new File([contents], 'pack.json', { type: 'application/json' })

  Object.defineProperty(input, 'files', { value: [file], configurable: true })
  input.dispatchEvent(new Event('change'))
  await flushPromises()
}

function button(text: string): HTMLButtonElement {
  return [...document.body.querySelectorAll('button')].find(
    (one) => one.textContent?.trim() === text,
  )!
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('WxBlockImportDialog', () => {
  it('shows what the file would do before it writes anything, then imports', async () => {
    const post = vi
      .fn()
      .mockResolvedValueOnce({ data: [row('badge', 'created'), row('hero', 'unchanged')] })
      .mockResolvedValueOnce({ data: [row('badge', 'created'), row('hero', 'unchanged')] })

    mount(BlockImportDialog, { attachTo: document.body, ...context({ post }) })
    await flushPromises()
    await choose(JSON.stringify(pack))

    expect(post).toHaveBeenCalledWith('/api/cms/blocks/import', {
      file: JSON.stringify(pack),
      name: 'pack.json',
      dry_run: true,
      publish: false,
      replace: [],
    })
    expect(document.body.textContent).toContain('New')
    expect(document.body.textContent).toContain('Unchanged')

    button('Import').click()
    await flushPromises()

    expect(post).toHaveBeenLastCalledWith('/api/cms/blocks/import', {
      file: JSON.stringify(pack),
      name: 'pack.json',
      dry_run: false,
      publish: false,
      replace: [],
    })
  })

  it('runs the publish checks in the plan once publishing is switched on', async () => {
    const post = vi
      .fn()
      .mockResolvedValueOnce({ data: [row('hero', 'created')] })
      .mockResolvedValueOnce({
        data: [row('hero', 'created', { error: 'would not be published — syntax error' })],
      })

    mount(BlockImportDialog, { attachTo: document.body, ...context({ post }) })
    await flushPromises()
    await choose(JSON.stringify(pack))

    document.body.querySelector<HTMLInputElement>('[role=switch]')!.click()
    await flushPromises()

    expect(post).toHaveBeenLastCalledWith('/api/cms/blocks/import', {
      file: JSON.stringify(pack),
      name: 'pack.json',
      dry_run: true,
      publish: true,
      replace: [],
    })
    expect(document.body.textContent).toContain('would not be published')
  })

  /* A type the site has stays the site's: replaced only when its own switch is on. */
  it('keeps a type that differs unless its row is switched to replace', async () => {
    const diff = { fields: { added: ['subtitle'], removed: [], changed: [] }, row: ['title'] }
    const post = vi
      .fn()
      .mockResolvedValueOnce({
        data: [row('badge', 'differs', { diff }), row('hero', 'differs', { diff })],
      })
      .mockResolvedValueOnce({
        data: [row('badge', 'differs', { diff }), row('hero', 'replaced', { diff })],
      })
      .mockResolvedValueOnce({
        data: [row('badge', 'differs', { diff }), row('hero', 'replaced', { diff, version: 2 })],
      })

    mount(BlockImportDialog, { attachTo: document.body, ...context({ post }) })
    await flushPromises()
    await choose(JSON.stringify(pack))

    expect(document.body.textContent).toContain('Differs, kept')
    expect(document.body.textContent).toContain('+ subtitle')
    expect(document.body.textContent).not.toContain('Select all')
    expect(button('Import').disabled).toBe(true)

    // The switches: one per differing row, then the one to publish.
    const switches = document.body.querySelectorAll<HTMLInputElement>('[role=switch]')
    expect(switches).toHaveLength(3)
    switches[1]!.click()
    await flushPromises()

    expect(post).toHaveBeenLastCalledWith('/api/cms/blocks/import', {
      file: JSON.stringify(pack),
      name: 'pack.json',
      dry_run: true,
      publish: false,
      replace: ['hero'],
    })
    expect(button('Import').disabled).toBe(false)

    button('Import').click()
    await flushPromises()

    expect(post).toHaveBeenLastCalledWith('/api/cms/blocks/import', {
      file: JSON.stringify(pack),
      name: 'pack.json',
      dry_run: false,
      publish: false,
      replace: ['hero'],
    })
  })

  it('says a file that is not JSON is not, without asking the server', async () => {
    const post = vi.fn()

    mount(BlockImportDialog, { attachTo: document.body, ...context({ post }) })
    await flushPromises()
    await choose('{nope')

    expect(post).not.toHaveBeenCalled()
    expect(document.body.textContent).toContain('This file is not JSON.')
  })

  it('has nothing to import when everything is already here', async () => {
    const post = vi.fn().mockResolvedValue({ data: [row('hero', 'unchanged')] })

    mount(BlockImportDialog, { attachTo: document.body, ...context({ post }) })
    await flushPromises()
    await choose(JSON.stringify(pack))

    expect(document.body.textContent).toContain('already here')
    expect(button('Import').disabled).toBe(true)
  })
})

describe('WxBlockExportDialog', () => {
  /* Opened from a type that was never published, the draft is all there is to take. */
  it('comes with the type ticked, and takes its draft when it was never published', async () => {
    const get = vi.fn().mockResolvedValue({
      data: [
        {
          id: 1,
          slug: 'hero',
          title: 'Hero',
          kind: 'block',
          draft: { number: 2 },
          published: null,
        },
        {
          id: 2,
          slug: 'quote',
          title: 'Quote',
          kind: 'block',
          draft: null,
          published: { number: 1 },
        },
      ],
    })

    mount(BlockExportDialog, {
      attachTo: document.body,
      props: { selected: ['hero'] },
      ...context({ get }),
    })
    await flushPromises()

    const boxes = [...document.body.querySelectorAll<HTMLInputElement>('input[type=checkbox]')]
    const ticked = boxes.filter((box) => box.checked).length

    // "Select all" is not, the hero is; the switch for drafts is on.
    expect(ticked).toBeGreaterThanOrEqual(2)
    expect(document.body.textContent).toContain('v2')
  })
})
