import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import NotesList from './NotesList.vue'
import { adminKey, type AdminContext } from './admin'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'
import type { NoteEntry } from './notes'

function note(over: Partial<NoteEntry> & { id: number | string }): NoteEntry {
  return {
    body: 'Rang back, no answer.',
    author: { id: 1, name: 'Ada' },
    created_at: '2026-09-18T08:10:00Z',
    ...over,
  }
}

function list(props: Record<string, unknown>) {
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    assetUrls: null,
  } as unknown as AdminContext

  return mount(NotesList, {
    props: { notes: [], ...props },
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })
}

/**
 * The feed without a store: what it is worth testing is that it never asks a server anything —
 * it says what was typed and the section answers — and that the menu of a note appears only where
 * notes change at all.
 */
describe('WxNotesList', () => {
  it('draws each note with its author and keeps the line breaks as typed', () => {
    const wrapper = list({
      notes: [note({ id: 'a', body: 'First line\nsecond line' }), note({ id: 'b' })],
    })

    const notes = wrapper.findAll('.wx-note')
    expect(notes).toHaveLength(2)
    expect(notes[0]!.text()).toContain('Ada')
    expect(notes[0]!.find('.wx-note__text').element.textContent).toBe('First line\nsecond line')
  })

  it('says there are none yet, and the box is still there to write the first', () => {
    const wrapper = list({ notes: [] })

    expect(wrapper.text()).toContain('No notes yet.')
    expect(wrapper.find('.wx-notes__new textarea').exists()).toBe(true)
  })

  it('hands the typed note out trimmed, and leaves emptying the box to the section', async () => {
    const wrapper = list({ notes: [], draft: '  Called, will pay tomorrow.  ' })

    await wrapper.find('.wx-notes__new .wx-button').trigger('click')

    expect(wrapper.emitted('add')).toEqual([['Called, will pay tomorrow.']])
    expect(wrapper.find('.wx-notes__new textarea').element.value).toBe(
      '  Called, will pay tomorrow.  ',
    )
  })

  it('sends nothing while a note is on its way or the box is blank', async () => {
    const blank = list({ notes: [], draft: '   ' })
    await blank.find('.wx-notes__new .wx-button').trigger('click')
    expect(blank.emitted('add')).toBeUndefined()

    const busy = list({ notes: [], draft: 'Twice', sending: true })
    await busy.find('.wx-notes__new .wx-button').trigger('click')
    expect(busy.emitted('add')).toBeUndefined()
  })

  it('draws no box for a reader who may not write', () => {
    const wrapper = list({ notes: [note({ id: 1 })], can: false })

    expect(wrapper.find('.wx-notes__new').exists()).toBe(false)
    expect(wrapper.findAll('.wx-note')).toHaveLength(1)
  })

  it('offers no menu where a note never changes, even to its author', async () => {
    const wrapper = list({ notes: [note({ id: 1, is_mine: true })] })
    await flushPromises()

    expect(wrapper.find('.wx-note__head .wx-row-menu').exists()).toBe(false)
    expect(wrapper.find('.wx-note__head button').exists()).toBe(false)
  })

  it('stops the box at the longest note the store takes', () => {
    const wrapper = list({ notes: [], maxlength: 1000 })

    expect(wrapper.find('.wx-notes__new textarea').attributes('maxlength')).toBe('1000')
  })
})
