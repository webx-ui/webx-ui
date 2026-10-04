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

  it('reads the aliases from the registry beside the redirects', async () => {
    const get = vi.fn().mockResolvedValue({
      data: [{ id: 3, pattern: '/about', target: '/about-us' }],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1, from: 1, to: 1 },
    })

    const page = await createSeoApi(context({ get })).aliases({ q: 'about' })

    expect(page.data[0]?.target).toBe('/about-us')
    expect(get).toHaveBeenCalledWith('/api/cms/seo/aliases', {
      query: { q: 'about', locale: undefined, page: undefined, per_page: undefined },
    })
  })

  it('asks about an address in the body, not in the query string', async () => {
    const post = vi.fn().mockResolvedValue({ data: { url: '/catalog?page=2' } })

    await createSeoApi(context({ post })).test('/catalog?page=2')

    expect(post).toHaveBeenCalledWith('/api/cms/seo/test-url', {
      url: '/catalog?page=2',
      locale: undefined,
    })
  })

  it('reads the sitemap and rebuilds it at the same address', async () => {
    const status = { enabled: true, url: '/sitemap.xml', built_at: null, files: {}, total: 0 }
    const get = vi.fn().mockResolvedValue({ data: status })
    const post = vi.fn().mockResolvedValue({ data: { ...status, total: 3 } })
    const api = createSeoApi(context({ get, post }))

    await expect(api.sitemap()).resolves.toEqual(status)
    await expect(api.rebuildSitemap()).resolves.toMatchObject({ total: 3 })
    expect(get).toHaveBeenCalledWith('/api/cms/seo/sitemap')
    expect(post).toHaveBeenCalledWith('/api/cms/seo/sitemap', {})
  })

  it('sends the broken-only filter as a number and leaves it out when off', async () => {
    const get = vi.fn().mockResolvedValue({ data: [], meta: {} })
    const api = createSeoApi(context({ get }))

    await api.links({ q: 'phones', broken: true })
    await api.links({ broken: false })

    expect(get).toHaveBeenNthCalledWith(1, '/api/cms/seo/links', {
      query: { q: 'phones', broken: 1, page: undefined, per_page: undefined },
    })
    expect(get).toHaveBeenNthCalledWith(2, '/api/cms/seo/links', {
      query: { q: undefined, broken: undefined, page: undefined, per_page: undefined },
    })
  })

  it('uploads a brief as multipart, a preview unless told otherwise', async () => {
    const send = vi
      .fn()
      .mockResolvedValue(new Response(JSON.stringify({ data: { donors: 2 } }), { status: 200 }))
    const file = new File(['donor,acceptor,anchor'], 'brief.csv')

    await expect(
      createSeoApi(context({ send })).importLinks(file, 'append', true),
    ).resolves.toEqual({ donors: 2 })

    const [method, path, options] = send.mock.calls[0]!
    const body = options.body as FormData

    expect([method, path]).toEqual(['POST', '/api/cms/seo/links/import'])
    expect((body.get('file') as File).name).toBe('brief.csv')
    expect(body.get('mode')).toBe('append')
    expect(body.get('dry_run')).toBe('1')
  })

  it('uploads a FAQ file to its own address and asks the list for pages with a FAQ', async () => {
    const send = vi
      .fn()
      .mockResolvedValue(new Response(JSON.stringify({ data: { addresses: 1 } }), { status: 200 }))
    const get = vi.fn().mockResolvedValue({ data: [], meta: {} })
    const api = createSeoApi(context({ send, get }))

    await expect(api.importFaq(new Blob(['x']), 'replace', false)).resolves.toEqual({
      addresses: 1,
    })

    const [method, path, options] = send.mock.calls[0]!

    expect([method, path]).toEqual(['POST', '/api/cms/seo/faq/import'])
    expect((options.body as FormData).get('dry_run')).toBe('0')
    expect(api.exportFaqUrl('xlsx')).toBe('/api/cms/seo/faq/export?format=xlsx')

    await api.urls({ has_faq: true })

    expect(get.mock.calls[0]![1].query.has_faq).toBe(1)
  })

  it('throws a refused upload with its errors, the way a form reads a 422', async () => {
    const send = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Invalid.', errors: { file: ['Unreadable.'] } }), {
        status: 422,
      }),
    )

    await expect(
      createSeoApi(context({ send })).importLinks(new Blob(['x']), 'replace', false),
    ).rejects.toMatchObject({ status: 422, errors: { file: ['Unreadable.'] } })
  })

  it('sets one heading on the ticked donors or on a prefix', async () => {
    const post = vi.fn().mockResolvedValue({ data: { count: 3 } })

    await createSeoApi(context({ post })).linksHeading({
      prefix: '/catalog/tech/',
      heading: 'Similar',
      dry_run: true,
    })

    expect(post).toHaveBeenCalledWith('/api/cms/seo/links/heading', {
      prefix: '/catalog/tech/',
      heading: 'Similar',
      dry_run: true,
    })
  })
})
