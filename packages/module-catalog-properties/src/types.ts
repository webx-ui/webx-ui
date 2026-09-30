import type { LocalizedValue } from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'

/** Chosen once, when the property is made (decision 1): the products' values have its shape. */
export type PropertyType = 'select' | 'number' | 'text' | 'bool'

export type FilterMode = 'slider' | 'intervals'

export type ValueOrder = 'alpha' | 'manual'

/** A map of languages as the server keeps it; empty where nothing was written. */
export type Words = LocalizedValue | [] | null

/** One property as the API spells it (§7.3 of the properties spec, `Resources::property`). */
export interface PropertyRow {
  id: number
  title: Words
  code: Words
  type: PropertyType
  group: { id: number; title: Words } | null
  is_multiple: boolean
  is_tree: boolean
  leaves_only: boolean
  is_filterable: boolean
  is_indexable: boolean
  is_searchable: boolean
  in_card: boolean
  on_page: boolean
  in_list: boolean
  has_color: boolean
  has_image: boolean
  unit_prefix: Words
  unit_suffix: Words
  precision: number
  filter_mode: FilterMode | null
  value_order: ValueOrder
  toggle_slug: Words
  seo_pattern: Words
  position: number
  products_count: number
  deleted_at: string | null
}

/** A value of a reference book (`Resources::value`), one node of its tree. */
export interface PropertyValue {
  id: number
  parent_id: number | null
  title: Words
  slug: Words
  color: string | null
  image: { id: number; url: string; thumb: string | null } | null
  depth: number
  has_children: boolean
  products_count: number
  /** Only when asked by id: the path down to it, the root first. */
  ancestors?: PropertyValue[]
}

/** A value as a node of `WxTree`, which keeps the children it has loaded on the node itself. */
export interface ValueNode extends PropertyValue {
  /** The name in the panel's language, which the tree reads by key. */
  label: string
  children?: ValueNode[]
  leaf: boolean
  [key: string]: unknown
}

/** One interval of a number's filter: `[min, max)`, either end open (§2). */
export interface PropertyInterval {
  id?: number | null
  title: Words
  slug: Words
  min: number | null
  max: number | null
  position?: number
}

/** A property as its editor opens it: the row, the values of its screen, its intervals. */
export interface PropertyDetail {
  property: PropertyRow
  values: ScreenModel
  intervals: PropertyInterval[]
}

/** The list, as Laravel's paginator answers it. */
export interface PropertiesPage {
  data: PropertyRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PropertyQuery {
  q?: string
  type?: PropertyType | ''
  group?: number | null
  ids?: number[]
  trashed?: boolean
  page?: number
  per_page?: number
}

/** A category's «Properties» tab: what it inherits and from whom, and what it adds itself. */
export interface CategorySet {
  inherited: { property: PropertyRow; from: { id: number; name: string } }[]
  own: PropertyRow[]
}

/** The set in force for a product's form, cut by the groups of the card. */
export interface EffectiveSet {
  groups: { group: { id: number; title: Words } | null; properties: PropertyRow[] }[]
}

/**
 * What a product holds of one property (§3.1): a value's id or a list of them, a number, `true`,
 * or a text in every language. `null` in what is sent takes the value away.
 */
export type HeldValue = number | number[] | true | LocalizedValue | null
