import type { AdminContext } from '@webx-ui/module-admin'
import type { IndexReport, RebuildProgress } from './types'

export interface SearchIndexApi {
  report(): Promise<IndexReport>
  /** The rebuild queued; refused with 409 while one is waiting or running. */
  rebuild(): Promise<RebuildProgress>
}

export function createSearchIndexApi(admin: AdminContext): SearchIndexApi {
  const base = `${admin.apiPath}/search-index`

  return {
    report: () => admin.http.get<{ data: IndexReport }>(base).then((body) => body.data),
    rebuild: () =>
      admin.http.post<{ data: RebuildProgress }>(`${base}/rebuild`, {}).then((body) => body.data),
  }
}
