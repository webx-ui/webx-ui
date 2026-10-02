import type { AuditFieldType, AuditFilterOp, AuditPageField } from './types'

/**
 * The fields of the snapshot, as `PageColumns` lists them on the server: what the pages screen
 * can show, filter on, sort by and export. Kept in the same order, so the column picker reads
 * like the page's card — address and answer first, markup, then speed and links.
 */
export const PAGE_FIELDS: Record<AuditPageField, AuditFieldType> = {
  url: 'url',
  status: 'status',
  final_status: 'status',
  redirect_to: 'url',
  source: 'source',
  depth: 'number',
  indexable: 'bool',
  issues: 'number',
  title: 'text',
  description: 'text',
  h1: 'text',
  canonical: 'url',
  robots_meta: 'text',
  x_robots_tag: 'text',
  lang: 'text',
  content_type: 'text',
  bytes: 'number',
  ttfb_ms: 'number',
  total_ms: 'number',
  compression: 'text',
  word_count: 'number',
  links_in: 'number',
  links_out_internal: 'number',
  links_out_external: 'number',
  images: 'number',
  images_without_alt: 'number',
  in_sitemap: 'bool',
  in_registry: 'bool',
  blocked_by_robots: 'bool',
}

export const DEFAULT_COLUMNS: AuditPageField[] = [
  'url',
  'status',
  'indexable',
  'title',
  'word_count',
  'links_in',
  'issues',
]

/** `h1` is read out of a JSON list: shown and exported, not filtered or sorted on. */
export const COMPUTED_FIELDS: AuditPageField[] = ['h1']

/** What can be asked of a field of each type. */
export const OPERATIONS: Record<AuditFieldType, AuditFilterOp[]> = {
  url: ['contains', 'eq', 'empty', 'filled'],
  text: ['contains', 'eq', 'empty', 'filled'],
  number: ['gt', 'lt', 'eq', 'empty'],
  status: ['eq', 'gt', 'lt', 'empty'],
  bool: ['yes', 'no'],
  source: ['eq'],
}

/** The operations that need a value typed beside them. */
export const WITH_VALUE: AuditFilterOp[] = ['contains', 'eq', 'gt', 'lt']

export const SOURCES = ['home', 'sitemap', 'registry', 'link'] as const
