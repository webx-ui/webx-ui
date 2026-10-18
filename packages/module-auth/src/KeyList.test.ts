import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import KeyDialog from './KeyDialog.vue'
import KeyList from './KeyList.vue'
import type { McpKey } from './types'

function key(row: Partial<McpKey> & { id: number }): McpKey {
  return {
    name: 'queue watcher',
    user: { id: 2, name: 'Robot Watcher' },
    created_by: { id: 1, name: 'Anna Kovalchuk' },
    scopes: ['tasks:read', 'tasks:review'],
    everything: false,
    state: 'live',
    created_at: '2026-10-15T09:00:00+00:00',
    expires_at: '2036-10-13T09:00:00+00:00',
    last_used_at: null,
    revoked_at: null,
    ...row,
  }
}

function context(rows: McpKey[], user: { id: number; name: string } | null = null) {
  // Robot (2) may read and review tasks; the other one (3) only read them.
  const usable: Record<number, string[]> = {
    2: ['mcp:use', 'tasks:read', 'tasks:review'],
    3: ['mcp:use', 'tasks:read'],
  }

  const get = vi.fn().mockImplementation((path: string, options?: { query?: { admin?: number } }) =>
    Promise.resolve(
      path.endsWith('/auth/keys/scopes')
        ? { data: usable[options?.query?.admin ?? 2] ?? [] }
        : path.endsWith('/auth/admins')
          ? {
              data: [{ id: 2, name: 'Robot Watcher', email: 'robot@example.test' }],
              meta: { total: 1 },
            }
          : {
              data: rows,
              meta: { scopes: ['mcp:use', 'tasks:read', 'tasks:review'], max_days: 3650 },
            },
    ),
  )

  const post = vi.fn().mockResolvedValue({ data: key({ id: 9 }), token: 'secret-once' })

  const remove = vi.fn().mockImplementation((path: string) => {
    const id = Number(path.split('/').pop())
    const found = rows.find((one) => one.id === id)

    return Promise.resolve({
      data: { ...found, state: 'revoked', revoked_at: '2026-10-15T10:00:00+00:00' },
    })
  })

  // The real dictionary: the package's own English is what a panel sees before the server's
  // translations arrive, and a screen that shows keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, delete: remove },
    i18n,
    state: { manifest: null, user, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  return {
    get,
    post,
    remove,
    global: {
      plugins: [WebxUI],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
    },
  }
}

/**
 * What the list and the dialog ask for and say. Nothing about how they look: jsdom computes no
 * layout, so the cards below 560 and the greyed rows are checked in a browser.
 */
describe('WxKeyList', () => {
  it('says what each key is, whom it acts as and what it may reach', async () => {
    const { get, global } = context([
      key({ id: 1 }),
      key({ id: 2, name: 'deploy', everything: true, scopes: ['mcp:use'], created_by: null }),
    ])
    const wrapper = mount(KeyList, { global })

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/auth/keys')

    const text = wrapper.text()

    expect(text).toContain('queue watcher')
    expect(text).toContain('Robot Watcher')
    // The scopes as issued: they are the words an agent's refusal quotes.
    expect(text).toContain('tasks:read, tasks:review')
    // `mcp:use` is a word only the code knows; the reader gets whose permissions it means.
    expect(text).toContain('Everything Robot can do')
    expect(text).not.toContain('mcp:use')
    expect(text).toContain('Issued by Anna Kovalchuk')
    expect(text).toContain('Issued in a terminal')
  })

  it('offers nothing to press on a key that was revoked or ran out', async () => {
    const { global } = context([
      key({ id: 1, state: 'revoked', revoked_at: '2026-10-14T12:00:00+00:00' }),
      key({ id: 2, state: 'expired', expires_at: '2026-10-01T00:00:00+00:00' }),
    ])
    const wrapper = mount(KeyList, { global })

    await flushPromises()

    expect(wrapper.text()).toContain('Revoked')
    expect(wrapper.text()).toContain('Expired')
    expect(wrapper.findAll('button').some((one) => one.text() === 'Revoke')).toBe(false)
  })

  it('revokes a key and keeps the row, because the call log points at it', async () => {
    const { remove, global } = context([key({ id: 4 })])
    const wrapper = mount(KeyList, { global })

    await flushPromises()

    await wrapper
      .findAll('button')
      .find((one) => one.text() === 'Revoke')!
      .trigger('click')

    // `confirm` opens outside this tree; the dialog's own button is what answers it.
    const agree = [...document.querySelectorAll('button')].find(
      (one) => one.textContent?.trim() === 'Revoke' && !wrapper.element.contains(one),
    )

    expect(agree).toBeDefined()
    agree!.click()
    await flushPromises()

    expect(remove).toHaveBeenCalledWith('/api/cms/auth/keys/4')
    expect(wrapper.text()).toContain('queue watcher')
    expect(wrapper.text()).toContain('Revoked')
  })
})

describe('your own keys', () => {
  it('lists only your own, even for a manager, and does not say whose they are', async () => {
    const { get, global } = context([key({ id: 1 })])
    const wrapper = mount(KeyList, { global, props: { mine: true } })

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/auth/keys', { query: { mine: 1 } })
    expect(wrapper.text()).toContain('queue watcher')
    expect(wrapper.text()).not.toContain('Robot Watcher')
  })

  it('issues a key for yourself without choosing or looking up anybody', async () => {
    // An administrator without admins.view could not list people: the dialog must not try.
    const { get, post, global } = context([], { id: 2, name: 'Robot Watcher' })
    const wrapper = mount(KeyDialog, { global, props: { mine: true }, attachTo: document.body })

    await flushPromises()

    expect(get).not.toHaveBeenCalledWith('/api/cms/auth/admins', expect.anything())
    expect(get).toHaveBeenCalledWith('/api/cms/auth/keys/scopes', { query: { admin: undefined } })
    expect(document.querySelector('[name="admin"]')).toBeNull()

    const vm = wrapper.vm as unknown as {
      form: { name: string; access: string; scopes: string[] }
      scopeOptions: { value: string }[]
      issue: () => Promise<void>
    }

    expect(vm.scopeOptions.map((one) => one.value)).toEqual(['tasks:read', 'tasks:review'])

    vm.form.name = 'my script'
    await vm.issue()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/auth/keys', {
      name: 'my script',
      admin: 2,
      scopes: [],
      days: 365,
    })

    wrapper.unmount()
  })
})

