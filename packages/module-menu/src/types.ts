import type { LinkCandidate, LinkRel, LinkTarget, LinkValue } from '@webx-ui/module-admin'

/** The label of an item, per language — what the field with the language chip edits. */
export type LocalizedText = Record<string, string>

/** When this menu's cache was built, and whether there is one at all (§10). */
export interface MenuCacheState {
  enabled: boolean
  /** The oldest of its languages, or null when none of it is built. */
  built_at: string | null
}

export interface MenuRow {
  /** Null until somebody has saved it: a declared menu is shown before it has a row (§5). */
  id: number | null
  /** What a template calls it by. The menu is addressed by this everywhere. */
  key: string
  title: string
  /** Asked for by a template of this site: its key is locked and it cannot be deleted. */
  declared: boolean
  items_count: number
  /** The looks this menu offers, which the site's own markup divides by. */
  variants: string[]
  cache: MenuCacheState
  can: { rename: boolean; delete: boolean }
}

export interface MenuItemRow {
  id: number
  parent_id: number | null
  depth: number
  title: LocalizedText
  /** What the row says: the item's own label, else the name of the thing it points at. */
  label: string
  target: LinkTarget
  entity_type: string | null
  entity_id: number | null
  url: string | null
  hash: string | null
  /** The address as the site would print it — prefix and anchor included. */
  href: string | null
  variant: string
  is_heading: boolean
  new_tab: boolean
  rel: LinkRel[]
  /** Empty means every language. */
  locales: string[]
  visible: boolean
  /** Whether the site would show what this points at right now. */
  available: boolean
  /** The entity behind an `entity` target, so the row needs no second request. */
  resolved: LinkCandidate | null
  children: MenuItemRow[]
}

/** Everything the item dialog sends: a link, and what the menu adds on top of one. */
export interface MenuItemInput {
  title: LocalizedText
  link: LinkValue
  variant: string
  is_heading: boolean
  locales: string[]
  visible: boolean
  parent_id?: number | null
}

export interface MenuInput {
  key: string
  title: string
}
