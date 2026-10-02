import { describe, expect, it, vi } from 'vitest'
import type { AdminContext } from '@webx-ui/module-admin'
import { createAuditApi } from './api'

function context(http: Record<string, unknown>): AdminContext {
  return { apiPath: '/api/cms', http } as unknown as AdminContext
}

describe('createAuditApi', () => {
  it('starts a run with the scope it was asked for', async () => {
    const post = vi.fn().mockResolvedValue({ data: { id: 4, status: 'queued' } })

    await expect(createAuditApi(context({ post })).start('quick')).resolves.toMatchObject({ id: 4 })
    expect(post).toHaveBeenCalledWith('/api/cms/audit/runs', { scope: 'quick' })
  })

  it('leaves out a filter nobody set and names the check it opens', async () => {
    const get = vi.fn().mockResolvedValue({
      data: [{ id: 1, check: 'host.mirror' }],
      meta: { current_page: 1, last_page: 2, per_page: 50, total: 51, from: 1, to: 50 },
    })

    const page = await createAuditApi(context({ get })).issues(9, {
      check: 'host.mirror',
      severity: null,
      state: 'new',
      page: 1,
      per_page: 50,
    })

    expect(page.total).toBe(51)
    expect(page.data).toHaveLength(1)
    expect(get).toHaveBeenCalledWith('/api/cms/audit/runs/9/issues', {
      query: {
        check: 'host.mirror',
        severity: undefined,
        group: undefined,
        state: 'new',
        page: 1,
        per_page: 50,
      },
    })
  })

  it('reads the checks of a run as they come', async () => {
    const get = vi.fn().mockResolvedValue({ data: [{ id: 'config.debug', count: 1 }] })

    await expect(createAuditApi(context({ get })).checks(3, { group: 'config' })).resolves.toEqual([
      { id: 'config.debug', count: 1 },
    ])
    expect(get).toHaveBeenCalledWith('/api/cms/audit/runs/3/checks', {
      query: expect.objectContaining({ group: 'config' }),
    })
  })
})
