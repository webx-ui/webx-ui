import type { LocalizedValue } from '@webx-ui/core'

/** One facet of a set: values of a list, or the ends of a range (§4 of the landings spec). */
export type SetChoice = { values: string[] } | { min: number | null; max: number | null }

/** A landing's set as the server stores it: facet key → choice. */
export type LandingFilters = Record<string, SetChoice>

/** «Brand: Apple, Dell» — a facet of the set as words. */
export interface LandingChip {
  key: string
  label: string
  text: string
}

export type LandingAttention = 'value_removed' | 'duplicate' | 'empty_set'

/** A row of the list. */
export interface LandingRow {
  id: number
  category_id: number | null
  /** The base's name in the panel's language; `null` — the whole catalogue. */
  category: string | null
  name: LocalizedValue
  slug: LocalizedValue
  url: string | null
  filters: LandingFilters
  chips: LandingChip[]
  /** `null` — not counted yet. */
  products_count: number | null
  counted_at: string | null
  is_published: boolean
  on_category: boolean
  position: number
  attention: LandingAttention | null
  deleted_at: string | null
  updated_at: string | null
  /** A row of `WxTable`, which reads cells by key. */
  [key: string]: unknown
}

/** A recommended product, named. */
export interface RecommendedItem {
  id: number
  name: string
  sku: string | null
  deleted?: boolean
}

/** A landing as the form reads it. */
export interface LandingDetail extends LandingRow {
  h1: LocalizedValue
  text_above: LocalizedValue
  text_below: LocalizedValue
  sort: string | null
  recommended: number[]
  recommended_items: RecommendedItem[]
  seo: Record<string, unknown>
}

export interface LandingsPage {
  data: LandingRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface LandingQuery {
  q?: string
  /** A category's id, or `root` — the landings of the whole catalogue. */
  category?: string
  attention?: boolean
  published?: boolean
  empty?: boolean
  trashed?: boolean
  page?: number
  per_page?: number
}

/** A facet a base offers the set, with its values counted (`GET facets?category=`). */
export interface BaseFacet {
  key: string
  label: string
  kind: string
  min: number | null
  max: number | null
  values: { value: string; label: string; count: number }[]
}

/** What `POST count` answers: the number, the landing holding the set, the suggested slugs. */
export interface SetCount {
  count: number
  taken: { id: number; name: string } | null
  suggested: Record<string, string>
}

/** What «Create in bulk» is asked (§8.3). */
export interface GenerateParams {
  categories: number[]
  subtree?: boolean
  facet: string
  values?: string[] | null
  min_products?: number | null
  slug?: string
  name?: string
  h1?: string
  title?: string
  description?: string
  publish?: boolean
}

export type GenerateConflict = 'slug-taken' | 'set-taken' | 'empty' | 'no-slug'

/** A row of the preview. */
export interface GenerateRow {
  category_id: number
  category: string
  value: string
  label: string
  slug: Record<string, string>
  name: Record<string, string>
  h1: Record<string, string>
  count: number
  conflict: GenerateConflict | null
  message: string | null
  [key: string]: unknown
}

export interface GeneratePreview {
  rows: GenerateRow[]
  total: number
  free: number
}

/** A generation: done at once (`id` null) or queued and polled. */
export interface GenerateRun {
  id: number | null
  status: 'queued' | 'running' | 'done' | 'failed'
  total: number
  done: number
  skipped: number
  failed: number
  errors: { slug: string; name: string; message: string }[]
  created_at: string | null
  finished_at: string | null
}
