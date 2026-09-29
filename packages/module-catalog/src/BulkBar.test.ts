import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import BulkBar from './BulkBar.vue'
import type { BulkActionInfo, BulkRun } from './types'

const actions: BulkActionInfo[] = [
  { key: 'publish', label: 'Publish', permission: 'catalog.manage', trashed: false, params: [] },
  { key: 'delete', label: 'Delete', permission: 'catalog.delete', trashed: false, params: [] },
]

function run(over: Partial<BulkRun> = {}): BulkRun {
  return {
    id: null,
    action: 'publish',
    label: 'Publish',
    status: 'done',
    total: 2,
    done: 2,
    failed: 0,
    errors: [],
    history_id: null,
    created_at: null,
    finished_at: null,
    ...over,
  }
}

function bar(post: ReturnType<typeof vi.fn>, get?: ReturnType<typeof vi.fn>) {
  const admin = {
    apiPath: '/api/cms',
    http: {
      get: get ?? vi.fn().mockResolvedValue({ data: actions }),
      post,
      put: vi.fn(),
      delete: vi.fn(),
    },
    i18n: createI18n(),
    can: () => true,
  } as unknown as AdminContext

  return mount(BulkBar, {
    props: { count: 2, selection: { ids: [1, 2] } },
    global: {
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: admin.i18n },
    },
    attachTo: document.body,
  })
}

async function choose(wrapper: ReturnType<typeof bar>, label: string): Promise<void> {
  await wrapper.find('button').trigger('click')
  await flushPromises()

  const item = [...document.querySelectorAll('.wx-dropdown-item')].find((one) =>
    one.textContent?.includes(label),
  ) as HTMLElement | undefined

  if (!item) throw new Error(`No action called ${label} in the menu.`)
  item.click()
  await flushPromises()
}

describe('BulkBar', () => {
  beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true }))
  afterEach(() => {
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('starts an action on the selection, and a small one is finished at once', async () => {
    const post = vi.fn().mockResolvedValue({ data: run() })
    const wrapper = bar(post)
    await flushPromises()

    await choose(wrapper, 'Publish')

    expect(post).toHaveBeenCalledWith('/api/cms/catalog/bulk', {
      action: 'publish',
      params: {},
      selection: { ids: [1, 2] },
    })
    expect(wrapper.emitted('finished')).toEqual([[[]]])
  })

  it('follows a queued run to its end and lists what was refused', async () => {
    const post = vi
      .fn()
      .mockResolvedValue({ data: run({ id: 7, status: 'queued', done: 0, total: 900 }) })
    const answers = [
      run({ id: 7, status: 'running', done: 500, total: 900 }),
      run({
        id: 7,
        status: 'done',
        done: 899,
        failed: 1,
        total: 900,
        errors: [{ id: 3, name: 'Orphan', message: 'No main category.' }],
      }),
    ]
    const get = vi
      .fn()
      .mockImplementation((url: string) =>
        url.endsWith('/catalog/bulk')
          ? Promise.resolve({ data: actions })
          : Promise.resolve({ data: answers.shift() }),
      )
    const wrapper = bar(post, get)
    await flushPromises()

    await choose(wrapper, 'Publish')
    expect(wrapper.find('[role="status"]').exists()).toBe(true)

    await vi.advanceTimersByTimeAsync(1600)
    await flushPromises()
    expect(wrapper.text()).toContain('500 of 900')

    await vi.advanceTimersByTimeAsync(1600)
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/catalog/bulk/7')
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Refused: 1')
    expect(wrapper.text()).toContain('Orphan')
    expect(wrapper.emitted('finished')).toEqual([[[3]]])
  })
})
