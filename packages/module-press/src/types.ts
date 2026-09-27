import type { ScreenModel } from '@webx-ui/schema'

/**
 * One outlet as the section lists it (§4.10).
 *
 * `locales` is where it can be seen — the languages at least one of its articles has a title in —
 * and it is a separate thing from `published` on purpose: "published, and seen nowhere" is the
 * row the list has to point out (decision 7).
 */
export interface OutletRow {
  id: number
  /** In the language the panel is open in, in any other where there is none, `#id` where none. */
  title: string
  logo: { thumb: string | null } | null
  published: boolean
  /** In the logo strip (decision 13). */
  featured: boolean
  /** Its place in the whole list. */
  position: number
  locales: string[]
  articles_count: number
  updated_at: string | null
  deleted_at: string | null
}

/** Every outlet — no pages: the list is where outlets are put in order (decision 6). */
export interface OutletsList {
  data: OutletRow[]
}

export interface OutletQuery {
  /** The name, in any language the site has. */
  search?: string
  /** What was deleted. The only way back to an outlet in the bin is through this list. */
  trashed?: boolean
}

/** The record half of the form's answer — what the head of the form needs. */
export interface OutletSummary {
  id: number
  title: string
  published: boolean
  deleted_at: string | null
  /** Its page on the site in the panel's language; `null` when it has none there or at all. */
  url: string | null
}

/**
 * One outlet as its form opens it: the record, the values of `press.outlet-form` by field name —
 * its articles among them, as rows in their order — and where its addresses start (`null` when
 * the site has no outlet pages, `pages = false`).
 */
export interface OutletDetail {
  outlet: OutletSummary
  values: ScreenModel
  prefix: string | null
}

/** One row of `articles` in the form's values (§4.10). */
export interface ArticleValues {
  /** Absent on a row not saved yet: the server makes it then. */
  id?: number
  title: Record<string, string>
  excerpt: Record<string, string>
  kind: string | null
  /** `Y-m-d`. */
  published_on: string | null
  date_precision: 'day' | 'month' | 'year'
  url: string | null
  file: { path: string } | null
  is_hidden: boolean
  [field: string]: unknown
}
