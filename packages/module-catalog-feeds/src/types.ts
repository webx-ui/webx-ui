import type { MustacheVariable } from '@webx-ui/core'

export type FeedFormat = 'xml' | 'json' | 'csv' | 'text'

export type BuildStatus = 'queued' | 'running' | 'done' | 'failed'

/** What the list says of a feed's last build (§7.6 of the feeds spec). */
export interface FeedBuildState {
  status: BuildStatus
  written: number
  finished_at: string | null
}

export interface FeedRow {
  id: number
  name: string
  preset: string | null
  format: FeedFormat
  driver: string | null
  locale: string
  currency: string
  schedule: string
  is_active: boolean
  /** With its key: `null` without `catalog.manage`. */
  address: string | null
  last_build: FeedBuildState | null
  updated_at: string | null
}

export interface FeedsPage {
  data: FeedRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

/** §5.1 of the feeds spec. */
export interface FeedFilter {
  categories?: { include?: number[]; exclude?: number[] }
  brands?: (string | number)[]
  price?: { min?: number | null; max?: number | null }
  labels?: string[]
  properties?: Record<string, string[] | { min?: number | null; max?: number | null }>
  only_in_stock?: boolean
}

/** One of the five `custom_label_*` (§3.3). */
export interface LabelSource {
  type: string
  labels?: string[]
  ranges?: { to?: number | null; value: string }[]
  property?: string
  value?: string
}

/** Where `mpn` comes from (§3.4). */
export interface MpnSource {
  type: 'field' | 'property'
  property?: string
}

/** What the form edits and sends. */
export interface FeedValues {
  name: string
  preset: string | null
  format: FeedFormat
  locale: string
  currency?: string
  head: string | null
  item: string | null
  foot: string | null
  separator: string
  filter: FeedFilter
  stock_map: Record<string, string>
  taxonomy: string | null
  custom_labels: Record<string, LabelSource>
  sources: { mpn?: MpnSource }
  options: Record<string, unknown>
  schedule: string
  schedule_at: string | null
  rebuild_on_change: boolean
  is_active: boolean
}

export interface FeedDetail extends FeedRow, Omit<FeedValues, 'preset' | 'format' | 'locale'> {
  currency: string
  key_issued_at: string
}

export interface FeedWarning {
  code: string
  count: number
  message: string | null
}

export interface FeedSaved {
  data: FeedDetail
  warnings: FeedWarning[]
}

export interface FeedPreset {
  key: string
  name: string
  description: string
  values: Partial<FeedValues>
}

export interface FeedsMeta {
  presets: FeedPreset[]
  formats: FeedFormat[]
  label_sources: string[]
  /** The site's: a feed has no other (§8.1). */
  currency: string
  /** Whether the stock satellite is there: without it, no «only in stock». */
  stock: boolean
  stock_statuses: StockStatusRow[]
}

/** The registry by part: a head and a foot have no product (§4.6). */
export interface FeedVariables {
  item: MustacheVariable[]
  frame: MustacheVariable[]
}

export interface FeedFacet {
  key: string
  label: string
  kind: 'terms' | 'range' | 'toggle' | 'tree'
  /** A text property: nothing to choose from, the value is typed. */
  typed: boolean
  values: { value: string; label: string }[]
}

export interface FeedCount {
  selected: number
  excluded: number
}

/** A mistake of a template, where it is known (§8.1). */
export interface FeedProblem {
  field: string
  message: string
  line?: number | null
  part?: string | null
  part_line?: number | null
  product?: number | null
  fragment?: string | null
}

/** What `POST sample` hands the browser to render with (§8.3). */
export interface FeedSample {
  products: {
    id: number
    name: string
    sku: string | null
    values: Record<string, unknown>
  }[]
  frame: Record<string, unknown>
  errors: FeedProblem[]
}

/** What `POST preview` says: the server's own sample and its verdict. */
export interface FeedTrial {
  ok: boolean
  text: string
  errors: FeedProblem[]
  warnings: FeedWarning[]
  selected: number | null
  excluded: number | null
  looked_at: number
  written: number
  rejected: Record<string, number>
  filtered: number
  products: { id: number; written: boolean; reason: string | null }[]
}

export interface ExcludedProduct {
  id: number
  name: string
  sku: string | null
}

export interface ExclusionsPage {
  data: ExcludedProduct[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface MappedValue {
  value: string | null
  label: string | null
}

/** A row of the tree of our categories against the marketplace's (§6.2). */
export interface MappedCategory extends MappedValue {
  id: number
  parent_id: number | null
  name: string
  taxonomy_item_id: number | null
  inherited: (MappedValue & { category_id: number }) | null
}

export interface TaxonomyState {
  key: string
  locale: string
  address: string | null
  list_locale: string | null
  /** The list is in another language than the feed: the labels are English. */
  fallback: boolean
  version: string | null
  imported_at: string | null
  items: number
  status: string | null
  error: string | null
}

export interface MappedCategories {
  data: MappedCategory[]
  unmapped: number
  taxonomy: TaxonomyState | null
}

export interface TaxonomyItem {
  id: number
  external_id: string
  path: string
}

export interface FeedBuildRow {
  id: number
  status: BuildStatus
  trigger: string
  queued_at: string | null
  started_at: string | null
  finished_at: string | null
  selected: number
  written: number
  filtered: number
  excluded: number
  rejected: Record<string, number>
  warnings: Record<string, number>
  error: string | null
  bytes: number | null
  /** The one being served. */
  current: boolean
}

export interface FeedBuildDetail extends FeedBuildRow {
  rejects: { product_id: number; name: string; sku: string | null; reason: string }[]
}

export interface FeedBuildsPage {
  data: FeedBuildRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  reissues: { at: string | null; by: string | null }[]
}

export interface StockStatusRow {
  code: string
  title: string
  is_purchasable: boolean
}
