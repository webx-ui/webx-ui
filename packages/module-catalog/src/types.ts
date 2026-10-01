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
  columns?: Record<string, ColumnValue>
  /** A row of `WxTable`, which reads cells by key. */
  [key: string]: unknown
}

/**
 * What a satellite's column holds for one product: words, or a record by name — with a tone
 * (`{ name, color }`, a stock status), or taken off the site (`{ name, visible: false }`, a brand) —
 * or a list of those (labels). `ColumnValue.vue` draws each by its shape.
 */
export type ColumnValue = string | number | boolean | null | ColumnRecord | ColumnRecord[]

export interface ColumnRecord {
  id?: number | string
  name: string
  /** One of the six tones (`tones.ts`). */
  color?: string | null
  visible?: boolean
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
  /** The words the list is for when the ones typed found nothing and the engine corrected them. */
  corrected?: string | null
  /** The engine did not answer and the database did: no corrections, words as typed. */
  fell_back?: boolean
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
  /** The search as typed, without the engine's correction of words that find nothing. */
  typed?: boolean
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
  /** The video attached to the picture, which is then its poster (the video spec, §1.1). */
  video?: ProductVideo | null
}

/** A video in the gallery: a file on the gallery's disk, or one on a provider's site. */
export interface ProductVideo {
  /** `file`, or the provider's key (`youtube`). */
  provider: string
  /** The file, or the provider's page of the video. */
  url: string
  /** The address for an iframe; `null` for a file. */
  embed: string | null
  /** Whole seconds, when known. */
  duration: number | null
}

/**
 * The answer to a direct link to a video file: the server downloads it in its queue, and the row
 * appears — or the picture gets its video — when that is done.
 */
export interface QueuedVideo {
  queued: true
  product: number
  url: string
  /** The picture the video goes onto; `null` for a new row. */
  image: number | null
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

/** What a bulk action asks for before it runs (`PartField` on the server). */
export interface BulkParam {
  name: string
  /** `category` draws the tree; anything else with `values` a list; the rest a text field. */
  type: string
  label: string
  rules?: string[]
  /** The allowed values, or the tool that lists them — which the panel does not follow. */
  values?: Array<string | number | { value: string | number; label: string }> | string
  /** Where the panel asks for the values instead: a reference book's path (`catalog/labels`). */
  source?: string
}

/** One bulk action the server offers this administrator (§11.4): the core's or a satellite's. */
export interface BulkActionInfo {
  key: string
  label: string
  permission: string
  /** It acts on the products in «Deleted» — a restore. */
  trashed: boolean
  params: BulkParam[]
}

/** Rows ticked, or everything the list's query finds — turned into ids when the run starts. */
export type BulkSelection =
  | { ids: number[] }
  | {
      query: {
        q?: string
        state?: string
        facets?: Record<
          string,
          Array<string | number> | { min?: number | null; max?: number | null }
        >
      }
    }

export interface BulkRunError {
  id: number
  name: string
  message: string
}

/** A run: done inside the request (`id` null) or queued, polled until it is `done` or `failed`. */
export interface BulkRun {
  id: number | null
  action: string
  label: string
  status: 'queued' | 'running' | 'done' | 'failed'
  total: number
  done: number
  failed: number
  errors: BulkRunError[]
  history_id: number | null
  created_at: string | null
  finished_at: string | null
}
