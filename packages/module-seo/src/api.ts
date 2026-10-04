import { HttpError, type AdminContext } from '@webx-ui/module-admin'
import type {
  SeoAlias,
  SeoAliasQuery,
  SeoFaqImportMode,
  SeoFaqImportResult,
  SeoLinkAddress,
  SeoLinkBlock,
  SeoLinkHeadingInput,
  SeoLinkHeadingResult,
  SeoLinkImportMode,
  SeoLinkImportResult,
  SeoLinkInput,
  SeoLinkQuery,
  SeoLinkSaved,
  SeoPage,
  SeoRedirect,
  SeoRedirectInput,
  SeoRedirectQuery,
  SeoSitemapStatus,
  SeoTestResult,
  SeoUrlInput,
  SeoUrlQuery,
  SeoUrlRule,
} from './types'

export interface SeoApi {
  /** The rules, in the order the site tries them. */
  urls(query?: SeoUrlQuery): Promise<SeoPage<SeoUrlRule>>
  url(id: number): Promise<SeoUrlRule>
  createUrl(input: SeoUrlInput): Promise<SeoUrlRule>
  updateUrl(id: number, input: SeoUrlInput): Promise<SeoUrlRule>
  removeUrl(id: number): Promise<void>

  redirects(query?: SeoRedirectQuery): Promise<SeoPage<SeoRedirect>>
  createRedirect(input: SeoRedirectInput): Promise<SeoRedirect>
  updateRedirect(id: number, input: SeoRedirectInput): Promise<SeoRedirect>
  removeRedirect(id: number): Promise<void>

  /** The redirects nobody wrote: what the address registry kept after a rename. Read only. */
  aliases(query?: SeoAliasQuery): Promise<SeoPage<SeoAlias>>

  /** What an address ends up saying, and where every part of it came from. */
  test(url: string, locale?: string | null): Promise<SeoTestResult>

  /** What is in the sitemap, when it was built, and what was kept out of it. */
  sitemap(): Promise<SeoSitemapStatus>
  /** Build it again now; answers with the new numbers. */
  rebuildSitemap(): Promise<SeoSitemapStatus>

  /** Donors with blocks of links, there only where interlinking is turned on (§18.4). */
  links(query?: SeoLinkQuery): Promise<SeoPage<SeoLinkBlock>>
  link(id: number): Promise<SeoLinkBlock>
  createLink(input: SeoLinkInput): Promise<SeoLinkSaved>
  updateLink(id: number, input: SeoLinkInput): Promise<SeoLinkSaved>
  removeLink(id: number): Promise<void>
  /** A file in the brief's own format; a preview unless `dryRun` is false. */
  importLinks(file: Blob, mode: SeoLinkImportMode, dryRun: boolean): Promise<SeoLinkImportResult>
  /** Where the whole list downloads from, in the same flat format the import reads. */
  exportLinksUrl(format: 'csv' | 'xlsx'): string
  /** One heading on many donors; a count of them unless `dry_run` is false. */
  linksHeading(input: SeoLinkHeadingInput): Promise<SeoLinkHeadingResult>
  /** Addresses from the registry for an acceptor field to suggest. */
  linkAddresses(q: string, locale?: string | null): Promise<SeoLinkAddress[]>

  /** Page FAQs from a file (§18.5); a preview unless `dryRun` is false. */
  importFaq(file: Blob, mode: SeoFaqImportMode, dryRun: boolean): Promise<SeoFaqImportResult>
  /** Where every page FAQ downloads from, in the format the import reads. */
  exportFaqUrl(format: 'csv' | 'xlsx'): string
}

