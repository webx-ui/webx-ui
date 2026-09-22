import type { LinkRel, LinkTarget } from '../../../../packages/module-admin/src/links'
import type { LocalizedText } from '../../../../packages/module-menu/src/types'

/**
 * The menus of the demo site: what the configuration asks for, and what somebody made.
 *
 * The list on the screen is the union of the two (§5), so it is assembled rather than stored: a
 * declared menu is there from the first day and its row appears the first time anybody saves
 * into it. That is why `id` is nullable here and why nothing addresses a menu by anything but
 * its key — for part of its life a menu has no id.
 *
 * The tree is one flat array in reading order and `parent_id`, not a nested set: where an item
 * sits among its siblings is its place in this array among the ones with the same parent, which
 * is the whole of what a drag changes. The bounds arithmetic the real module needs is what buys
 * it a subtree in one query, and there is no query here.
 */

export interface MenuRecord {
  /** Null until somebody has saved it — a declared menu is shown before it has a row. */
  id: number | null
  key: string
  title: string
  /** Asked for by a template of this site: the key is locked and it cannot be deleted. */
  declared: boolean
  /** The looks this menu offers, which the site's own markup divides by. */
  variants: string[]
  /** When the cache of this menu was built, or null when none of it is. */
  built_at: string | null
}

export interface ItemRecord {
  id: number
  /** The key of the menu it belongs to: an item is only ever read through one. */
  menu: string
  parent_id: number | null
  title: LocalizedText
  target: LinkTarget
  entity_type: string | null
  entity_id: number | null
  url: string | null
  hash: string | null
  variant: string
  is_heading: boolean
  new_tab: boolean
  rel: LinkRel[]
  /** Empty means every language. */
  locales: string[]
  visible: boolean
}

/**
 * Whether this site caches its menus at all — `webx-menu.cache.enabled`.
 *
 * On, because what the section says about the cache is only worth looking at when there is one:
 * off, every menu reads "Cache off" and the reset button is not drawn.
 */
export const CACHE_ENABLED = true

/** The menus a template of this site asks for, with the titles the configuration gives them. */
const DECLARED: Record<string, { title: string; variants: string[] }> = {
  header: { title: 'Шапка сайта', variants: ['link', 'button'] },
  footer: { title: 'Подвал', variants: ['link'] },
  social: { title: 'Социальные сети', variants: ['link'] },
}

/** What a menu naming no looks of its own gets — `webx-menu.variants`. */
const FALLBACK = ['link']

let nextMenuId = 1
let nextItemId = 1

/** Menus in the order the section draws them: the declared ones first, then the rest. */
export const menus: MenuRecord[] = []

/** Every item of every menu, in reading order. */
export const items: ItemRecord[] = []

function menu(key: string, built: string | null, title?: string): MenuRecord {
  const declared = DECLARED[key]

  const record: MenuRecord = {
    id: nextMenuId++,
    key,
    title: title ?? declared?.title ?? key,
    declared: declared !== undefined,
    variants: declared?.variants ?? FALLBACK,
    built_at: built,
  }

  menus.push(record)

  return record
}

interface ItemInput {
  title?: LocalizedText
  target?: LinkTarget
  entity_type?: string | null
  entity_id?: number | null
  url?: string | null
  hash?: string | null
  variant?: string
  is_heading?: boolean
  new_tab?: boolean
  rel?: LinkRel[]
  locales?: string[]
  visible?: boolean
  parent_id?: number | null
}

/** An item at the end of the level it was asked for, which is the only place a new one goes. */
export function addItem(key: string, input: ItemInput): ItemRecord {
  const record: ItemRecord = {
    id: nextItemId++,
    menu: key,
    parent_id: input.parent_id ?? null,
    title: input.title ?? {},
    target: input.target ?? 'none',
    entity_type: input.entity_type ?? null,
    entity_id: input.entity_id ?? null,
    url: input.url ?? null,
    hash: input.hash ?? null,
    variant: input.variant ?? 'link',
    is_heading: input.is_heading ?? false,
    new_tab: input.new_tab ?? false,
    rel: input.rel ?? [],
    locales: input.locales ?? [],
    visible: input.visible ?? true,
  }

  items.push(record)

  return record
}

