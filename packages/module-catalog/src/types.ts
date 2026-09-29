import type { LocalizedValue, Paginated } from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'

/**
 * Published · taken off the site · in «Deleted» (§5). "Published" is what was asked for, not what
 * a visitor sees: a published product in no visible category answers like an unpublished one,
 * and `visible` on the row is the one that says so.
 */
export type ProductState = 'published' | 'unpublished' | 'deleted'

/** A category as a product names it — possibly one in the bin, which a restore cannot bring along. */
export interface CategoryRef {
  id: number
  name: string
  deleted: boolean
}

/**
 * One product as a row of the list and as the head of its editor (§11.2).
 *
 * `price`, `old_price` and `barcode` are absent — not null — when the site has them switched off,
 * so the panel cannot show a column the site does not have.
 */
export interface ProductRow {
  id: number
  /** In the panel's language, with the usual fallback. */
  name: string
  sku: string | null
  barcode?: string | null
  price?: number | null
  old_price?: number | null
  unit: string | null
  priority: number
  is_published: boolean
  state: ProductState
  /** Published and in at least one visible category (§5). */
  visible: boolean
  /** The main one — `null` is decision 3's "no category". */
  category: CategoryRef | null
  image: { id: number; url: string; thumb: string | null } | null
  /** On the site; an unpublished one's too, which is the trimmed page. `null` once deleted. */
  url: string | null
  created_at: string | null
  updated_at: string | null
  deleted_at: string | null
  /** The satellites' columns (`ProductColumns`), by key. Absent while none is installed. */
  columns?: Record<string, string | number | boolean | null>
  /** A row of `WxTable`, which reads cells by key. */
  [key: string]: unknown
}

/** A column a satellite adds to the list (§7.4). The values ride on each row under its key. */
export interface ProductColumnInfo {
  key: string
  label: string
  /** The key of the `Sorts` registry that orders by it, or `null`. */
  sort?: string | null
}

/**
 * What one facet counts for the list as it is filtered, its own choice aside: the values of a
 * terms or tree facet with their words and counts, the bounds of a range, the products a toggle
 * would leave.
 */
export interface FacetCount {
  key: string
  kind: FacetKind
  values?: { value: string; label: string; count: number }[]
  min?: number | null
  max?: number | null
  count?: number
}

/**
 * The list: Laravel's paginator as it is, with the number of products nobody filed (decision 3),
 * the facets' counts and the satellites' columns.
 */
export interface ProductsPage extends Paginated<ProductRow> {
  counts: { no_category: number }
  facets?: Record<string, FacetCount>
  columns?: ProductColumnInfo[]
}

/** A key of the `Sorts` registry: `default`, `new`, `popular`, `price_asc` — whatever it holds. */
export type ProductSort = string

/** One way to order the list, as the registry names it. */
export interface SortInfo {
  key: string
  label: string
}

/** One facet's choice in the list's filter: values, a range, or on. */
export type FacetChoice =
  Array<string | number> | { min?: number | null; max?: number | null } | true

export interface ProductQuery {
  q?: string
  state?: 'published' | 'unpublished' | 'no-category' | ''
  sort?: ProductSort | null
  page?: number
  per_page?: number
  /** Facet key → what is chosen. Sent as `facets[key][]=…`, `facets[key][min]=…`; on is `facets[key][]=1`. */
  facets?: Record<string, FacetChoice>
}

/** One picture of the gallery; `alt` and `title` in every language at once (§11.1). */
export interface ProductImage {
  id: number
  path: string
  url: string
  thumb: string | null
  alt: LocalizedValue
  title: LocalizedValue
  width: number | null
  height: number | null
  size: number | null
  position: number
}

/** One product as its editor opens it. */
export interface ProductDetail {
  product: ProductRow
  values: ScreenModel
  images: ProductImage[]
}

/** The 422 of a taken article number: who holds it and where to open them. */
export interface SkuHolder {
  id: number
  name: string
  url: string
  deleted: boolean
}

/**
 * What the tree and the editor both say about a category. `products_count` is live products in it
 * and everything under it, main and additional alike, each product once.
 */
interface CategoryFields {
  id: number
  parent_id: number | null
  name: string
  slug: string | null
  depth: number
  is_published: boolean
  /** Published, and so is every category above it (§6.3). */
  visible: boolean
  products_count: number
  url: string | null
}

/** One category of the tree (§6.1). */
export interface CategoryNode extends CategoryFields {
  children: CategoryNode[]
  /** A row of `WxTable` and a node of `WxTreeSelect`, both of which read by key. */
  [key: string]: unknown
}

/** One category as the head of its editor. */
export interface CategoryRow extends CategoryFields {
  created_at: string | null
  updated_at: string | null
  deleted_at: string | null
}

export interface CategoryDetail {
  category: CategoryRow
  values: ScreenModel
  /**
   * Whose facet setting it shows while it has none of its own: the nearest ancestor with one.
   * `null` when it has its own, and when nobody above it has — every facet by default (§6.2).
   */
  facets_from?: { id: number; name: string } | null
}

/** A category's own facet setting: the facets in its order, each shown or not (§6.2). */
export interface FacetSetting {
  key: string
  visible: boolean
}

export type FacetKind = 'terms' | 'range' | 'toggle' | 'tree'

/**
 * One facet of the registry. Its values are not here: the list counts them for what it shows
 * (`ProductsPage.facets`), and the tree facet (the categories) is chosen from the tree itself.
 */
export interface FacetInfo {
  key: string
  code: string
  kind: FacetKind
  label: string
  /** Whether its first level may be an open page of the storefront (§8.1 of the architecture). */
  indexable?: boolean
}

/** `GET /facets`: the registry of facets, and the sorts the list can be ordered by. */
export interface FacetRegistryAnswer {
  facets: FacetInfo[]
  sorts: SortInfo[]
}

/** A row of «Deleted» — products. */
export interface DeletedProduct {
  id: number
  name: string
  sku: string | null
  category: CategoryRef | null
  deleted_at: string | null
  [key: string]: unknown
}

/** A row of «Deleted» — categories. */
export interface DeletedCategory {
  id: number
  name: string
  slug: string | null
  parent: CategoryRef | null
  deleted_at: string | null
  [key: string]: unknown
}

export type DeletedKind = 'products' | 'categories'
