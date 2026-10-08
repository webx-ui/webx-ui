import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import type * as core from '@webx-ui/core'
import { confirm, localesKey } from '@webx-ui/core'
import { adminKey, type AdminContext } from './admin'
import { useEditing, type Editing, type EditingEvent, type EditingOptions } from './editing'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'

// The question is what is under test, not the dialog that asks it.
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof core>()),
  confirm: vi.fn().mockResolvedValue(false),
}))

type Values = { title: { en: string }; blocks: unknown[] }

const values = (title: string, eyebrow = 'Hi'): Values => ({
  title: { en: title },
  blocks: [{ key: 'k1', type: 'hero', values: { eyebrow: { en: eyebrow } } }],
})

const owner = { author: 'Owner', author_id: 7, source: 'panel', at: null }

/** What the next heartbeat answers. */
let ping: Record<string, unknown>

function answer(over: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    revision: 'r1',
    changed: null,
    editors: [],
    state: {
      status: 'published',
      has_draft: false,
      published_at: null,
      trashed: false,
      deleted_at: null,
    },
    place: null,
    events: [],
    heartbeat: 20,
    ...over,
  }
}

function event(id: number, kind: string, detail: Record<string, unknown> | null = null) {
  return { id, kind, ...owner, at: '2026-10-08T16:40:00+00:00', detail } as EditingEvent
}

/**
 * An editor reduced to what the kit needs: a form, a revision, a read. `server` is what the read
 * answers; the test moves it the way another person's save would.
 */
async function host(
  over: Partial<EditingOptions<Values>> = {},
  server = { values: values('About'), revision: 'r1' },
) {
  const form = ref<Values>(values(''))
  const revision = ref('')
  const read = vi.fn(() =>
    Promise.resolve({ values: structuredClone(server.values), revision: server.revision }),
  )
  const refresh = vi.fn()
  const post = vi.fn(() => Promise.resolve({ data: ping }))
  let editing!: Editing<Values>

  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    http: { post, get: vi.fn(), delete: vi.fn(() => Promise.resolve()) },
    i18n,
    state: { user: { id: 3 } },
    loadScreen: () =>
      Promise.resolve([{ id: 'title', type: 'wx-input', name: 'title', label: 'Page title' }]),
    blockLabels: () =>
      Promise.resolve({
        type: (slug: string) => (slug === 'hero' ? 'Hero' : undefined),
        field: (slug: string, name: string) =>
          slug === 'hero' && name === 'eyebrow' ? 'Line above the heading' : undefined,
      }),
  } as unknown as AdminContext

  const id = ref<number | null>(over.id ? null : 2)

  const Host = defineComponent({
    setup() {
      editing = useEditing<Values>({
        entity: 'pages',
        id: () => id.value,
        values: form,
        revision,
        read,
        refresh,
        screen: 'pages.form',
        ...over,
      })

      return () => h('div')
    },
  })

  const wrapper = mount(Host, {
    global: {
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: i18n,
        [localesKey as symbol]: { list: ref([{ code: 'en' }]), active: ref('en') },
      },
    },
  })

  /** The editor's own load: the form, the revision, and «this is what I opened». */
  function open(): void {
    form.value = structuredClone(server.values)
    revision.value = server.revision
    editing.opened(structuredClone(server.values))
  }

  await flushPromises()

  return { wrapper, editing: () => editing, form, revision, read, refresh, post, open, server, id }
}

afterEach(() => {
  vi.useRealTimers()
  vi.mocked(confirm).mockReset().mockResolvedValue(false)
})

