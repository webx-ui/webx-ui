import type { AdminContext } from '@webx-ui/module-admin'
import type {
  AuditCheckRow,
  AuditFixOffer,
  AuditFixResult,
  AuditIssue,
  AuditIssueQuery,
  AuditLatest,
  AuditLinkRow,
  AuditPage,
  AuditPageCard,
  AuditPageQuery,
  AuditPageRow,
  AuditResourceRow,
  AuditResourceTab,
  AuditRun,
  AuditScope,
} from './types'

export interface AuditApi {
  /** The run that is going, the last one that finished, and whether the queue can take one. */
  latest(): Promise<AuditLatest>
  run(id: number): Promise<AuditRun>
  /** Queues a run; refused with 409 on a `sync` queue or while another one is going. */
  start(scope: AuditScope): Promise<AuditRun>
  cancel(id: number): Promise<AuditRun>
  /** The checks of a run that found something, worst first. */
  checks(run: number, query?: AuditIssueQuery): Promise<AuditCheckRow[]>
  /** The findings of a run — of one check when `check` is given. */
  issues(run: number, query?: AuditIssueQuery): Promise<AuditPage<AuditIssue>>
  /** The crawled pages of a run under the screen's filters. */
  pages(run: number, query?: AuditPageQuery): Promise<AuditPage<AuditPageRow>>
  /** Where the browser downloads the same list as CSV, under the panel's session. */
  pagesFile(run: number, query: AuditPageQuery, columns: string[]): string
  page(run: number, id: number): Promise<AuditPageCard>
  /** The links of a page: `out` — what it points at, `in` — what points at it. */
  links(
    run: number,
    id: number,
    query?: { direction?: 'in' | 'out'; kind?: string; page?: number; per_page?: number },
  ): Promise<AuditPage<AuditLinkRow>>
  /** What a page loads, a tab of the card at a time. */
  resources(
    run: number,
    id: number,
    query: { tab: AuditResourceTab; page?: number; per_page?: number },
  ): Promise<AuditPage<AuditResourceRow>>
  /** The fixes that can close a finding now, each with what it would change. */
  fixes(run: number, issue: number): Promise<AuditFixOffer[]>
  /** Presses a fix — or, with `dryRun`, only asks again what it would change. */
  fix(run: number, issue: number, fix: string, dryRun?: boolean): Promise<AuditFixResult>
}

/** The query of the pages screen as the API reads it: `f[field]=op:value` for each filter. */
export function pageParams(query: AuditPageQuery): Record<string, string | number | undefined> {
  const params: Record<string, string | number | undefined> = {
    search: query.search || undefined,
    status: query.status || undefined,
    indexable:
      query.indexable === null || query.indexable === undefined
        ? undefined
        : query.indexable
          ? 1
          : 0,
    check: query.check || undefined,
    sort: query.sort || undefined,
    page: query.page,
    per_page: query.per_page,
  }

  for (const filter of query.filters ?? []) {
    params[`f[${filter.field}]`] =
      filter.value === undefined || filter.value === '' ? filter.op : `${filter.op}:${filter.value}`
  }

  return params
}

/** Everything under `/audit`, below the panel's API path. */
export function createAuditApi(admin: AdminContext): AuditApi {
  const base = `${admin.apiPath}/audit`
  const data = <T>(body: { data: T }): T => body.data

  /* `undefined` drops a parameter; `null` and `''` would be sent as words. */
  const filters = (query: AuditIssueQuery) => ({
    check: query.check || undefined,
    severity: query.severity || undefined,
    group: query.group || undefined,
    state: query.state || undefined,
    page: query.page,
    per_page: query.per_page,
  })

  return {
    latest: () => admin.http.get<{ data: AuditLatest }>(`${base}/runs/latest`).then(data),

    run: (id) => admin.http.get<{ data: AuditRun }>(`${base}/runs/${id}`).then(data),

    start: (scope) => admin.http.post<{ data: AuditRun }>(`${base}/runs`, { scope }).then(data),

    cancel: (id) => admin.http.post<{ data: AuditRun }>(`${base}/runs/${id}/cancel`, {}).then(data),

    checks: (run, query = {}) =>
      admin.http
        .get<{ data: AuditCheckRow[] }>(`${base}/runs/${run}/checks`, { query: filters(query) })
        .then(data),

    issues: (run, query = {}) =>
      admin.http
        .get<{
          data: AuditIssue[]
          meta: Omit<AuditPage<AuditIssue>, 'data'>
        }>(`${base}/runs/${run}/issues`, { query: filters(query) })
        .then((body) => ({ ...body.meta, data: body.data })),

    pages: (run, query = {}) =>
      admin.http
        .get<{
          data: AuditPageRow[]
          meta: Omit<AuditPage<AuditPageRow>, 'data'>
        }>(`${base}/runs/${run}/pages`, { query: pageParams(query) })
        .then((body) => ({ ...body.meta, data: body.data })),

    pagesFile: (run, query, columns) => {
      const params = new URLSearchParams()

      for (const [key, value] of Object.entries({
        ...pageParams(query),
        page: undefined,
        per_page: undefined,
      })) {
        if (value !== undefined) params.set(key, String(value))
      }

      params.set('columns', columns.join(','))

      return `${base}/runs/${run}/pages/export?${params.toString()}`
    },

    page: (run, id) =>
      admin.http.get<{ data: AuditPageCard }>(`${base}/runs/${run}/pages/${id}`).then(data),

    links: (run, id, query = {}) =>
      admin.http
        .get<{
          data: AuditLinkRow[]
          meta: Omit<AuditPage<AuditLinkRow>, 'data'>
        }>(`${base}/runs/${run}/pages/${id}/links`, { query })
        .then((body) => ({ ...body.meta, data: body.data })),

    resources: (run, id, query) =>
      admin.http
        .get<{
          data: AuditResourceRow[]
          meta: Omit<AuditPage<AuditResourceRow>, 'data'>
        }>(`${base}/runs/${run}/pages/${id}/resources`, { query })
        .then((body) => ({ ...body.meta, data: body.data })),

    fixes: (run, issue) =>
      admin.http
        .get<{ data: AuditFixOffer[] }>(`${base}/runs/${run}/issues/${issue}/fixes`)
        .then(data),

    fix: (run, issue, fix, dryRun = false) =>
      admin.http
        .post<{
          data: AuditFixResult
        }>(`${base}/runs/${run}/issues/${issue}/fixes/${encodeURIComponent(fix)}`, {
          dry_run: dryRun,
        })
        .then(data),
  }
}
