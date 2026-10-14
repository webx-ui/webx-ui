import { inject, type Component, type InjectionKey } from 'vue'
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
  draft: SiteBarDraft | null
  updatedAt: string | null
  /** Where the panel edits it, relative to the panel. */
  edit: string | null
  /** A signed link to the draft shown as the page it will be; only while there is a draft. */
  preview?: string | null
  /** What the record's editor in the panel calls it — `editing/{editing}/{id}` is its heartbeat. */
  editing?: string | null
  /** The revision the record's editor holds; sent with «Publish» so an unseen edit is not published. */
  revision?: string | null
  can: { edit: boolean; publish: boolean }
}

export interface SiteBarDraft {
  differs: boolean
  by: string | null
  at: string | null
  /** How many fields of the record the draft changes. */
  changes?: number
  /** Whether the last hand on the draft was this administrator's. */
  mine?: boolean
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
  /** The name the server registered the section under — and the tab's own, as `show()` names it. */
  name: string
  /**
   * More sections the same tab stands for: drawn once when the server offered any of them.
   * «Blocks» is the record's blocks and the layout's regions — two rights, one picker on the page.
   */
  sections?: string[]
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
 *   sees it changed without reloading (the rest of `<head>` waits for the next load);
 * - `reload` — load the page again: after «Publish» and «Discard» it is the site's again;
 * - `tab` — the tab in front, for the page to remember and open the next frame on;
 * - `blocks` — whose blocks may be picked on the page — the record's, a region's — and what each
 *   is called;
 * - `mode` — picking blocks on the page on or off (§8);
 * - `select` / `highlight` — frame a block on the page and bring it into view, or stop;
 * - `replace` — put this markup in place of the block's range: the block drawn from the draft;
 * - `insert` — the same when the page has the block, otherwise put it in after the first of
 *   `after` the page has, before the first of `before`, or — `top` — at the end of its tree;
 * - `move` — carry the block's range to where `after`/`before` say, `html` if the page lacks it;
 * - `remove` — take the block's range out (removed, or hidden: the site does not draw it);
 * - `assets` — add the styles and script of block types the page did not have.
 *
 * A block is named by its key and its `scope` — `{type}:{id}` of a record, `region:{name}` of a
 * region — since the header and the page are two trees that never promised each other unique keys.
 */
export type SiteBarMessage =
  | { type: 'close' }
  | { type: 'title'; title: string }
  | { type: 'reload' }
  | { type: 'tab'; name: string }
  | { type: 'blocks'; sources: SiteBarBlockSource[] }
  | { type: 'mode'; on: boolean }
  | { type: 'select'; key: string | null; scope?: string }
  | { type: 'highlight'; key: string | null; scope?: string }
  | { type: 'replace'; key: string; scope?: string; html: string }
  | ({ type: 'insert' | 'move'; key: string; scope?: string; html: string } & SiteBarPlace)
  | { type: 'remove'; key: string; scope?: string }
  | { type: 'assets'; hash: string; css: string | null; js: string | null }

/** Where a block that is not on the page goes: by its neighbours, nearest first. */
export interface SiteBarPlace {
  after?: string[]
  before?: string[]
  /** It stands at the top level of its tree: with no neighbour on the page, at the tree's end. */
  top?: boolean
}

/** One tree of blocks the page may pick from. */
export interface SiteBarBlockSource {
  /** `{type}:{id}` of a record (`page:12`), `region:{name}` of a region of the layout. */
  scope: string
  /** Said on the frame beside the block's type — «Header» — when the block is not the page's own. */
  title?: string
  items: { key: string; label: string }[]
}

/**
 * What the page says to the panel: a block picked on it, picking switched off there (Esc),
 * «Add below» pressed on the picked block, and the sets of block styles and scripts the page has —
 * sent with an edit, so that a type the page lacks brings its own and one it has brings nothing.
 */
export type SitePageMessage =
  | { type: 'select'; key: string; scope?: string }
  | { type: 'mode'; on: boolean }
  | { type: 'add'; key: string; scope?: string }
  | { type: 'bundles'; hashes: string[] }
  /** The regions the page prints — the layout's, and the shared blocks placed on this page. */
  | { type: 'regions'; names: string[] }

/**
 * Listen to the page the panel is framed in. Only that page — the parent window, from this
 * origin — and only messages of the bar: anything else on the page can post messages too.
 * Returns the way to stop listening.
 */
export function onSitePage(listener: (message: SitePageMessage) => void): () => void {
  if (typeof window === 'undefined' || window.parent === window) return () => {}

  const handler = (event: MessageEvent): void => {
    if (event.origin !== window.location.origin || event.source !== window.parent) return

    const data = event.data as { wxBar?: unknown; type?: unknown } | null

    if (typeof data !== 'object' || data === null || data.wxBar !== 1) return
    if (
      data.type !== 'select' &&
      data.type !== 'mode' &&
      data.type !== 'add' &&
      data.type !== 'bundles' &&
      data.type !== 'regions'
    )
      return

    listener(data as SitePageMessage)
  }

  window.addEventListener('message', handler)

  return () => window.removeEventListener('message', handler)
}

/**
 * What the screen of the bar offers the tabs inside it: bring a tab forward (a block picked on
 * the page opens «Blocks»), and read the page's context again (an edit changes the draft the
 * head counts).
 */
export interface SiteBarHost {
  show(name: string): void
  refresh(): Promise<void>
}

export const siteBarKey: InjectionKey<SiteBarHost> = Symbol('wx-site-bar')

/** The bar around a tab; null outside one — a tab drawn in a test or a demo. */
export function useSiteBar(): SiteBarHost | null {
  return inject(siteBarKey, null)
}

/**
 * «Publish» in the head of the bar: the record's draft on the site. `revision` is the one the bar
 * held; an edit made since is answered 409 with who made it, and nothing is published.
 */
export function publishSiteBarEntity(
  http: Http,
  apiPath: string,
  entity: { type: string; id: number | string; revision?: string | null },
): Promise<unknown> {
  return http.post(`${apiPath}/site-bar/publish`, {
    type: entity.type,
    id: entity.id,
    ...(entity.revision ? { revision: entity.revision } : {}),
  })
}

/** «Discard»: the record's draft goes, and the record is what the site shows. */
export function discardSiteBarEntity(
  http: Http,
  apiPath: string,
  entity: { type: string; id: number | string },
): Promise<unknown> {
  return http.post(`${apiPath}/site-bar/discard`, { type: entity.type, id: entity.id })
}

/**
 * Say something to the page the panel is framed in. Only to that page's own origin: the screen
 * may be opened on its own, or framed by somebody else, and neither should hear it.
 */
export function tellSitePage(message: SiteBarMessage | 'close'): void {
  if (typeof window === 'undefined' || window.parent === window) return

  const body = typeof message === 'string' ? { type: message } : message

  window.parent.postMessage({ wxBar: 1, ...body }, window.location.origin)
}
