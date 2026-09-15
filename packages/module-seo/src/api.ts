import type { AdminContext } from '@webx-ui/module-admin'
import type {
  SeoAlias,
  SeoAliasQuery,
  SeoPage,
  SeoRedirect,
  SeoRedirectInput,
  SeoRedirectQuery,
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
  }
}