/** A page of the demo site, pointed at by nothing but its id — no label, so the page names it. */
function page(key: string, id: number, parent: ItemRecord | null = null): ItemRecord {
  return addItem(key, {
    target: 'entity',
    entity_type: 'page',
    entity_id: id,
    parent_id: parent?.id ?? null,
  })
}

/* ------------------------------------------------------------------------------ header ----- */

const header = menu('header', '2026-09-22T05:40:00+00:00')

page(header.key, 1)
page(header.key, 2)

const services = page(header.key, 6)

page(header.key, 7, services)
page(header.key, 8, services)
page(header.key, 10, services)

addItem(header.key, {
  target: 'url',
  url: '/blog',
  title: { ru: 'Блог', en: 'Blog' },
})

/* A draft page: it has an address and is not on the site, so the row is dimmed rather than gone. */
page(header.key, 12)

/* The one item that takes the other look the header declares. */
addItem(header.key, {
  target: 'entity',
  entity_type: 'page',
  entity_id: 13,
  title: { ru: 'Обсудить проект', en: 'Talk to us' },
  variant: 'button',
})

/* ------------------------------------------------------------------------------ footer ----- */

const footer = menu('footer', null)

const about = addItem(footer.key, {
  target: 'none',
  is_heading: true,
  title: { ru: 'О компании', en: 'About us' },
})

page(footer.key, 2, about)
page(footer.key, 3, about)
page(footer.key, 4, about)

const what = addItem(footer.key, {
  target: 'none',
  is_heading: true,
  title: { ru: 'Услуги', en: 'Services' },
})

page(footer.key, 7, what)
page(footer.key, 8, what)
page(footer.key, 9, what)

page(footer.key, 14)

addItem(footer.key, {
  target: 'url',
  url: 'https://webx-ui.github.io/webx-ui/',
  title: { ru: 'Документация', en: 'Documentation' },
  new_tab: true,
  rel: ['nofollow'],
})

/*
 * A menu built before its page went to the bin: the item is still here and what it pointed at
 * is not, which is the one state a row cannot be read without being told.
 */
addItem(footer.key, {
  target: 'entity',
  entity_type: 'page',
  entity_id: 15,
  title: { ru: 'Акция «Лето»', en: 'Summer offer' },
})

addItem(footer.key, {
  target: 'url',
  url: '/sitemap.xml',
  title: { ru: 'Карта сайта', en: 'Sitemap' },
  visible: false,
})

/* `social` is declared and never saved into: no row, no items — what a fresh section looks like. */

/* ------------------------------------------------------------------- a menu of one's own ----- */

const side = menu('blog-side', '2026-09-21T18:05:00+00:00', 'Меню блога')

for (const id of [1, 2, 3]) {
  addItem(side.key, { target: 'entity', entity_type: 'rubric', entity_id: id })
}

addItem(side.key, {
  target: 'url',
  url: '/blog/rss',
  title: { ru: 'RSS', en: 'RSS' },
  /* Only where the site is read in Russian — what the language field is for. */
  locales: ['ru'],
})

/* ------------------------------------------------------------------------------- reads ----- */

/** Every menu the section lists: the declared ones in the order of the configuration, then the rest. */
export function list(): MenuRecord[] {
  const keys = Object.keys(DECLARED)
  const declared = keys.map((key) => find(key) ?? placeholder(key))

  return [...declared, ...menus.filter((one) => !one.declared)]
}

export function find(key: string): MenuRecord | null {
  return menus.find((one) => one.key === key) ?? null
}

export function isDeclared(key: string): boolean {
  return DECLARED[key] !== undefined
}

/** A declared menu nobody has saved into: everything it has comes from the configuration. */
function placeholder(key: string): MenuRecord {
  return {
    id: null,
    key,
    title: DECLARED[key].title,
    declared: true,
    variants: DECLARED[key].variants,
    built_at: null,
  }
}

/** The row of a declared menu, made the moment something is saved into it. */
export function ensure(key: string): MenuRecord {
  return find(key) ?? menu(key, null)
}

