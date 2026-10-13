import type { Component } from 'vue'
import type { AdminContext } from './admin'
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

/**
 * A tab a section adds to the panel on the site — «SEO» from `module-seo`, «Blocks» from
 * `module-blocks`.
 *
 * The server decides whether the tab is there (`SiteBar::section()` answers per page and per
 * administrator, and `context.sections` lists the ones that said yes); the module brings what
 * it looks like. Split that way so the frame never learns what its tabs are about, and a tab
 * the server offers without a module on this side is simply not drawn.
 */
export interface SiteBarTab {
  /** The name the server registered the section under. */
  name: string
  /** What the tab is called, in the panel's language. */
  label: (admin: AdminContext) => string
  /** Drawn inside the tab, with the page's context as its `context` prop. */
  component: Component
}

/**
 * @param locale The language the page says it is in (`<html lang>`). The address's own row wins
 *               over it; it only matters for a page of the site's own code, which has no row.
 */
export function fetchSiteBarContext(
  http: Http,
  apiPath: string,
  url: string,
  locale?: string | null,
): Promise<{ data: SiteBarContext }> {
  const language = locale ? `&locale=${encodeURIComponent(locale)}` : ''

  return http.get<{ data: SiteBarContext }>(
    `${apiPath}/site-bar/context?url=${encodeURIComponent(url)}${language}`,
  )
}

/**
 * What the panel can ask of the page it is framed in:
 *
 * - `close` — shut the drawer;
 * - `title` — put this in the browser's tab: the page's `<title>` after an edit, so the editor
 *   sees it changed without reloading (the rest of `<head>` waits for the next load).
 */
export type SiteBarMessage = { type: 'close' } | { type: 'title'; title: string }

/**
 * Say something to the page the panel is framed in. Only to that page's own origin: the screen
 * may be opened on its own, or framed by somebody else, and neither should hear it.
 */
export function tellSitePage(message: SiteBarMessage | 'close'): void {
  if (typeof window === 'undefined' || window.parent === window) return

  const body = typeof message === 'string' ? { type: message } : message

  window.parent.postMessage({ wxBar: 1, ...body }, window.location.origin)
}
