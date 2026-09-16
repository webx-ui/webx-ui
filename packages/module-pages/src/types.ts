import type { TreeDropZone } from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'

/** Never published · on the site · on the site with edits waiting. */
export type PageStatus = 'draft' | 'published' | 'modified'

/** What a page may have done to it, as the server decides it — the home page may not be moved. */
export interface PageCapabilities {
  move: boolean
  delete: boolean
  address: boolean
}

/**
 * One page as the section lists it.
 *
 * The title is the draft's and the address is the registry's, which is why a renamed page can
 * show a new name beside its old address: that is what the site is serving until it is
 * published.
 */
export interface PageRow {
  id: number
  parent_id: number | null
  depth: number
  is_home: boolean
  title: string
  slug: string
  /** `null` — this page names no address in the language the panel is open in. */
  path: string | null
  url: string | null
  status: PageStatus
  published_at: string | null
  updated_at: string | null
  /** Who wrote the newest version of it. */
  edited_by: string | null
  children_count: number
  /** Everything below it, at any depth — what a delete takes with it. */
  descendants_count: number
  deleted_at: string | null
  /** Which page's deletion put this one in the bin. */
  trashed_with: number | null
  can: PageCapabilities
  /** Filled in by the table as branches are opened. */
  children?: PageRow[]
  // A row of `WxTable`, which reads its cells by name; without this the table falls back on
  // its own `TableRow` and every slot hands back `unknown`.
  [key: string]: unknown
}

/** A level of the tree, with the home page pinned beside it rather than inside it. */
export interface PageLevel {
  home: PageRow | null
  items: PageRow[]
}

export interface PageQuery {
  /** The parent whose children are wanted. Left out — the home page's. */
  parent?: number | null
  search?: string
  status?: PageStatus | ''
  trashed?: boolean
  /** The whole tree at once, flat — what a phone asks for. */
  flat?: boolean
}

export interface PageInput {
  title: string
  slug?: string
  parent_id?: number | null
}

/** A page and the trail above it, for the breadcrumbs of a form. */
export interface PageDetail {
  page: PageRow
  ancestors: PageRow[]
  /** The values of `pages.form`, keyed by field name. */
  values: ScreenModel
  /**
   * The page as the editor read it, as a short string. It travels back with every save, and a
   * save whose revision is not the current one is refused with a 409 rather than written over
   * whoever saved in between.
   */
  revision: string
  /**
   * The address of the page above, by content language — what the whole address of this page
   * is made of, less its own last segment. A language that is not in here is one the page above
   * has no address in, and so neither has this one (§8).
   */
  address_prefix: Record<string, string>
  /** A signed, short-lived link to the draft as a page of the site. */
  preview_url: string
}

/** What a `PUT` carries: the values of the screen, and the page they were read from. */
export interface PageSave {
  values: ScreenModel
  revision?: string
}

/** A 409: somebody wrote while this editor was typing. The page comes back as it now is. */
export interface PageConflict {
  message: string
  data: PageDetail
}

/** One publication in the history. */
export interface PageVersion {
  number: number
  created_at: string | null
  /** Who published it; `null` for an agent or an import with nobody behind it. */
  author: string | null
  source: 'panel' | 'mcp' | 'import'
  comment: string | null
  is_pinned: boolean
}

export interface PageMoveResult {
  page: PageRow
  /** How many addresses the move rewrote — the page and everything under it. */
  addresses_changed: number
}

export type PageDropZone = TreeDropZone
