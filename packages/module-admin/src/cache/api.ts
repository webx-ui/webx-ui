import type { AdminContext } from '../admin'

/** One group of cached data, as `GET /cache` lists it (WEBX_UI_CACHE.md §5). */
export interface CacheGroupRow {
  id: string
  /** The owner's words for it; the id when it gave none. */
  title: string
  /** The version its entries carry now; moves with every reset. */
  version: number
  /** The models whose every save resets it, by short name; empty for a group reset only by hand. */
  models: string[]
}

export interface CacheReport {
  /** The store's driver: `file`, `database`, `redis` — or `array`, which keeps nothing. */
  driver: string
  /** The store's name in the configuration. */
  store: string
  /** `webx-cache.enabled` — off, the site computes everything on every request. */
  enabled: boolean
  /** How long an entry lives, in seconds, unless its code says otherwise. */
  ttl: number
  groups: CacheGroupRow[]
}

/** What a reset answers: the groups moved, and the page's data as it now is. */
export interface CacheResetResult extends CacheReport {
  reset: string[]
}

export interface CacheApi {
  report(): Promise<CacheReport>
  /** One group, or every group with none named. Refused with 422 for a group that is not there. */
  reset(group?: string | null): Promise<CacheResetResult>
}

export function createCacheApi(admin: AdminContext): CacheApi {
  const base = `${admin.apiPath}/cache`

  return {
    report: () => admin.http.get<{ data: CacheReport }>(base).then((body) => body.data),
    reset: (group = null) =>
      admin.http
        .post<{ data: CacheResetResult }>(`${base}/reset`, group === null ? {} : { group })
        .then((body) => body.data),
  }
}
