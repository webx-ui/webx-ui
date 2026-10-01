import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { connectModals } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import ExchangePage from './ExchangePage.vue'
import { createExchangeApi, useRunPolling, type ExchangeRun } from './exchange'
import { runStatus, runTotals } from './exchangeWords'

function run(over: Partial<ExchangeRun> = {}): ExchangeRun {
  return {
    id: 1,
    profile_id: null,
    direction: 'import',
    format: 'csv',
    options: {},
    mapping: { sku: 'sku' },
    dry_run: false,
    status: 'done',
    source: 'price.csv',
    rows_total: 10,
    rows_done: 10,
    created: 2,
    updated: 6,
    skipped: 0,
    failed: 2,
    absent: 0,
    history_id: 40,
    admin_id: 1,
    admin_name: 'Anna',
    file_url: null,
    started_at: '2026-10-01T09:00:00Z',
    finished_at: '2026-10-01T09:01:00Z',
    created_at: '2026-10-01T09:00:00Z',
    ...over,
  }
}

function panel(get: ReturnType<typeof vi.fn>, post = vi.fn()) {
  return {
    apiPath: '/api/cms',
    http: { get, post, put: vi.fn(), delete: vi.fn() },
    i18n: createI18n(),
    can: () => true,
  } as unknown as AdminContext
}

const t = (key: string, params: Record<string, string | number> = {}) =>
  `${key}${Object.values(params).length ? ` ${Object.values(params).join(' ')}` : ''}`

describe('the exchange API', () => {
  it('asks the server where §8.2 says, and leaves the two files to the browser', async () => {
    const post = vi.fn().mockResolvedValue({ data: run() })
    const api = createExchangeApi(panel(vi.fn(), post))

    await api.inspect({ upload_id: 'abc' }, { delimiter: ';' })
    await api.startImport({ url: 'https://x.test/a.csv' }, { profile_id: 3 }, true)

    expect(post).toHaveBeenNthCalledWith(1, '/api/cms/catalog/exchange/inspect', {
      upload_id: 'abc',
      delimiter: ';',
    })
    expect(post).toHaveBeenNthCalledWith(2, '/api/cms/catalog/exchange/import', {
      url: 'https://x.test/a.csv',
      profile_id: 3,
      dry_run: true,
    })
    expect(api.errorsFile(7)).toBe('/api/cms/catalog/exchange/runs/7/errors?format=csv')
    expect(api.file(7)).toBe('/api/cms/catalog/exchange/runs/7/file')
  })
})

describe('the words of a run', () => {
  it('counts only what happened, each count at the end of its line', () => {
    expect(runTotals(run(), t).map((one) => one.text)).toEqual([
      'panel.exchange-created 2',
      'panel.exchange-updated 6',
      'panel.exchange-errors-count 2',
    ])
    expect(runTotals(run({ direction: 'export', rows_done: 40 }), t)[0]!.text).toBe(
      'panel.exchange-exported 40',
    )
  })

  it('says a finished import with errors in amber, not green', () => {
    expect(runStatus(run(), t).type).toBe('warning')
    expect(runStatus(run({ failed: 0 }), t).type).toBe('success')
    expect(runStatus(run({ status: 'stopped' }), t).type).toBe('warning')
  })
})

describe('polling a run', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('asks every two seconds while it runs, and stops once it ends', async () => {
    const answers = [run({ status: 'running', rows_done: 4 }), run({ status: 'done' })]
    const api = { run: vi.fn().mockImplementation(async () => answers.shift()) }
    const seen: string[] = []

    mount(
      defineComponent({
        setup() {
          useRunPolling(api, (fresh) => seen.push(fresh.status)).watch(1)

          return () => h('div')
        },
      }),
    )

    await vi.advanceTimersByTimeAsync(1999)
    expect(api.run).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1)
    await vi.advanceTimersByTimeAsync(2000)
    await vi.advanceTimersByTimeAsync(6000)

    expect(api.run).toHaveBeenCalledTimes(2)
    expect(seen).toEqual(['running', 'done'])
  })
})

describe('ExchangePage', () => {
  beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true }))
  afterEach(() => {
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('opens the run it was sent to, with its errors, and follows the one still going', async () => {
    const going = run({ id: 2, status: 'running', rows_done: 3, failed: 0 })
    const get = vi.fn().mockImplementation(async (url: string) => {
      if (url.endsWith('/runs')) {
        return { data: [going, run()], current_page: 1, last_page: 1, total: 2 }
      }
      if (url.endsWith('/runs/1/errors')) {
        return {
          data: [{ row: 14, column: 'price', value: 'twelve', message: 'Not a number.' }],
          current_page: 1,
          last_page: 1,
        }
      }
      if (url.endsWith('/runs/2')) return { data: { ...going, status: 'done', rows_done: 10 } }
      if (url.endsWith('/profiles')) return { data: [] }

      throw new Error(`Unexpected ${url}`)
    })
    const admin = panel(get)
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/catalog/exchange', component: ExchangePage }],
    })

    await router.push('/catalog/exchange?run=1')

    const wrapper = mount(ExchangePage, {
      global: {
        plugins: [router, { install: (app) => connectModals(app) }],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: admin.i18n },
      },
      attachTo: document.body,
    })
    await flushPromises()

    expect(wrapper.text()).toContain('price.csv')
    expect(wrapper.find('.wx-catalog-run').exists()).toBe(true)
    expect(wrapper.text()).toContain('Not a number.')
    expect(
      wrapper.find('a[href="/api/cms/catalog/exchange/runs/1/errors?format=csv"]').exists(),
    ).toBe(true)

    await vi.advanceTimersByTimeAsync(2100)
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/catalog/exchange/runs/2')
    expect(wrapper.text()).not.toContain('Running')

    wrapper.unmount()
  })
})