describe('useEditing', () => {
  it('opened after its first heartbeat, still hears the next save — the heartbeat waits for the load', async () => {
    vi.useFakeTimers()
    ping = answer({ revision: 'r1' })

    // The record is known before its values are: the first heartbeat is answered with the form
    // still empty and no revision held.
    const editor = await host()

    expect(editor.post).toHaveBeenCalled()
    expect(editor.read).not.toHaveBeenCalled()
    expect(editor.editing().incoming.value).toBeNull()

    editor.open()
    await flushPromises()

    // Somebody writes; the next heartbeat hears it and the notice is offered.
    editor.server.values = values('About', 'Hello there')
    editor.server.revision = 'r2'
    ping = answer({ revision: 'r2', changed: owner })

    await vi.advanceTimersByTimeAsync(20_000)
    await flushPromises()

    const incoming = editor.editing().incoming.value

    expect(incoming?.theirs.revision).toBe('r2')
    expect(editor.editing().places(incoming!.paths)).toBe('Hero › Line above the heading · EN')
  })

  it('opened on a record that already had a draft, offers the next save as a notice', async () => {
    vi.useFakeTimers()
    ping = answer({ revision: 'r5', state: { ...answer().state!, has_draft: true } })

    const editor = await host({}, { values: values('Draft title'), revision: 'r5' })

    editor.open()
    await flushPromises()

    editor.server.values = values('Draft title', 'Changed by an agent')
    editor.server.revision = 'r6'
    ping = answer({ revision: 'r6', changed: { ...owner, source: 'mcp' } })

    await vi.advanceTimersByTimeAsync(20_000)
    await flushPromises()

    expect(editor.editing().incoming.value?.theirs.revision).toBe('r6')
  })

  it('names the field by the screen and the block type, not by its key', async () => {
    const editor = await host()

    await flushPromises()

    expect(editor.editing().label([{ field: 'title' }, { field: 'en' }])).toBe('Page title · EN')
    expect(
      editor
        .editing()
        .label([
          { field: 'blocks' },
          { block: 'k1', type: 'hero' },
          { field: 'values' },
          { field: 'eyebrow' },
        ]),
    ).toBe('Hero › Line above the heading')
  })

  describe('before publishing', () => {
    it('publishes what it holds when nobody wrote since', async () => {
      ping = answer()
      const editor = await host()
      editor.open()

      expect(await editor.editing().beforePublish()).toEqual({ revision: 'r1', asked: false })
      expect(confirm).not.toHaveBeenCalled()
    })

    it('says whose changes are in the draft and publishes with them when told to', async () => {
      ping = answer()
      const editor = await host()
      editor.open()

      editor.server.values = values('About', 'Their eyebrow')
      editor.server.revision = 'r2'
      ping = answer({ revision: 'r2', changed: owner })
      await editor.editing().check()
      vi.mocked(confirm).mockResolvedValueOnce(true)

      const held = await editor.editing().beforePublish()
      const asked = vi.mocked(confirm).mock.calls[0]![0] as { title: string; message: string }

      expect(asked.title).toBe('Publish changes you have not seen?')
      expect(asked.message).toContain('Owner changed Hero › Line above the heading · EN')
      expect(held).toEqual({ revision: 'r2', asked: true })
    })

    it('pulls them into the form instead when the person wants to look first', async () => {
      ping = answer()
      const editor = await host()
      editor.open()

      editor.server.values = values('About', 'Their eyebrow')
      editor.server.revision = 'r2'

      expect(await editor.editing().beforePublish()).toBeNull()
      expect(editor.form.value.blocks).toEqual(values('About', 'Their eyebrow').blocks)
      expect(editor.revision.value).toBe('r2')
    })
  })

  it('catches up when somebody else published, and says so', async () => {
    ping = answer({ state: { ...answer().state!, status: 'modified', has_draft: true } })
    const editor = await host()
    editor.open()
    await flushPromises()

    ping = answer({ events: [event(10, 'published')] })
    await editor.editing().check()

    expect(editor.refresh).toHaveBeenCalled()
    expect(editor.editing().events.value).toHaveLength(1)
    expect(editor.editing().describe(editor.editing().events.value)).toMatch(/^Owner published · /)
  })

  it('says a version put back and published in one sentence, not as a list of fields', async () => {
    ping = answer()
    const editor = await host()
    editor.open()
    await flushPromises()

    ping = answer({
      events: [event(11, 'restored_version', { number: 21 }), event(12, 'published')],
    })
    await editor.editing().check()

    expect(editor.editing().describe(editor.editing().events.value)).toMatch(
      /^Owner restored version 21 and published · /,
    )
  })

  it('leaves out what this editor did itself, but not the same person through an agent', async () => {
    ping = answer()
    const editor = await host()
    editor.open()
    await flushPromises()

    const mine = { ...event(20, 'published'), author_id: 3 }
    const agent = { ...event(21, 'moved'), author_id: 3, source: 'mcp' }

    ping = answer({ events: [mine, agent] })
    await editor.editing().check()

    expect(editor.editing().events.value.map((one) => one.id)).toEqual([21])
  })

  it('says where a move took the record and reads the trail again', async () => {
    ping = answer({ place: { parent_id: 1, paths: { en: '/concurrency-test' } } })
    const editor = await host()
    editor.open()
    await flushPromises()

    ping = answer({
      place: { parent_id: 5, paths: { en: '/contact/concurrency-test' } },
      events: [event(30, 'moved')],
    })
    await editor.editing().check()

    expect(editor.refresh).toHaveBeenCalled()
    expect(editor.editing().describe(editor.editing().events.value)).toContain(
      'moved it to /contact/concurrency-test',
    )
  })

  it('in the bin: says who put it there, keeps the form and takes it out on request', async () => {
    ping = answer()
    const restore = vi.fn(() => Promise.resolve())
    const editor = await host({ restore })
    editor.open()
    await flushPromises()

    editor.form.value = values('My unsaved title')
    ping = answer({
      state: { ...answer().state!, status: 'trashed', trashed: true, deleted_at: null },
      events: [event(40, 'trashed')],
    })

    // The save that found it gone hands over to the notice instead of a toast of its own.
    expect(await editor.editing().failed({ status: 404 })).toBe(true)
    expect(editor.editing().trashed.value).toBe(true)
    expect(editor.editing().trashedBy.value?.author).toBe('Owner')
    expect(editor.editing().unsaved.value).toBe(true)
    expect(editor.form.value.title.en).toBe('My unsaved title')

    ping = answer()
    expect(await editor.editing().restoreFromBin()).toBe(true)
    expect(restore).toHaveBeenCalled()
    expect(editor.editing().trashed.value).toBe(false)
  })

  it('a publication refused for a stale revision is the notice’s to explain', async () => {
    ping = answer()
    const editor = await host()
    editor.open()

    expect(
      await editor.editing().failed({ status: 409, body: { message: 'Moved', revision: 'r9' } }),
    ).toBe(true)
    expect(await editor.editing().failed({ status: 500 })).toBe(false)
  })
})
