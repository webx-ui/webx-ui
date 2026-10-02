import { describe, expect, it, vi } from 'vitest'
import type { AdminContext } from '@webx-ui/module-admin'
import { createAuditApi, pageParams } from './api'

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

  it('asks a finding for its fixes, then presses one — a dry run only when told', async () => {
    const get = vi.fn().mockResolvedValue({ data: [{ id: 'audit.replace-host', total: 2 }] })
    const post = vi.fn().mockResolvedValue({ data: { id: 'audit.replace-host', applied: true } })
    const api = createAuditApi(context({ get, post }))

    await expect(api.fixes(3, 17)).resolves.toEqual([{ id: 'audit.replace-host', total: 2 }])
    expect(get).toHaveBeenCalledWith('/api/cms/audit/runs/3/issues/17/fixes')

    await api.fix(3, 17, 'audit.replace-host')
    await api.fix(3, 17, 'seo.normalise-host', true)
    expect(post).toHaveBeenNthCalledWith(
      1,
      '/api/cms/audit/runs/3/issues/17/fixes/audit.replace-host',
      {
        dry_run: false,
      },
    )
    expect(post).toHaveBeenNthCalledWith(
      2,
      '/api/cms/audit/runs/3/issues/17/fixes/seo.normalise-host',
      {
        dry_run: true,
      },
    )
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

  it('sends the field filters of the pages screen as f[field]=op:value', () => {
    expect(
      pageParams({
        indexable: false,
        filters: [
          { field: 'title', op: 'empty' },
          { field: 'word_count', op: 'lt', value: '250' },
        ],
        sort: '-word_count',
      }),
    ).toEqual({
      search: undefined,
      status: undefined,
      indexable: 0,
      check: undefined,
      sort: '-word_count',
      page: undefined,
      per_page: undefined,
      'f[title]': 'empty',
      'f[word_count]': 'lt:250',
    })
  })

  it('asks what a page loads a tab at a time', async () => {
    const get = vi.fn().mockResolvedValue({
      data: [{ id: 4, url: 'https://shop.com/logo.svg', status: 200 }],
      meta: { current_page: 1, last_page: 1, per_page: 50, total: 1, from: 1, to: 1 },
    })

    const page = await createAuditApi(context({ get })).resources(7, 12, { tab: 'images' })

    expect(get).toHaveBeenCalledWith('/api/cms/audit/runs/7/pages/12/resources', {
      query: { tab: 'images' },
    })
    expect(page.total).toBe(1)
    expect(page.data[0]?.url).toBe('https://shop.com/logo.svg')
  })

  it('builds the CSV address with the same filters and the columns on screen', () => {
    const file = createAuditApi(context({})).pagesFile(
      7,
      { status: '4xx', search: 'blog', page: 3 },
      ['url', 'status'],
    )

    expect(file).toBe(
      '/api/cms/audit/runs/7/pages/export?search=blog&status=4xx&columns=url%2Cstatus',
    )
  })
})