/** A menu of somebody's own: its key is not in the configuration, so it is neither declared nor
 *  offered any look beyond the fallback. */
export function create(key: string, title: string): MenuRecord {
  return menu(key, null, title)
}

export function remove(key: string): void {
  const at = menus.findIndex((one) => one.key === key)

  if (at >= 0) menus.splice(at, 1)

  /* The items go with it, the way the cascade on `menu_id` takes them. */
  for (let index = items.length - 1; index >= 0; index -= 1) {
    if (items[index].menu === key) items.splice(index, 1)
  }
}

export function rename(record: MenuRecord, key: string): void {
  for (const item of items) {
    if (item.menu === record.key) item.menu = key
  }

  record.key = key
}

export function countItems(key: string): number {
  return items.filter((item) => item.menu === key).length
}

/** The children of an item, or the top level when asked for null — in their own order. */
export function childrenOf(key: string, parentId: number | null): ItemRecord[] {
  return items.filter((item) => item.menu === key && item.parent_id === parentId)
}

export function itemOf(key: string, id: number): ItemRecord | null {
  return items.find((item) => item.menu === key && item.id === id) ?? null
}

export function depthOf(item: ItemRecord): number {
  let depth = 0
  let parent = item.parent_id === null ? null : itemOf(item.menu, item.parent_id)

  while (parent !== null) {
    depth += 1
    parent = parent.parent_id === null ? null : itemOf(parent.menu, parent.parent_id)
  }

  return depth
}

/** Everything under an item, at any depth. */
export function descendantsOf(item: ItemRecord): ItemRecord[] {
  const found: ItemRecord[] = []

  for (const child of childrenOf(item.menu, item.id)) {
    found.push(child, ...descendantsOf(child))
  }

  return found
}

/* ------------------------------------------------------------------------------ writes ----- */

/**
 * Where an item sits: under which parent, and how far down that level.
 *
 * The siblings are read with the item itself left out, because a position counted over a list
 * the item is still in means one thing before the move and another after it. Only the item
 * moves in the array; whatever hangs under it keeps its `parent_id` and so comes with it.
 */
export function moveItem(item: ItemRecord, parentId: number | null, index: number): boolean {
  if (parentId !== null) {
    const parent = itemOf(item.menu, parentId)

    // Into its own branch: the tree would have no root. Refused rather than corrected.
    if (parent === null || parent.id === item.id || isUnder(parent, item)) return false
  }

  items.splice(items.indexOf(item), 1)
  item.parent_id = parentId

  const siblings = childrenOf(item.menu, parentId)
  const after = index > 0 ? siblings[index - 1] : undefined
  const before = siblings[index]

  if (after !== undefined) {
    items.splice(items.indexOf(after) + 1, 0, item)
  } else if (before !== undefined) {
    items.splice(items.indexOf(before), 0, item)
  } else {
    // The level is empty, or the position is past its end — both mean "last".
    items.push(item)
  }

  return true
}

function isUnder(item: ItemRecord, ancestor: ItemRecord): boolean {
  let parent = item.parent_id === null ? null : itemOf(item.menu, item.parent_id)

  while (parent !== null) {
    if (parent.id === ancestor.id) return true

    parent = parent.parent_id === null ? null : itemOf(parent.menu, parent.parent_id)
  }

  return false
}

/** An item and everything under it. Answers how many went, which is what the panel promised. */
export function removeItem(item: ItemRecord): number {
  const going = [item, ...descendantsOf(item)]

  for (const one of going) {
    items.splice(items.indexOf(one), 1)
  }

  return going.length
}

/**
 * Forget what a menu was built into, in every language.
 *
 * Nothing here builds it again: what builds a menu's cache is a visitor to the site, and the
 * playground has no site. So an edit and the reset both leave the mark reading "Cache not
 * built", which is exactly what they do on a site nobody has opened since.
 */
export function forget(key: string): void {
  const record = find(key)

  if (record !== null) record.built_at = null
}

export function forgetAll(): void {
  for (const record of menus) record.built_at = null
}
