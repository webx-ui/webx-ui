import type { LocalizedValue } from '@webx-ui/core'

/** The shapes `webx-ui/module-seo` answers with, and what its forms send back. */

/** How a pattern is compared with an address. */
export type MatchType = 'exact' | 'mask' | 'regex'

/** What `wx-media` stores, plus the address the server works out on every read. */
export interface SeoImage {
  path: string
  alt?: string
  title?: string
  url?: string | null
}

/**
 * What a page says about itself.
 *
 * The text fields are language maps — the same `{ en: '…', ru: '…' }` every localized field in
 * the panel uses. The picture is not: there are no per-language images anywhere yet, and a
 * column that already holds an object would read `path` as a language code.
 */
export interface SeoFields {
  title: LocalizedValue
  h1: LocalizedValue
  description: LocalizedValue
  keywords: LocalizedValue
  og_title: LocalizedValue
  og_description: LocalizedValue
  og_image: SeoImage | null
  canonical: string | null
  robots: string | null
  /** A JSON-LD object, a list of them, or null. */
  json_ld: unknown
}

/**
 * The value of a `wx-seo` field: the same fields, none of them required.
 *
 * A record that has never been given any SEO stores nothing rather than a shape full of nulls.
 */
export type SeoValue = Partial<SeoFields>

export interface SeoUrlRule extends SeoFields {
  id: number
  match_type: MatchType
  pattern: string
  priority: number
  is_active: boolean
  created_at: string | null
  updated_at: string | null
  /**
   * What a table row is, as far as `WxTable` is concerned. Without it this type does not
   * extend `TableRow` and every cell slot hands back `unknown`.
   */
  [key: string]: unknown
}

/** A `PUT` replaces the whole rule: the form edits every field at once. */
export interface SeoUrlInput extends SeoValue {
  match_type: MatchType
  pattern: string
  priority?: number
  is_active?: boolean
}

export interface SeoRedirect {
  id: number
  match_type: MatchType
  pattern: string
  target: string
  status: number
  is_active: boolean
  hits: number
  last_hit_at: string | null
  /** The server says so rather than refusing the row: a loop is skipped, not rejected. */
  is_loop: boolean
  created_at: string | null
  updated_at: string | null
  [key: string]: unknown
}

/**
 * An address a rename left behind, from `webx-ui/routing`.
 *
 * The same words a manual redirect uses where the two overlap, so one screen can show both
 * without learning two vocabularies. What it has instead of the rest is an entity: these are
 * written by whatever moved, always exact, always 301, and never edited here.
 */
export interface SeoAlias {
  id: number
  locale: string
  /** The old address. */
  pattern: string
  /** The address it leads to now; null when the row it pointed at is gone. */
  target: string | null
  url: string
  target_url: string | null
  /** `page`, `article`, `product` — what the content module called itself. */
  entity_type: string
  entity_id: number
  created_at: string | null
  [key: string]: unknown
}

export interface SeoAliasQuery {
  q?: string
  locale?: string | null
  page?: number
  per_page?: number
}

/**
 * What the address registry holds at an address, when something does.
 *
 * A rule written by hand is tried before any of it — that is deliberate — so this is a warning
 * and never a refusal: the page at this address is about to stop being reachable.
 */
export interface SeoRoute {
  path: string
  url: string
  kind: 'canonical' | 'alias'
  /** Where an old address leads now; null on a live page, which has nowhere to lead. */
  target: string | null
  entity_type: string
  entity_id: number
  /** False when a shorter address matched: the page at `/parts` answering for `/parts/bobcat`. */
  exact: boolean
}

export interface SeoRedirectInput {
  match_type: MatchType
  pattern: string
  target: string
  status?: number
  is_active?: boolean
}

/**
 * A page, flat — what `->paginate()` serialises and `WxTable` reads as it arrives. A resource
 * collection nests the same numbers under `meta`; the client flattens them once, here.
 */
export interface SeoPage<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface SeoUrlQuery {
  q?: string
  match_type?: MatchType | null
  is_active?: boolean | null
  page?: number
  per_page?: number
}

export interface SeoRedirectQuery extends SeoUrlQuery {
  sort?: string
}

/** What a page ends up saying, every source merged. */
export interface SeoResolved {
  title: string | null
  h1: string | null
  description: string | null
  keywords: string | null
  canonical: string | null
  robots: string | null
  og: Record<string, string>
  json_ld: Record<string, unknown>[]
}

