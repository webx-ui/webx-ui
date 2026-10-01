import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import SearchIndexPage from './SearchIndexPage.vue'
import type { IndexReport, RebuildProgress } from './types'

vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<Record<string, unknown>>()),
  // The question is the dialog's to ask; here the answer is yes.
  confirm: vi.fn(async () => true),
}))

const idle: RebuildProgress = {
  state: 'idle',
  done: 0,
  total: 0,
  queued_at: null,
  started_at: null,
  finished_at: null,
  error: null,
  stalled: false,
}

function report(overrides: Partial<IndexReport> = {}): IndexReport {
  return {
    connection: {
      address: '127.0.0.1:9308',
      prefix: 'shop',
      version: '29.0.2',
      available: true,
      error: null,
    },
    products: 120,
    tables: [
      {
        locale: 'en',
        table: 'shop_catalog_products_en',
        state: 'stale',
        reason: 'the column [codes] is missing',
        documents: 118,
        rebuilding: false,
      },
    ],
    queue: { waiting: 2, oldest: '2026-10-01T10:00:00+00:00' },
    rebuild: idle,
    outdated: true,
    ...overrides,
  }
}

function panel(answers: IndexReport[], options: { manage?: boolean } = {}) {
  const get = vi.fn()

  for (const answer of answers) get.mockResolvedValueOnce({ data: answer })
  get.mockResolvedValue({ data: answers.at(-1) })

  const post = vi.fn().mockResolvedValue({ data: { ...idle, state: 'queued' } })
  const i18n = createI18n()
  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post },
    i18n,
    state: { manifest: { modules: [] }, user: null, status: 'ready', error: null },
    can: (permission: string) => permission !== 'search-index.manage' || options.manage !== false,
  } as unknown as AdminContext

  const wrapper = mount(SearchIndexPage, {
    global: {
      plugins: [WebxUI],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      stubs: { RouterLink: true },
    },
  })

  return { wrapper, get, post }
}

const button = (wrapper: ReturnType<typeof panel>['wrapper'], label: string) =>
  wrapper.findAll('button').find((one) => one.text() === label)

afterEach(() => {
  vi.useRealTimers()
})

describe('WxSearchIndexPage', () => {
  it('shows the server, each table against the database, and the queue', async () => {
    const { wrapper, get } = panel([report()])

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/search-index')
    expect(wrapper.text()).toContain('127.0.0.1:9308')
    expect(wrapper.text()).toContain('29.0.2')
    expect(wrapper.text()).toContain('shop_catalog_products_en')
    expect(wrapper.text()).toContain('Schema out of date')
    expect(wrapper.text()).toContain('the column [codes] is missing')
    expect(wrapper.text()).toContain('118')
    expect(wrapper.text()).toContain('120')
    expect(wrapper.text()).toContain('The index is not of the schema the catalogue writes now')
  })

  it('shows how far a rebuild from the console has got, by the table beside', async () => {
    vi.useFakeTimers()
    const [table] = report().tables
    const { wrapper, get } = panel([
      report({ tables: [{ ...table!, rebuilding: true, filled: 40 }] }),
      report({ tables: [{ ...table!, rebuilding: true, filled: 80 }] }),
    ])

    await flushPromises()

    // The console writes no progress of its own: the page counts the table being filled and
    // keeps asking.
    expect(wrapper.text()).toContain('A rebuild is filling the table beside it: 40 of 120')

    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()

    expect(get).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('A rebuild is filling the table beside it: 80 of 120')
  })

  it('queues the rebuild and follows it until it is done', async () => {
    vi.useFakeTimers()
    const running = report({ rebuild: { ...idle, state: 'running', done: 40, total: 120 } })
    const done = report({
      outdated: false,
      tables: [{ ...report().tables[0]!, state: 'ready', reason: null, documents: 120 }],
      rebuild: { ...idle, state: 'done', done: 120, total: 120 },
    })
    const { wrapper, post, get } = panel([report(), running, done])

    await flushPromises()
    await button(wrapper, 'Rebuild')!.trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/search-index/rebuild', {})
    expect(wrapper.text()).toContain('Rebuilding: 40 of 120')
    // While it runs, a second one is not offered.
    expect(button(wrapper, 'Rebuild')!.attributes('disabled')).toBeDefined()

    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()

    expect(get).toHaveBeenCalledTimes(3)
    expect(wrapper.text()).toContain('Rebuilt: 120 products')
    expect(wrapper.text()).toContain('Up to date')
  })

  it('offers no rebuild to somebody who may only look', async () => {
    const { wrapper } = panel([report()], { manage: false })

    await flushPromises()

    expect(button(wrapper, 'Rebuild')).toBeUndefined()
  })

  it('says the server does not answer, and why', async () => {
    const { wrapper } = panel([
      report({
        connection: {
          address: '127.0.0.1:9308',
          prefix: 'shop',
          version: null,
          available: false,
          error: 'Connection refused',
        },
        tables: [],
        outdated: false,
      }),
    ])

    await flushPromises()

    expect(wrapper.text()).toContain('Does not answer')
    expect(wrapper.text()).toContain('Connection refused')
    expect(wrapper.text()).toContain('The server did not say which tables it has.')
  })
})
