import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import NotesFeed from './NotesFeed.vue'
import { adminKey, type AdminContext } from './admin'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'
import type { EntityNote } from './notes'

function note(over: Partial<EntityNote> & { id: number }): EntityNote {
  return {
    body: 'Rang back, no answer.',
    author: { id: 1, name: 'Ada' },
    is_mine: false,
    created_at: '2026-09-18T08:10:00Z',
    updated_at: null,
    ...over,
  }
}

function feed(notes: EntityNote[], can = true) {
  const get = vi.fn().mockResolvedValue({ data: notes })
  const post = vi
    .fn()
    .mockImplementation((_path: string, body: { body: string }) =>
      Promise.resolve({ data: note({ id: 99, body: body.body, is_mine: true }) }),
    )
  const put = vi.fn().mockResolvedValue({ data: note({ id: 1, body: 'Rewritten', is_mine: true }) })
  const remove = vi.fn().mockResolvedValue(undefined)

  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put, delete: remove },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  return {
    get,
    post,
    put,
    remove,
    wrapper: mount(NotesFeed, {
      props: { type: 'inbox_submission', id: 5, can },
      global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
    }),
  }
}

/**
 * The feed the whole panel writes notes in.
 *
 * What is worth a test is the shape of the request — the address is the panel's own and carries
 * an alias, never a class name — and the one rule the feed draws from: a line is edited by
 * whoever wrote it, and the server says which those are.
 */
describe('WxNotes', () => {
  it('reads the feed of one record under its alias', async () => {
    const { wrapper, get } = feed([note({ id: 1 })])

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/entities/inbox_submission/5/notes')
    expect(wrapper.text()).toContain('Rang back, no answer.')
    expect(wrapper.text()).toContain('Ada')
  })

  it('writes one and keeps it without asking again', async () => {
    const { wrapper, post, get } = feed([])

    await flushPromises()

    await wrapper.get('textarea').setValue('Called, will ring tomorrow.')
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/entities/inbox_submission/5/notes', {
      body: 'Called, will ring tomorrow.',
    })
    expect(get).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Called, will ring tomorrow.')
  })

  it('offers a menu only on the reader’s own line', async () => {
    const { wrapper } = feed([note({ id: 1 }), note({ id: 2, is_mine: true })])

    await flushPromises()

    // One menu for two notes: the other one is somebody else's, and a note anybody could
    // rewrite is not a record of anything.
    expect(wrapper.findAll('.wx-actions')).toHaveLength(1)
  })

  it('draws no box for somebody who may not write', async () => {
    const { wrapper } = feed([note({ id: 1 })], false)

    await flushPromises()

    expect(wrapper.find('textarea').exists()).toBe(false)
  })
})
