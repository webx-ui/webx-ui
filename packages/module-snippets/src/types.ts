import type { ScreenModel } from '@webx-ui/schema'

/**
 * The categories of the consent widget a code can wait for (decision 1 of the spec): the
 * widget's own names, so nothing is translated between the two. `statistics` is what the
 * interface calls "Analytics".
 */
export type SnippetConsent = 'necessary' | 'statistics' | 'marketing' | 'media'

/** One code as a row of the list and the head of its editor (`SnippetForm::row()`). */
export interface SnippetRow {
  id: number
  /** For administrators only; the site never shows it. */
  name: string
  place: string
  /** False for a place taken out of the config: the code is printed nowhere (§4.3). */
  place_declared: boolean
  consent: SnippetConsent
  /** Path masks; empty — the whole site. */
  only_paths: string[]
  except_paths: string[]
  /** Empty — every language. */
  locales: string[]
  /** Printed outside production too. */
  on_stage: boolean
  enabled: boolean
  /** Switched on, and still not printed on this copy of the site: it is not `on_stage` (§5.5). */
  silent_here?: boolean
  /** Its place in the order of its place, from zero. */
  position: number
  /** The size of the code — the limit is in bytes, not in characters. */
  bytes: number
  updated_at: string | null
  deleted_at: string | null
}

/**
 * A place and its codes, in the order of the config: the three built-in ones first, the site's
 * own after, then the places nobody declares any more that still hold codes.
 */
export interface SnippetPlace {
  key: string
  label: string
  built_in: boolean
  /** False — taken out of `config/webx-snippets.php`: printed nowhere, codes kept. */
  declared: boolean
  /** The day a page last printed the place (§10.3); `null` — not since the cache was cleared. */
  last_seen: string | null
  snippets: SnippetRow[]
}

export interface SnippetsList {
  data: SnippetPlace[]
  /** `APP_ENV` of the site. */
  environment: string
  /** False outside the environments that print everything: only the codes marked "on dev too". */
  everything_printed: boolean
}

/** One code as its editor opens it — and as a save answers. */
export interface SnippetDetail {
  snippet: SnippetRow
  values: ScreenModel
  /**
   * What to look at in the code — the tags of a whole document (§7.4). A save goes through with
   * them; the editor says so above the code.
   */
  warnings: string[]
}
