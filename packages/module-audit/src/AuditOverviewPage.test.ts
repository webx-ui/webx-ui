import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import AuditOverviewPage from './AuditOverviewPage.vue'
import type { AuditCounts, AuditRun } from './types'

function run(counts: AuditCounts): AuditRun {
  return {
    id: 7,
    status: 'done',
    scope: 'full',
    base_url: 'https://shop.example.com',
    resolve_to: null,
    urls: [],
    progress: { stage: null, done: [], checks: 0, pages: { crawled: 12, limit: 500 } },
    counts,
    started_by: null,
    created_at: null,
    started_at: null,
    finished_at: '2026-10-04T12:00:00Z',
    error: null,
  }
}

const counts = (extra: Partial<AuditCounts>): AuditCounts => ({
  severity: { error: 4, warning: 9, notice: 1 },
  groups: {},
  checks: [],
  failed: {},
  health: 51,
  new: 0,
  fixed: 0,
  previous_id: null,
  sources: { searched: [], missing: [] },
  ...extra,
})

function overview(done: AuditRun) {
  const get = vi.fn().mockResolvedValue({
    data: { active: null, done, crawled: done, last: done, queue: { sync: false } },
  })
  const i18n = createI18n()
  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => false,
  } as unknown as AdminContext

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:all(.*)', component: { template: '<div />' } }],
  })

  return mount(AuditOverviewPage, {
    props: { base: '/audit' },
    global: {
      plugins: [WebxUI, router],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
    },
  })
}

describe('AuditOverviewPage', () => {
  it('says what the health is made of', async () => {
    const wrapper = overview(
      run(counts({ health_parts: { pages: 12, clean: 9, site_errors: 1, warnings: 6 } })),
    )
    await flushPromises()

    const parts = wrapper.get('.wx-audit-overview__parts').text()

    expect(wrapper.text()).toContain('51%')
    expect(parts).toContain('Pages without errors: 9 of 12')
    expect(parts).toContain('Errors of the whole site: 1')
    expect(parts).toContain('Checks with warnings: 6')
    expect(parts).toContain('The share of pages without errors')
  })

  it('leaves out what is zero, and the breakdown of a run counted before it was kept', async () => {
    const quick = overview(
      run(
        counts({ health: 100, health_parts: { pages: 0, clean: 0, site_errors: 0, warnings: 0 } }),
      ),
    )
    await flushPromises()

    expect(quick.get('.wx-audit-overview__parts').text()).not.toContain('Pages without errors')
    expect(quick.text()).not.toContain('Errors of the whole site')

    const old = overview(run(counts({})))
    await flushPromises()

    expect(old.find('.wx-audit-overview__parts').exists()).toBe(false)
    expect(old.text()).toContain('51%')
  })
})
