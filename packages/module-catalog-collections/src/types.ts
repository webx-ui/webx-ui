import type { LocalizedValue } from '@webx-ui/core'
import type { BaseFacet, FacetSetValue } from '@webx-ui/module-catalog'

export type { BaseFacet, FacetSetValue }

export type CollectionSource = 'manual' | 'filter'
export type CollectionView = 'grid' | 'carousel'
export type CollectionAttention = 'value-deleted'

/** A row of the list. */
export interface CollectionRow {
  id: number
  key: string
  title: LocalizedValue
  source: CollectionSource
  /** The preset the fields still answer to; `null` — the manager's own conditions. */
  preset: string | null
  view: CollectionView
  limit: number
  products_count: number
  /** The blocks that place it; `null` — the site has no blocks, so nothing is counted. */
  places: number | null
  is_enabled: boolean
  attention: CollectionAttention | null
  deleted_at: string | null
  updated_at: string | null
  /** A row of `WxTable`, which reads cells by key. */
  [key: string]: unknown
}

/** A page or a region whose content places a collection (§6.4). */
export interface CollectionPlace {
  model: string
  id: number | string
  title: string | null
  published: boolean
  /** In what the site shows. */
  live: boolean
  /** In the draft. */
  draft: boolean
  url: string | null
  region: string | null
}

/** A hand-picked product, named. */
export interface CollectionItem {
  id: number
  name: string
  sku: string | null
  deleted?: boolean
}

/** A collection as the form reads it. */
export interface CollectionDetail extends CollectionRow {
  category_id: number | null
  filters: FacetSetValue
  /** What the chosen values are called: facet key → value → word. */
  filter_labels: Record<string, Record<string, string>>
  on_sale: boolean
  sort: string | null
  only_purchasable: boolean
  link: Record<string, unknown> | null
  link_text: LocalizedValue
  products: number[]
  product_items: CollectionItem[]
}

export interface CollectionsPage {
  data: CollectionRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface CollectionQuery {
  q?: string
  attention?: boolean
  /** `false` — only the ones turned off. */
  enabled?: boolean
  trashed?: boolean
  page?: number
  per_page?: number
}

/** A ready way of filling a collection: it writes ordinary fields and is not stored (§3.4). */
export interface CollectionPreset {
  key: string
  sort: string | null
  on_sale: boolean
  /** The facet whose values the manager picks; `null` for none. */
  facet: string | null
}

/** What `GET facets` answers. */
export interface CollectionFacets {
  data: BaseFacet[]
  presets: CollectionPreset[]
}

export interface PreviewProduct {
  id: number
  name: string
  sku: string | null
  image: string | null
  /** Left out where the catalogue hides its prices. */
  price?: number | null
  old_price?: number | null
}

export type DropReason =
  'over-limit' | 'gone' | 'in-bin' | 'unpublished' | 'hidden' | 'no-language' | 'status'

/** A hand-picked product that will not reach the site. */
export interface DroppedProduct {
  id: number
  name: string
  sku: string | null
  reason: DropReason
  /** The status's own name, when that is the reason. */
  status: string | null
}

/** What `POST preview` answers (§6.3). */
export interface CollectionPreview {
  products: PreviewProduct[]
  count: number
  /** More than `count` fit: a filter stops counting past the ceiling. */
  more: boolean
  limit: number
  dropped: DroppedProduct[]
}

/** A row of the panel's relation target `catalog-collection`. */
export interface CollectionCandidate {
  id: number
  title: string
  subtitle: string | null
  thumb: string | null
  visible: boolean
  trashed?: boolean
  key?: string
}