describe('the key dialog', () => {
  it('issues a key for the chosen administrator, for as long as chosen, and shows it once', async () => {
    const { post, global } = context([])
    const wrapper = mount(KeyDialog, { global, attachTo: document.body })

    await flushPromises()

    const vm = wrapper.vm as unknown as {
      form: { name: string; admin: number | null; access: string; scopes: string[]; days: number }
      issue: () => Promise<void>
    }

    vm.form.name = 'queue watcher'
    vm.form.admin = 2
    vm.form.access = 'chosen'
    vm.form.scopes = ['tasks:read', 'tasks:review']
    vm.form.days = 3650
    await vm.issue()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/auth/keys', {
      name: 'queue watcher',
      admin: 2,
      scopes: ['tasks:read', 'tasks:review'],
      days: 3650,
    })

    const secret = [...document.querySelectorAll('input')].find(
      (one) => one.value === 'secret-once',
    )

    expect(secret).toBeDefined()
    expect(document.body.textContent).toContain('Copy the key now')

    wrapper.unmount()
  })

  it('offers only the scopes of the owner, and drops the ones the next owner cannot use', async () => {
    const { get, global } = context([])
    const wrapper = mount(KeyDialog, { global, attachTo: document.body })

    await flushPromises()

    const vm = wrapper.vm as unknown as {
      form: { admin: number | null; scopes: string[] }
      scopeOptions: { value: string }[]
    }

    expect(vm.scopeOptions).toEqual([])

    vm.form.admin = 2
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/auth/keys/scopes', { query: { admin: 2 } })
    expect(vm.scopeOptions.map((one) => one.value)).toEqual(['tasks:read', 'tasks:review'])

    vm.form.scopes = ['tasks:read', 'tasks:review']
    vm.form.admin = 3
    await flushPromises()

    expect(vm.scopeOptions.map((one) => one.value)).toEqual(['tasks:read'])
    expect(vm.form.scopes).toEqual(['tasks:read'])

    wrapper.unmount()
  })

  it('asks for a scope rather than issuing a key that may do nothing', async () => {
    const { post, global } = context([])
    const wrapper = mount(KeyDialog, { global, attachTo: document.body })

    await flushPromises()

    const vm = wrapper.vm as unknown as {
      form: { name: string; admin: number | null; access: string; scopes: string[] }
      issue: () => Promise<void>
    }

    vm.form.name = 'reader'
    vm.form.admin = 2
    vm.form.access = 'chosen'
    await vm.issue()

    expect(post).not.toHaveBeenCalled()

    wrapper.unmount()
  })
})
