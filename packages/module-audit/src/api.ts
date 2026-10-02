import type { AdminContext } from '@webx-ui/module-admin'
import type {
  AuditCheckRow,
  AuditIssue,
  AuditIssueQuery,
  AuditLatest,
  AuditPage,
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
  }
}
