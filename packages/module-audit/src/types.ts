export type AuditSeverity = 'error' | 'warning' | 'notice'

/** `urls` is a recheck of a few addresses, started from the page's card. */
export type AuditScope = 'full' | 'quick' | 'urls'

export type AuditRunStatus = 'queued' | 'running' | 'done' | 'failed' | 'cancelled'

export type AuditStage = 'probes' | 'database' | 'crawl' | 'analyse'

/** What a run counted, once it is done. */
export interface AuditCounts {
  severity: Record<AuditSeverity, number>
  groups: Record<string, Record<AuditSeverity, number>>
  /** Ids of the checks that ran. */
  checks: string[]
  /** The worst severity of every check that found something. */
  failed: Record<string, AuditSeverity>
  /**
   * 0–100: the share of crawled pages without errors, minus 10 for each error of the whole site
   * and 2 for each check with warnings (20 at most).
   */
  health: number
  /** What the health was made of; absent on runs counted before it was kept. */
  health_parts?: AuditHealthParts
  new: number
  fixed: number
  previous_id: number | null
  sources: { searched: string[]; missing: string[] }
}

export interface AuditHealthParts {
  /** Pages crawled; 0 for a quick run. */
  pages: number
  /** Of them, without an error. */
  clean: number
  /** Checks with an error that belongs to no page. */
  site_errors: number
  /** Checks with a warning, each counted once. */
  warnings: number
}

export interface AuditRun {
  id: number
  status: AuditRunStatus
  scope: AuditScope
  base_url: string
  resolve_to: string | null
  /** The addresses of a recheck; empty for the other scopes. */
  urls: string[]
  progress: {
    stage: AuditStage | null
    done: AuditStage[]
    checks: number
    pages: { crawled: number; limit: number }
    /** The phase of the crawl: `seed`, `fetch`, `resources`, `checks`. */
    phase?: string | null
    /** While what the pages load is being asked. */
    resources?: { checked: number; total: number } | null
  }
  counts: AuditCounts | null
  started_by: string | null
  created_at: string | null
  started_at: string | null
  finished_at: string | null
  error: string | null
}

/** The overview's one call: the run that is going, the last that finished, and the queue. */
export interface AuditLatest {
  active: AuditRun | null
  done: AuditRun | null
  /** The last full run — the one with pages to list. */
  crawled: AuditRun | null
  /** A run that failed or was cancelled after the last good one. */
  last: AuditRun | null
  queue: { sync: boolean }
}

/** One check that found something, with its three texts in the reader's language. */
export interface AuditCheckRow {
  id: string
  group: string
  severity: AuditSeverity
  count: number
  new: number
  title: string
  found: string
  why: string
  fix: string
  /** The fixes that can close this check — the screen offers a button when there are any. */
  fixes: string[]
}

/** `code` is markup quoted from the page — the page's HTML itself is not kept. */
export type AuditCellType = 'url' | 'status' | 'bool' | 'text' | 'missing' | 'edit' | 'code'

export interface AuditDetailsColumn {
  key: string
  label: string
  type: AuditCellType
}

/** The expansion of a finding: data, drawn by one component for every check. */
export interface AuditDetails {
  summary: string | null
  table: { columns: AuditDetailsColumn[]; rows: Record<string, unknown>[] } | null
}

export interface AuditIssue {
  id: number
  check: string
  severity: AuditSeverity
  url: string | null
  state: 'new' | 'persisting'
  ignored: boolean
  /** The rule that hides it, on the list of hidden findings. */
  ignore?: AuditIgnoreRule | null
  /** The fix pressed on it; the finding stays until the next run says it is gone. */
  fixed_with: string | null
  fixed_at: string | null
  details: AuditDetails
}

/** One thing a fix changes: a field with its count of replacements, or a setting before and after. */
export interface AuditFixChange {
  label: string
  field?: string | null
  count?: number
  before?: string | null
  after?: string | null
  edit_url?: string | null
}

/** A fix that can close a finding, with what it would change. */
export interface AuditFixOffer {
  id: string
  title: string
  description: string
  changes: AuditFixChange[]
  total: number
  note: string | null
}

export interface AuditFixResult {
  id: string
  applied: boolean
  changes: AuditFixChange[]
  total: number
  note: string | null
}

export interface AuditIssueQuery {
  check?: string
  severity?: AuditSeverity | null
  group?: string | null
  /** `hidden` lists what the rules hide instead of what counts. */
  state?: 'new' | 'persisting' | 'hidden' | null
  page?: number
  per_page?: number
}

export interface AuditPage<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export type AuditPageSource = 'home' | 'sitemap' | 'registry' | 'link' | 'list'

export type AuditHostClass = 'own' | 'own_mirror' | 'dev' | 'external'

