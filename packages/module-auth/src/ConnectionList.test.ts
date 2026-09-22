import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import ConnectionList from './ConnectionList.vue'
import type { Connection, ConnectionScope } from './types'

function connection(row: Partial<Connection> & { id: number }): Connection {
  return {
    client: 'Claude',
    host: 'claude.ai',
    read_only: false,
    user: { id: 1, name: 'Anna Kovalchuk' },
    connected_at: '2026-09-07T11:30:00+00:00',
    last_used_at: '2026-09-21T09:16:00+00:00',
    revoked_at: null,
    ...row,
  }
}

function panel(rows: Connection[], scope: ConnectionScope = 'mine') {
  const get = vi.fn().mockResolvedValue({
    data: rows,
    meta: { scope, can_see_everybody: scope === 'all' },
  })

  const remove = vi.fn().mockImplementation((path: string) => {
    const id = Number(path.split('/').pop())
    const found = rows.find((one) => one.id === id)

    return Promise.resolve({ data: { ...found, revoked_at: '2026-09-21T10:00:00+00:00' } })
  })

  // The real dictionary: the package's own English is what a panel sees before the server's
  // translations arrive, and a screen that shows keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, delete: remove },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  return {
    get,
    remove,
    wrapper: mount(ConnectionList, {
      props: { scope },
      global: {
        plugins: [WebxUI],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

/**
 * What the list asks for and what it says about a row. Nothing about how it looks: jsdom
 * computes no layout, so the cards below 560 and the greyed row are checked in a browser.
 */
describe('WxConnectionList', () => {
  it('asks for this person alone, and says the client, the return address and the terms', async () => {
    const { get, wrapper } = panel([connection({ id: 1 })])

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/auth/connections', { query: { all: undefined } })

    const text = wrapper.text()

    expect(text).toContain('Claude')
    // The name is the client's own choice; the address is what a reader checks it against.
    expect(text).toContain('claude.ai')
    expect(text).toContain('Everything you can do')
  })

  it('asks for everybody when that is the list, and then names whose each one is', async () => {
    const { get, wrapper } = panel(
      [connection({ id: 1, user: { id: 2, name: 'Dmytro Levchenko' } })],
      'all',
    )

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/auth/connections', { query: { all: 1 } })

    const text = wrapper.text()

    expect(text).toContain('Dmytro Levchenko')
    // The given name in the badge, because the column beside it already says who, in full.
    expect(text).toContain('Everything Dmytro can do')
  })

  it('says read-only rather than what the person can do, because the switch outranks that', async () => {
    const { wrapper } = panel([connection({ id: 1, read_only: true })])

    await flushPromises()

    expect(wrapper.text()).toContain('Reading only')
    expect(wrapper.text()).not.toContain('Everything you can do')
  })

  it('offers nothing to press on a connection that was already ended', async () => {
    const { wrapper } = panel([
      connection({ id: 1, revoked_at: '2026-09-14T12:00:00+00:00', last_used_at: null }),
    ])

    await flushPromises()

    expect(wrapper.text()).toContain('Disconnected')
    expect(wrapper.text()).toContain('Never')
    expect(wrapper.findAll('button').some((one) => one.text() === 'Disconnect')).toBe(false)
  })

  it('ends a connection and keeps the row, because the call log points at it', async () => {
    const { remove, wrapper } = panel([connection({ id: 4 })])

    await flushPromises()

    const button = wrapper.findAll('button').find((one) => one.text() === 'Disconnect')

    expect(button).toBeDefined()
    await button!.trigger('click')

    // `confirm` opens outside this tree; the dialog's own button is what answers it.
    const agree = [...document.querySelectorAll('button')].find(
      (one) => one.textContent?.trim() === 'Disconnect' && !wrapper.element.contains(one),
    )

    expect(agree).toBeDefined()
    agree!.click()
    await flushPromises()

    expect(remove).toHaveBeenCalledWith('/api/cms/auth/connections/4')
    expect(wrapper.text()).toContain('Claude')
    expect(wrapper.text()).toContain('Disconnected')
  })
})
