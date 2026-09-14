import { describe, expect, it, vi } from 'vitest'
import type { AdminContext } from '@webx-ui/module-admin'
import { createSeoApi } from './api'

function context(http: Record<string, unknown>): AdminContext {
  return { apiPath: '/api/cms', http } as unknown as AdminContext
}

describe('createSeoApi', () => {
  it('joins the rows and the paging numbers a resource collection keeps apart', async () => {
    const get = vi.fn().mockResolvedValue({
      data: [{ id: 1, pattern: '/about' }],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
    })

    const page = await createSeoApi(context({ get })).urls()

    expect(page.data).toHaveLength(1)
    expect(page.total).toBe(1)
    expect(get).toHaveBeenCalledWith('/api/cms/seo/urls', { query: expect.any(Object) })
  })

  it('leaves out a filter nobody set', async () => {
    const get = vi.fn().mockResolvedValue({ data: [], meta: {} })

    await createSeoApi(context({ get })).urls({ q: '', match_type: null, page: 2 })

    expect(get).toHaveBeenCalledWith('/api/cms/seo/urls', {
      query: {
        q: undefined,
        match_type: undefined,
        is_active: undefined,
        sort: undefined,
        page: 2,
        per_page: undefined,
      },
    })
  })

  it('replaces a whole rule on save', async () => {
    const put = vi.fn().mockResolvedValue({ data: { id: 7, pattern: '/about' } })
    const input = { match_type: 'exact' as const, pattern: '/about', title: { en: 'About' } }

    await expect(createSeoApi(context({ put })).updateUrl(7, input)).resolves.toEqual({
      id: 7,
      pattern: '/about',
    })
    expect(put).toHaveBeenCalledWith('/api/cms/seo/urls/7', input)
  })

  it('asks about an address in the body, not in the query string', async () => {
    const post = vi.fn().mockResolvedValue({ data: { url: '/catalog?page=2' } })

    await createSeoApi(context({ post })).test('/catalog?page=2')

    expect(post).toHaveBeenCalledWith('/api/cms/seo/test-url', {
      url: '/catalog?page=2',
      locale: undefined,
    })
  })
})