/** Everything under `/seo`, below the panel's API path. */
export function createSeoApi(admin: AdminContext): SeoApi {
  const base = `${admin.apiPath}/seo`
  const data = <T>(body: { data: T }): T => body.data

  /* A paginated list arrives as a resource collection — rows under `data`, numbers under
     `meta`. The table wants them on one object, so they are joined here once rather than in
     every screen. */
  const page = <T>(body: { data: T[]; meta: Omit<SeoPage<T>, 'data'> }): SeoPage<T> => ({
    ...body.meta,
    data: body.data,
  })

  /* `undefined` drops the parameter; `null` and `''` would be sent as words and read as a
     filter nobody asked for. */
  const listQuery = (
    query: SeoRedirectQuery,
  ): Record<string, string | number | boolean | null | undefined> => ({
    q: query.q || undefined,
    has_faq: query.has_faq ? 1 : undefined,
    match_type: query.match_type ?? undefined,
    is_active: query.is_active ?? undefined,
    sort: query.sort || undefined,
    page: query.page,
    per_page: query.per_page,
  })

  return {
    urls: (query = {}) =>
      admin.http
        .get<{ data: SeoUrlRule[]; meta: Omit<SeoPage<SeoUrlRule>, 'data'> }>(`${base}/urls`, {
          query: listQuery(query),
        })
        .then(page),

    url: (id) => admin.http.get<{ data: SeoUrlRule }>(`${base}/urls/${id}`).then(data),

    createUrl: (input) => admin.http.post<{ data: SeoUrlRule }>(`${base}/urls`, input).then(data),

    updateUrl: (id, input) =>
      admin.http.put<{ data: SeoUrlRule }>(`${base}/urls/${id}`, input).then(data),

    removeUrl: (id) => admin.http.delete<void>(`${base}/urls/${id}`),

    redirects: (query = {}) =>
      admin.http
        .get<{ data: SeoRedirect[]; meta: Omit<SeoPage<SeoRedirect>, 'data'> }>(
          `${base}/redirects`,
          { query: listQuery(query) },
        )
        .then(page),

    createRedirect: (input) =>
      admin.http.post<{ data: SeoRedirect }>(`${base}/redirects`, input).then(data),

    updateRedirect: (id, input) =>
      admin.http.put<{ data: SeoRedirect }>(`${base}/redirects/${id}`, input).then(data),

    removeRedirect: (id) => admin.http.delete<void>(`${base}/redirects/${id}`),

    aliases: (query = {}) =>
      admin.http
        .get<{ data: SeoAlias[]; meta: Omit<SeoPage<SeoAlias>, 'data'> }>(`${base}/aliases`, {
          query: {
            q: query.q || undefined,
            locale: query.locale ?? undefined,
            page: query.page,
            per_page: query.per_page,
          },
        })
        .then(page),

    // A POST because it carries an address in its body, and an address in a query string is an
    // address somebody has to escape twice.
    test: (url, locale = null) =>
      admin.http
        .post<{ data: SeoTestResult }>(`${base}/test-url`, { url, locale: locale ?? undefined })
        .then(data),

    sitemap: () => admin.http.get<{ data: SeoSitemapStatus }>(`${base}/sitemap`).then(data),

    rebuildSitemap: () =>
      admin.http.post<{ data: SeoSitemapStatus }>(`${base}/sitemap`, {}).then(data),

    links: (query = {}) =>
      admin.http
        .get<{ data: SeoLinkBlock[]; meta: Omit<SeoPage<SeoLinkBlock>, 'data'> }>(`${base}/links`, {
          query: {
            q: query.q || undefined,
            broken: query.broken ? 1 : undefined,
            page: query.page,
            per_page: query.per_page,
          },
        })
        .then(page),

    link: (id) => admin.http.get<{ data: SeoLinkBlock }>(`${base}/links/${id}`).then(data),

    createLink: (input) =>
      admin.http.post<{ data: SeoLinkSaved }>(`${base}/links`, input).then(data),

    updateLink: (id, input) =>
      admin.http.put<{ data: SeoLinkSaved }>(`${base}/links/${id}`, input).then(data),

    removeLink: (id) => admin.http.delete<void>(`${base}/links/${id}`),

    importLinks: (file, mode, dryRun) =>
      upload<SeoLinkImportResult>(`${base}/links/import`, file, mode, dryRun, 'links.csv'),

    exportLinksUrl: (format) => `${base}/links/export?format=${format}`,

    linksHeading: (input) =>
      admin.http.post<{ data: SeoLinkHeadingResult }>(`${base}/links/heading`, input).then(data),

    linkAddresses: (q, locale = null) =>
      admin.http
        .get<{ data: SeoLinkAddress[] }>(`${base}/links/addresses`, {
          query: { q: q || undefined, locale: locale ?? undefined },
        })
        .then(data),

    importFaq: (file, mode, dryRun) =>
      upload<SeoFaqImportResult>(`${base}/faq/import`, file, mode, dryRun, 'faq.csv'),

    exportFaqUrl: (format) => `${base}/faq/export?format=${format}`,
  }

  // Multipart rather than JSON, so it goes through `send`: the panel's cookie, token and
  // language, and the body as it is.
  async function upload<T>(
    url: string,
    file: Blob,
    mode: string,
    dryRun: boolean,
    name: string,
  ): Promise<T> {
    const body = new FormData()
    body.append('file', file, file instanceof File ? file.name : name)
    body.append('mode', mode)
    body.append('dry_run', dryRun ? '1' : '0')

    if (admin.http.send === undefined) throw new Error('This client cannot send a file.')

    const response = await admin.http.send('POST', url, { body })

    if (!response.ok) throw await failure(response)

    return data(await (response.json() as Promise<{ data: T }>))
  }
}

/** A refused upload in the shape the JSON calls throw, so a form reads its 422 the same way. */
async function failure(response: Response): Promise<HttpError> {
  type Body = { message?: unknown; errors?: unknown }
  let body = null as Body | null

  try {
    body = (await response.json()) as Body
  } catch {
    // A gateway answers with HTML; there is nothing to read out of it.
  }

  return new HttpError(
    typeof body?.message === 'string' ? body.message : `Request failed with ${response.status}`,
    response.status,
    body?.errors !== null && typeof body?.errors === 'object'
      ? (body.errors as Record<string, string[]>)
      : {},
    null,
    body,
  )
}
