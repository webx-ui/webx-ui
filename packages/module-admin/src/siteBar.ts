import type { Http } from './http'

/**
 * What `GET /api/cms/site-bar/context` says about the page in front of the panel on the site —
 * mirrors `WebxUi\Admin\Http\Controllers\SiteBarController::context()`.
 */
export interface SiteBarContext {
  /** The path that was asked about, without its query. */
  url: string
  /** The language of the address, when the registry knows it. */
  locale: string | null
  /** The record behind the address, or null — a page of the site's own code, or none it may open. */
  entity: SiteBarEntity | null
  /** The tabs this administrator gets here, in order. */
  sections: string[]
}

export type SiteBarStatus = 'published' | 'scheduled' | 'unpublished' | 'trashed'

export interface SiteBarEntity {
  type: string
  id: number | string
  /** What the record is, in the panel's language: «Page», «Article». */
  kind: string
  label: string
  status: SiteBarStatus
  /** Absent for a record that keeps no draft. */
  draft: { differs: boolean; by: string | null; at: string | null } | null
  updatedAt: string | null
  /** Where the panel edits it, relative to the panel. */
  edit: string | null
  can: { edit: boolean; publish: boolean }
}

export function fetchSiteBarContext(
  http: Http,
  apiPath: string,
  url: string,
): Promise<{ data: SiteBarContext }> {
  return http.get<{ data: SiteBarContext }>(
    `${apiPath}/site-bar/context?url=${encodeURIComponent(url)}`,
  )
}

/**
 * Say something to the page the panel is framed in. Only to that page's own origin: the screen
 * may be opened on its own, or framed by somebody else, and neither should hear it.
 */
export function tellSitePage(type: 'close'): void {
  if (typeof window === 'undefined' || window.parent === window) return

  window.parent.postMessage({ wxBar: 1, type }, window.location.origin)
}