/** One crawled address as the pages screen lists it — every field of the snapshot it can show. */
export interface AuditPageRow {
  id: number
  url: string
  status: number | null
  final_status: number | null
  redirect_to: string | null
  source: AuditPageSource
  depth: number | null
  indexable: boolean
  issues: number
  title: string | null
  description: string | null
  h1: string | null
  canonical: string | null
  robots_meta: string | null
  x_robots_tag: string | null
  lang: string | null
  content_type: string | null
  bytes: number | null
  ttfb_ms: number | null
  total_ms: number | null
  compression: string | null
  word_count: number | null
  links_in: number
  links_out_internal: number
  links_out_external: number
  images: number
  images_without_alt: number
  in_sitemap: boolean
  in_registry: boolean
  blocked_by_robots: boolean
}

export type AuditPageField = Exclude<keyof AuditPageRow, 'id'>

/** How a field is drawn, filtered and compared. */
export type AuditFieldType = 'url' | 'text' | 'number' | 'bool' | 'status' | 'source'

export type AuditFilterOp = 'contains' | 'eq' | 'empty' | 'filled' | 'gt' | 'lt' | 'yes' | 'no'

export interface AuditFieldFilter {
  field: AuditPageField
  op: AuditFilterOp
  value?: string
}

export interface AuditPageQuery {
  search?: string
  /** `2xx`, `3xx`, `4xx`, `5xx` or `none` — nothing answered. */
  status?: string | null
  indexable?: boolean | null
  /** Pages with a finding of this check. */
  check?: string | null
  filters?: AuditFieldFilter[]
  /** A field, `-field` for descending. */
  sort?: string | null
  page?: number
  per_page?: number
}

/** The page's card: the whole snapshot, its findings and how many links lead in and out. */
export interface AuditPageCard {
  page: AuditPageRow & {
    headers: Record<string, string>
    h1: string[]
    headings: Record<string, number>
    hreflang: { lang: string; url: string }[]
    og: Record<string, string>
    twitter: Record<string, string>
    json_ld: AuditJsonLdBlock[]
    error: string | null
    facts: Record<string, unknown>
    fetched_at: string | null
  }
  issues: AuditIssue[]
  counts: {
    issues: number
    incoming: number
    outgoing: number
    images?: number
    css?: number
    js?: number
    microdata?: number
  }
}

/** One JSON-LD block of a page: what it is, and what the types search engines show lack. */
export interface AuditJsonLdBlock {
  types: string[]
  error: string | null
  items?: { type: string; missing: string[]; recommended: string[] }[]
  /** The block as the page printed it, cut. */
  source?: string
}

/** The tabs of the card that list what the page loads. */
export type AuditResourceTab = 'images' | 'css' | 'js'

/** Something a page loads, with what it answered when the run asked. */
export interface AuditResourceRow {
  id: number
  url: string
  kind: string
  alt: string | null
  host_class: AuditHostClass | null
  /** False past the run's limit, and for a stand's address — it is not asked. */
  checked: boolean
  status: number | null
  error: string | null
  location: string | null
  content_type: string | null
  bytes: number | null
  cache_control: string | null
  compression: string | null
  width: number | null
  height: number | null
}

/** A link of a page: where it leads (out) or where it comes from (in). */
export interface AuditLinkRow {
  id: number
  url: string | null
  page_id: number | null
  status: number | null
  kind: string
  anchor: string | null
  rel: string | null
  target: string | null
  host: string | null
  host_class: AuditHostClass | null
  absolute: boolean
}

/** A rule that hides findings on purpose (decision 9): a check, an address or a mask, and why. */
export interface AuditIgnoreRule {
  id: number
  check: string
  /** An address, a mask (`/search/**`), or empty for every finding of the check. */
  pattern: string
  reason: string
  created_by: string | null
  created_at: string | null
  /** The check's title in the reader's language — in the list of rules. */
  title?: string
  /** How many stored findings it hides — in the list of rules. */
  hidden?: number
}

export type AuditComparisonKind = 'new' | 'persisting' | 'fixed'

/** One check of two runs compared by fingerprint. */
export interface AuditComparisonRow {
  check: string
  title: string
  severity: AuditSeverity
  new: number
  persisting: number
  fixed: number
}

export interface AuditComparison {
  from: AuditRun
  to: AuditRun
  checks: AuditComparisonRow[]
}

/** One host the site points at, on the «Outgoing» screen. */
export interface AuditHostRow {
  host: string
  class: AuditHostClass
  links: number
  pages: number
  /** Links that answered 4xx, 5xx or nothing. */
  broken: number
  nofollow: number
  /** Fields in the database that hold an address on it. */
  fields: number
  /** The earliest run among the snapshots kept that saw it. */
  first_seen: string | null
}

/** A link to a host from a crawled page. */
export interface AuditHostPage {
  page_id: number
  page: string
  url: string
  kind: string
  anchor: string | null
  rel: string | null
  status: number | null
}

/** A field of the database that holds an address on a host. */
export interface AuditHostField {
  source: string
  record_id: string
  record_label: string
  field: string
  locale: string | null
  url: string
  published: boolean | number
  edit_url: string | null
}

export interface AuditHosts {
  crawled_run: number | null
  database_run: number | null
  classes: Partial<Record<AuditHostClass, number>>
  hosts: AuditHostRow[]
  /** With `host`: where it stands. */
  pages?: AuditHostPage[]
  fields?: AuditHostField[]
}