/** One source's contribution, before the merge. */
export interface SeoChainStep {
  source: string
  priority: number
  data: SeoResolved
}

/** The answer to "why does this page say that". */
export interface SeoTestResult {
  url: string
  /** Said first because it happens first: a redirected address never reaches the rules. */
  redirect: SeoRedirect | null
  /** What the address registry has here — a live page, or the trail of one that moved. */
  route: SeoRoute | null
  matched: SeoUrlRule | null
  chain: SeoChainStep[]
  seo: SeoResolved
  /** Whether the address is in the sitemap, and why not — the first question when a page is missing from a search engine. */
  sitemap: SeoSitemapVerdict
}

/** Why an address is not in the sitemap. `null` when it is. */
export type SeoSitemapReason = 'disabled' | 'unknown' | 'alias' | 'hidden' | 'noindex' | 'canonical'

export interface SeoSitemapVerdict {
  included: boolean
  reason: SeoSitemapReason | null
}

/**
 * The sitemap as a crawler gets it: the card above the rules. `files` counts the addresses in
 * each file of the map; `excluded` counts the visible ones the resolver closed, by why.
 */
export interface SeoSitemapStatus {
  enabled: boolean
  url: string
  built_at: string | null
  files: Record<string, number>
  total: number
  excluded: { noindex: number; canonical: number }
}

/** The meta directives the card offers as checkboxes. Anything else is kept as written. */
export const robotsDirectives = [
  'noindex',
  'nofollow',
  'noarchive',
  'nosnippet',
  'noimageindex',
] as const

export type RobotsDirective = (typeof robotsDirectives)[number]

/* ------------------------------------------------------------------ interlinking (§18.4) -- */

/**
 * An address of the site as interlinking keeps it: bound to an entity when the registry knew
 * one, a path when it did not. `url` is where it leads now, `saved_url` what was written — the
 * two differ once a bound page has been renamed.
 */
export interface SeoLinkTarget {
  url: string
  saved_url: string
  locale: string
  entity_type: string | null
  entity_id: number | null
  /** The entity is gone or hidden, or nothing on the site answers the path. */
  broken: boolean
}

export interface SeoLinkItem {
  id: number
  acceptor: SeoLinkTarget
  anchor: string
  position: number
}

/** One donor and its block. The list leaves `items` out; a single block carries them. */
export interface SeoLinkBlock {
  id: number
  donor: SeoLinkTarget
  heading: string | null
  is_active: boolean
  links_count: number
  broken_count: number
  updated_at: string | null
  items?: SeoLinkItem[]
}

/** A problem with one line of a file or one link of a form. */
export interface SeoLinkProblem {
  /** The line of the file (the header is the first), or the index of the link in a form. */
  line: number
  field: 'donor' | 'acceptor' | 'anchor' | string
  code: string
  level: 'error' | 'warning'
  message: string
}

/** What a save answers with: the block, and what was replaced on the way (`redirected`). */
export interface SeoLinkSaved extends SeoLinkBlock {
  warnings: SeoLinkProblem[]
}

export interface SeoLinkInput {
  donor: string
  locale?: string | null
  heading: string | null
  is_active: boolean
  items: { acceptor: string; anchor: string }[]
}

export interface SeoLinkQuery {
  q?: string
  /** Only donors with at least one broken link. */
  broken?: boolean
  page?: number
  per_page?: number
}

export type SeoLinkImportMode = 'replace' | 'append'

/** A preview of an import, or the report of one that was applied. */
export interface SeoLinkImportResult {
  ok: boolean
  applied: boolean
  mode: SeoLinkImportMode
  donors: number
  links: number
  created: number
  replaced: number
  appended: number
  errors: number
  problems: SeoLinkProblem[]
  blocks: {
    donor: string
    links: number
    action: 'create' | 'replace' | 'append'
    heading: string | null
  }[]
}

/** One heading for many donors: the ticked ones, or every one under a prefix. */
export interface SeoLinkHeadingInput {
  ids?: number[]
  prefix?: string
  heading: string | null
  dry_run: boolean
}

export interface SeoLinkHeadingResult {
  ok: boolean
  applied: boolean
  count: number
  heading: string | null
  donors: string[]
}

/** An address the registry suggests for an acceptor. */
export interface SeoLinkAddress {
  url: string
  locale: string
  entity_type: string | null
  entity_id: number | null
}
