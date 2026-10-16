import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { WebxUI } from '@webx-ui/core'
import CachePage from './CachePage.vue'
import type { CacheReport } from './api'
import { adminKey, type AdminContext } from '../admin'
import { createI18n, i18nKey } from '../i18n'
import { adminMessages } from '../messages'

vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<Record<string, unknown>>()),
  // The question is the dialog's to ask; here the answer is yes.
  confirm: vi.fn(async () => true),
}))

function report(overrides: Partial<CacheReport> = {}): CacheReport {
  return {
    driver: 'file',
    store: 'file',
    enabled: true,
    ttl: 86400,
    groups: [
      { id: 'locales', title: 'Languages', version: 4120033001, models: ['Locale'] },
      { id: 'menu', title: 'Menu', version: 7731000210, models: ['Menu', 'MenuItem'] },
      { id: 'theme', title: 'Theme', version: 5100002000, models: [] },
    ],
    ...overrides,
  }
}

const mounted: { unmount(): void }[] = []

afterEach(() => {
  mounted.splice(0).forEach((wrapper) => wrapper.unmount())
})

function panel(answer: CacheReport, options: { reset?: boolean } = {}) {
  const get = vi.fn((url: string) =>
    url.includes('/history/')
      ? Promise.resolve({ data: [], current_page: 1, last_page: 1, total: 0 })
      : Promise.resolve({ data: answer }),
  )
  const post = vi.fn((_url: string, body: { group?: string }) => {
    const moved = body.group === undefined ? answer.groups.map((group) => group.id) : [body.group]

    // Each reset moves on from the last: the versions add up the way the server's do.
    answer = {
      ...answer,
      groups: answer.groups.map((group) =>
        moved.includes(group.id) ? { ...group, version: group.version + 1 } : group,
      ),
    }

    return Promise.resolve({ data: { ...answer, reset: moved } })
  })
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)
  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post },
    i18n,
    state: { manifest: { modules: [] }, user: null, status: 'ready', error: null },
    can: (permission: string) => permission !== 'cache.reset' || options.reset !== false,
  } as unknown as AdminContext

  const wrapper = mount(CachePage, {
    attachTo: document.body,
    global: {
      plugins: [WebxUI],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      stubs: { RouterLink: true },
    },
  })
  mounted.push(wrapper)

  return { wrapper, get, post }
}

const button = (wrapper: ReturnType<typeof panel>['wrapper'], label: string) =>
  wrapper.findAll('button').find((one) => one.text() === label)

describe('WxCachePage', () => {
  it('shows the store, every group with what resets it, and the journal of resets', async () => {
    const { wrapper, get } = panel(report())

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/cache')
    expect(get).toHaveBeenCalledWith('/api/cms/history/cache/0', expect.anything())
    expect(wrapper.text()).toContain('file')
    expect(wrapper.text()).toContain('24 h')
    expect(wrapper.text()).toContain('Languages')
    expect(wrapper.text()).toContain('MenuItem')
    expect(wrapper.text()).toContain('7731000210')
    expect(wrapper.text()).toContain('Only by hand')
    expect(wrapper.text()).toContain('Resets')
    expect(wrapper.text()).not.toContain('Nothing survives the request')
  })

  it('warns about a store that keeps nothing', async () => {
    const { wrapper } = panel(report({ driver: 'array', store: 'array' }))

    await flushPromises()

    expect(wrapper.text()).toContain('Nothing survives the request')
  })

  it('resets everything from the head and one group from its row', async () => {
    const { wrapper, post } = panel(report())

    await flushPromises()

    await button(wrapper, 'Reset the site cache')!.trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/cache/reset', {})
    expect(wrapper.text()).toContain('7731000211')

    // The second row's menu: «Menu», with one line — «Reset». The head has a menu of its own.
    const menus = wrapper.findAll('.wx-table .wx-actions__menu button')
    expect(menus, 'every group has a menu with «Reset»').toHaveLength(3)
    await menus[1]!.trigger('click')
    await flushPromises()

    const reset = document.querySelector<HTMLButtonElement>('.wx-dropdown-item')
    expect(reset?.textContent).toContain('Reset')
    reset!.click()
    await flushPromises()

    expect(post).toHaveBeenLastCalledWith('/api/cms/cache/reset', { group: 'menu' })
    expect(wrapper.text()).toContain('7731000212')
  })

  it('offers no reset to somebody who may only look', async () => {
    const { wrapper } = panel(report(), { reset: false })

    await flushPromises()

    expect(button(wrapper, 'Reset the site cache')).toBeUndefined()
    expect(wrapper.find('.wx-table .wx-actions__menu').exists()).toBe(false)
  })
})
