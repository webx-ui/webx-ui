import type { FacetInfo, ProductColumnInfo } from '../../../../packages/module-catalog/src/types'
import type { HistoryChange, HistoryPage } from '../../../../packages/module-admin/src/history'
import { fileById } from './media'
import { Reply } from './reply'

/**
 * The catalogue's properties in the fake server (WEBX_UI_CATALOG_PROPERTIES.md): the properties
 * with their reference books and intervals, their groups through the shared category API, the
 * sets of the categories — and what they add to the catalogue through its registries: the
 * «Specifications» part of the product form, columns and facets of the list, bulk actions.
 * `catalog.ts` asks this file at each of those points, as it asks `catalog-dictionaries.ts`.
 *
 * The shapes are the php half's (`Resources`, `PropertyForm`, `PropertiesPart`, `PropertyColumns`,
 * `PropertyActions`), and so are its refusals: the type does not change once saved, a code is
 * `[a-z0-9-]` and one per language, a value on products is merged rather than deleted, a property
 * an ancestor has is not added below it.
 */

type Words = Record<string, string>
type Line = (locale: string, namespace: string, path: string) => string
type Fail = (
  status: number,
  message: string,
  errors?: Record<string, string[]>,
  extra?: Record<string, unknown>,
) => Error
type On = (
  method: string,
  pattern: string,
  handler: (context: {
    params: string[]
    query: URLSearchParams
    body: Record<string, unknown>
    locale: string
  }) => unknown,
) => void

/** What this file needs to know of a category: the catalogue hands it in with the routes. */
export type CategoryLookup = (
  id: number,
) => { parent_id: number | null; name: Words; deleted_at: string | null } | undefined

const NS = 'webx-catalog-properties'
const DEFAULT = 'ru'
const SOURCE = 'catalog/properties'
const GROUPS = 'catalog/property-groups'
const CODE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/
const CODE_LENGTH = 48
const SLUG_LENGTH = 64
const TYPES = ['select', 'number', 'text', 'bool'] as const
const FLAGS = [
  'is_multiple',
  'is_tree',
  'leaves_only',
  'is_filterable',
  'is_indexable',
  'is_searchable',
  'in_card',
  'on_page',
  'in_list',
  'has_color',
  'has_image',
] as const
const TRANSLATED = [
  'title',
  'code',
  'unit_prefix',
  'unit_suffix',
  'toggle_slug',
  'seo_pattern',
] as const
/* The codes the core's facets and the reference books hold (`Facets::taken()`). */
const TAKEN: [string, string, string][] = [
  ['category', 'webx-catalog', 'module.category'],
  ['price', 'webx-catalog', 'product.price'],
  ['label', 'webx-catalog-labels', 'product.labels'],
  ['stock', 'webx-catalog-stock', 'product.status'],
  ['brand', 'webx-catalog-brands', 'product.brand'],
]

type Type = (typeof TYPES)[number]
type Flag = (typeof FLAGS)[number]
type Translated = (typeof TRANSLATED)[number]

interface PropertyRecord extends Record<Flag, boolean>, Record<Translated, Words> {
  id: number
  type: Type
  group_id: number | null
  filter_mode: string | null
  value_order: string
  precision: number
  position: number
  extra: Record<string, unknown>
  deleted_at: string | null
}

interface ValueRecord {
  id: number
  property_id: number
  parent_id: number | null
  /** Among its siblings: the tree's order, which is the order «by hand». */
  position: number
  title: Words
  slug: Words
  color: string | null
  image_id: number | null
}

interface IntervalRecord {
  id: number
  property_id: number
  title: Words
  slug: Words
  min: number | null
  max: number | null
  position: number
}

interface GroupRecord {
  id: number
  title: Words
  is_visible: boolean
  position: number
  extra: Record<string, unknown>
  deleted_at: string | null
}

/** A product's value in its type's shape: an id or ids, a number, `true`, a map of languages. */
type Held = number | number[] | true | Words

/* ------------------------------------------------------------------------ fixtures ----- */

const groups: GroupRecord[] = [group(1, 'Основное', 'Main'), group(2, 'Габариты', 'Dimensions')]

function group(id: number, ru: string, en: string): GroupRecord {
  return {
    id,
    title: { ru, en },
    is_visible: true,
    position: id,
    extra: {},
    deleted_at: null,
  }
}

function property(
  id: number,
  type: Type,
  title: Words,
  code: Words,
  extra: Partial<PropertyRecord> = {},
): PropertyRecord {
  return {
    id,
    type,
    title,
    code,
    group_id: null,
    is_multiple: false,
    is_tree: false,
    leaves_only: false,
    is_filterable: false,
    is_indexable: false,
    is_searchable: type === 'select' || type === 'text',
    in_card: false,
    on_page: true,
    in_list: false,
    has_color: false,
    has_image: false,
    filter_mode: type === 'number' ? 'slider' : null,
    value_order: 'alpha',
    unit_prefix: {},
    unit_suffix: {},
    precision: 0,
    toggle_slug: {},
    seo_pattern: {},
    position: id,
    extra: {},
    deleted_at: null,
    ...extra,
  }
}

/* §12: the Russian codes are translated, so that a translated address shows. */
const properties: PropertyRecord[] = [
  property(
    1,
    'select',
    { ru: 'Цвет', en: 'Colour' },
    { ru: 'cvet', en: 'color' },
    {
      group_id: 1,
      is_multiple: true,
      value_order: 'manual',
      has_color: true,
      is_filterable: true,
      is_indexable: true,
      in_card: true,
      in_list: true,
    },
  ),
  property(
    2,
    'select',
    { ru: 'Материал', en: 'Material' },
    { ru: 'material', en: 'material' },
    {
      group_id: 1,
      is_tree: true,
      is_filterable: true,
      is_indexable: true,
    },
  ),
  property(
    3,
    'number',
    { ru: 'Вес', en: 'Weight' },
    { ru: 'ves', en: 'weight' },
    {
      group_id: 2,
      unit_suffix: { ru: ' кг', en: ' kg' },
      precision: 2,
      is_filterable: true,
      is_indexable: true,
      filter_mode: 'intervals',
    },
  ),
  property(
    4,
    'number',
    { ru: 'Диагональ', en: 'Diagonal' },
    { ru: 'diagonal', en: 'diagonal' },
    {
      group_id: 2,
      unit_suffix: { ru: '″', en: '″' },
      precision: 1,
      is_filterable: true,
      in_list: true,
    },
  ),
  property(
    5,
    'bool',
    { ru: 'Wi-Fi', en: 'Wi-Fi' },
    { ru: 'wi-fi', en: 'wi-fi' },
    {
      group_id: 1,
      is_filterable: true,
      toggle_slug: { ru: 'est', en: 'yes' },
    },
  ),
  // No group: the card shows it last, without a heading.
  property(
    6,
    'text',
    { ru: 'Комплектация', en: 'Contents' },
    { ru: 'komplektaciya', en: 'contents' },
  ),
]

const values: ValueRecord[] = []

function value(
  id: number,
  owner: number,
  parent: number | null,
  ru: string,
  en: string,
  color: string | null = null,
): void {
  values.push({
    id,
    property_id: owner,
    parent_id: parent,
    position: values.filter((one) => one.property_id === owner && one.parent_id === parent).length,
    title: { ru, en },
    slug: { ru: slugOf(ru), en: slugOf(en) },
    color,
    image_id: null,
  })
}

value(1, 1, null, 'Чёрный', 'Black', '#1f2328')
value(2, 1, null, 'Белый', 'White', '#ffffff')
value(3, 1, null, 'Серый', 'Grey', '#8c959f')
value(4, 1, null, 'Красный', 'Red', '#cf222e')
value(5, 1, null, 'Синий', 'Blue', '#0969da')
value(6, 2, null, 'Металл', 'Metal')
value(7, 2, 6, 'Сталь', 'Steel')
value(8, 2, 7, 'Нержавеющая', 'Stainless')
value(9, 2, 6, 'Алюминий', 'Aluminium')
value(10, 2, null, 'Пластик', 'Plastic')
value(11, 2, null, 'Дерево', 'Wood')

const intervals: IntervalRecord[] = [
  interval(1, 'до 1 кг', 'up to 1 kg', 'do-1-kg', 'up-to-1-kg', null, 1),
  interval(2, '1–3 кг', '1–3 kg', '1-3-kg', '1-3-kg', 1, 3),
  interval(3, 'от 3 кг', 'from 3 kg', 'ot-3-kg', 'from-3-kg', 3, null),
]

function interval(
  id: number,
  ru: string,
  en: string,
  slugRu: string,
  slugEn: string,
  min: number | null,
  max: number | null,
): IntervalRecord {
  return {
    id,
    property_id: 3,
    title: { ru, en },
    slug: { ru: slugRu, en: slugEn },
    min,
    max,
    position: id - 1,
  }
}

/*
 * The sets (`catalog_category_property`): the colour at the root of «Electronics», so every
 * electronic thing inherits it; the rest on two branches — laptops and phones, and the
 * appliances. Accessories have none, so a case with a colour holds it outside its set.
 */
const sets = new Map<number, number[]>([
  [1, [1]],
  [2, [4, 3, 5, 6]],
  [3, [4, 5]],
  [6, [1, 2, 3]],
  [7, [6]],
  [8, [5]],
])

/** product → property → value; the rows of `catalog_product_property_values`. */
const stored = new Map<number, Map<number, Held>>()

function hold(product: number, held: Record<number, Held | undefined>): void {
  const entries = Object.entries(held).filter(
    (entry): entry is [string, Held] => entry[1] !== undefined,
  )

  stored.set(product, new Map(entries.map(([key, one]) => [Number(key), one])))
}

hold(1, {
  1: [1, 3],
  4: 14,
  3: 1.35,
  5: true,
  6: {
    ru: 'Ноутбук, блок питания 65 Вт, документация',
    en: 'Laptop, 65 W power adapter, documentation',
  },
})
hold(2, { 1: [3], 4: 16, 3: 2.1, 5: true })
hold(3, { 1: [2], 4: 13.3, 3: 0.98, 5: true })
hold(4, { 1: [1, 5], 4: 6.4, 5: true })
hold(5, { 1: [1], 4: 6.7, 5: true })
hold(6, { 1: [2, 4], 4: 6.1, 5: true })
hold(7, { 1: [3], 4: 6.3, 5: true })
hold(8, { 1: [2], 2: 10, 3: 1.1, 6: { ru: 'Чайник, подставка', en: 'Kettle, base' } })
hold(9, { 1: [3], 2: 8, 3: 1.4 })
hold(10, { 1: [1], 2: 7, 3: 1.25 })
hold(11, { 1: [4], 2: 10, 3: 5.8 })
hold(12, { 1: [1], 2: 10, 3: 3.2, 5: true })
// Outside their sets (§12): a case is an accessory, and a laptop stand's shelf has no material.
hold(13, { 1: [1] })
hold(22, { 2: 9, 3: 0.6 })
hold(25, { 1: [2] })

/* The series of the demo shop: phones and laptops of a few sizes, earphones of a few colours. */
for (let index = 0; index < 120; index++) {
  const id = 100 + index

  switch (index % 8) {
    case 0:
      hold(id, { 1: [[1, 2, 3][index % 3]!] })
      break
    case 6:
      hold(id, { 1: [[1, 5][index % 2]!], 4: [6.1, 6.4, 6.7][index % 3]!, 5: true })
      break
    case 7:
      hold(id, {
        1: [3],
        4: [13.3, 14, 15.6][index % 3]!,
        3: [0.98, 1.4, 2.3][index % 3]!,
        ...(index % 2 === 0 ? { 5: true as const } : {}),
      })
      break
  }
}

/* ------------------------------------------------------------------------ helpers ----- */

let lineOf: Line = (_locale, _namespace, path) => path
let categoryOf: CategoryLookup = () => undefined

function say(locale: string, path: string, swap: Record<string, string | number> = {}): string {
  let text = lineOf(locale, NS, path)

  for (const [key, one] of Object.entries(swap)) text = text.replace(`:${key}`, String(one))

  return text
}

function word(map: Words, locale: string): string {
  return map[locale] || map[DEFAULT] || map.en || Object.values(map).find(Boolean) || ''
}

/** `Str::slug` with the language: Cyrillic transliterated, anything else a hyphen. */
function slugOf(text: string): string {
  const table: Record<string, string> = {
    а: 'a',
    б: 'b',
    в: 'v',
    г: 'g',
    д: 'd',
    е: 'e',
    ё: 'e',
    ж: 'zh',
    з: 'z',
    и: 'i',
    й: 'y',
    к: 'k',
    л: 'l',
    м: 'm',
    н: 'n',
    о: 'o',
    п: 'p',
    р: 'r',
    с: 's',
    т: 't',
    у: 'u',
    ф: 'f',
    х: 'h',
    ц: 'c',
    ч: 'ch',
    ш: 'sh',
    щ: 'sch',
    ъ: '',
    ы: 'y',
    ь: '',
    э: 'e',
    ю: 'yu',
    я: 'ya',
  }

  return text
    .toLowerCase()
    .split('')
    .map((char) => table[char] ?? char)
    .join('')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function nextId(list: { id: number }[]): number {
  return Math.max(0, ...list.map((one) => one.id)) + 1
}

/**
 * A map of languages laid over one — a bare string is the panel's language; a blank one drops.
 * A unit keeps its spaces: ` kg` is how the number and the unit stand apart.
 */
function layOver(before: Words, sent: unknown, locale: string, trim = true): Words {
  const next = { ...before }
  const map =
    typeof sent === 'string'
      ? { [locale]: sent }
      : sent && typeof sent === 'object'
        ? (sent as Record<string, unknown>)
        : {}

  for (const [code, text] of Object.entries(map)) {
    if (typeof text === 'string' && text.trim() !== '') next[code] = trim ? text.trim() : text
    else delete next[code]
  }

  return next
}

function paginate<T>(rows: T[], query: URLSearchParams, path: string) {
  const perPage = Math.min(500, Math.max(1, Number(query.get('per_page') ?? 50) || 50))
  const last = Math.max(1, Math.ceil(rows.length / perPage))
  const page = Math.min(last, Math.max(1, Number(query.get('page') ?? 1) || 1))
  const from = rows.length === 0 ? null : (page - 1) * perPage + 1
  const url = (at: number) => `/api/cms/${path}?page=${at}`

  return {
    current_page: page,
    data: rows.slice((page - 1) * perPage, page * perPage),
    first_page_url: url(1),
    from,
    last_page: last,
    last_page_url: url(last),
    next_page_url: page < last ? url(page + 1) : null,
    path: `/api/cms/${path}`,
    per_page: perPage,
    prev_page_url: page > 1 ? url(page - 1) : null,
    to: from === null ? null : Math.min(rows.length, page * perPage),
    total: rows.length,
  }
}

function liveProperties(): PropertyRecord[] {
  return properties
    .filter((one) => one.deleted_at === null)
    .sort((a, b) => a.position - b.position || a.id - b.id)
}

function propertyById(id: number, trashed = false): PropertyRecord | undefined {
  return properties.find((one) => one.id === id && (trashed || one.deleted_at === null))
}

function displayName(record: PropertyRecord, locale: string): string {
  return record.title[locale] || record.code[locale] || `#${record.id}`
}

function codeIn(record: PropertyRecord, locale: string): string {
  return record.code[locale] || `p${record.id}`
}

function usesIntervals(record: PropertyRecord): boolean {
  return record.type === 'number' && record.filter_mode === 'intervals'
}

/** `⌀12 мм`, `1,35 кг`: the precision, the language's decimal mark, the prefix and suffix. */
function formatNumber(record: PropertyRecord, number: number, locale: string): string {
  const formatted = new Intl.NumberFormat(locale, {
    minimumFractionDigits: record.precision,
    maximumFractionDigits: record.precision,
  }).format(number)

  return `${record.unit_prefix[locale] ?? ''}${formatted}${record.unit_suffix[locale] ?? ''}`
}

/* ---------------------------------------------------------------------- the reference book ----- */

function childrenOf(owner: number, parent: number | null): ValueRecord[] {
  return values
    .filter((one) => one.property_id === owner && one.parent_id === parent)
    .sort((a, b) => a.position - b.position)
}

/** The tree walked depth first: the order of `lft`. */
function treeOrder(owner: number): ValueRecord[] {
  const walk = (parent: number | null): ValueRecord[] =>
    childrenOf(owner, parent).flatMap((one) => [one, ...walk(one.id)])

  return walk(null)
}

function valueById(id: number): ValueRecord | undefined {
  return values.find((one) => one.id === id)
}

function ancestorsOf(record: ValueRecord): ValueRecord[] {
  const chain: ValueRecord[] = []
  let parent = record.parent_id === null ? undefined : valueById(record.parent_id)

  while (parent) {
    chain.unshift(parent)
    parent = parent.parent_id === null ? undefined : valueById(parent.parent_id)
  }

  return chain
}

function subtreeIds(record: ValueRecord): number[] {
  return [record.id, ...childrenOf(record.property_id, record.id).flatMap(subtreeIds)]
}

function valueName(record: ValueRecord, locale: string): string {
  return record.title[locale] || record.slug[locale] || `#${record.id}`
}

/** Every product holding one of these values of a reference book. */
function productsHolding(owner: number, ids: number[]): number[] {
  const found: number[] = []

  for (const [product, held] of stored) {
    const one = held.get(owner)

    if (one === undefined) continue

    const own = Array.isArray(one) ? one : typeof one === 'number' ? [one] : []

    if (own.some((id) => ids.includes(id))) found.push(product)
  }

  return found
}

/**
 * The value as the API spells it. The list counts the products of the value itself, and a single
 * value — after a save — those of its subtree, as `Resources::value()` does on each path.
 */
function valueRow(record: ValueRecord, subtree = true): Record<string, unknown> {
  const file = record.image_id === null ? null : fileById(record.image_id)

  return {
    id: record.id,
    parent_id: record.parent_id,
    title: { ...record.title },
    slug: { ...record.slug },
    color: record.color,
    image: file ? { id: file.id, url: file.url, thumb: file.thumb ?? file.url } : null,
    depth: ancestorsOf(record).length,
    has_children: childrenOf(record.property_id, record.id).length > 0,
    products_count: productsHolding(record.property_id, subtree ? subtreeIds(record) : [record.id])
      .length,
  }
}

/** «Alphabetical» in the panel's language, «by hand» in the tree's order. */
function ordered(owner: PropertyRecord, list: ValueRecord[], locale: string): ValueRecord[] {
  if (owner.value_order === 'manual') {
    const order = treeOrder(owner.id).map((one) => one.id)

    return list.slice().sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id))
  }

  return list
    .slice()
    .sort(
      (a, b) => word(a.title, locale).localeCompare(word(b.title, locale), locale) || a.id - b.id,
    )
}

/* ------------------------------------------------------------------------------- sets ----- */

/** The category and its ancestors, root first. */
function lineage(id: number | null): number[] {
  const chain: number[] = []
  let current = id === null ? undefined : categoryOf(id)
  let at = id

  while (current && at !== null) {
    chain.unshift(at)
    at = current.parent_id
    current = at === null ? undefined : categoryOf(at)
  }

  return chain
}

/** The set in force: each ancestor's own from the root, then its own; the first owner wins. */
function effectiveRows(category: number | null): { property: number; from: number }[] {
  const rows = new Map<number, { property: number; from: number }>()

  for (const owner of lineage(category)) {
    for (const id of sets.get(owner) ?? []) {
      if (!rows.has(id)) rows.set(id, { property: id, from: owner })
    }
  }

  return [...rows.values()].filter((row) => propertyById(row.property) !== undefined)
}

function effective(category: number | null): number[] {
  return effectiveRows(category).map((row) => row.property)
}

/* -------------------------------------------------------------------- the API's shapes ----- */

function productsOf(record: PropertyRecord): number {
  return [...stored.values()].filter((held) => held.has(record.id)).length
}

function propertyRow(record: PropertyRecord): Record<string, unknown> {
  const owner = groups.find((one) => one.id === record.group_id)
  const flags = Object.fromEntries(FLAGS.map((flag) => [flag, record[flag]]))

  return {
    id: record.id,
    title: { ...record.title },
    code: { ...record.code },
    type: record.type,
    group: owner ? { id: owner.id, title: { ...owner.title } } : null,
    ...flags,
    unit_prefix: { ...record.unit_prefix },
    unit_suffix: { ...record.unit_suffix },
    precision: record.precision,
    filter_mode: record.filter_mode,
    value_order: record.value_order,
    toggle_slug: { ...record.toggle_slug },
    seo_pattern: { ...record.seo_pattern },
    position: record.position,
    products_count: productsOf(record),
    deleted_at: record.deleted_at,
  }
}

function intervalRow(record: IntervalRecord): Record<string, unknown> {
  return {
    id: record.id,
    title: { ...record.title },
    slug: { ...record.slug },
    min: record.min,
    max: record.max,
    position: record.position,
  }
}

function intervalsOf(owner: number): IntervalRecord[] {
  return intervals
    .filter((one) => one.property_id === owner)
    .sort((a, b) => a.position - b.position || a.id - b.id)
}

/** `PropertyForm::read()`: the property's own fields, then the project's out of `extra`. */
function formValues(record: PropertyRecord): Record<string, unknown> {
  const own: Record<string, unknown> = {
    title: { ...record.title },
    code: { ...record.code },
    type: record.type,
    group_id: record.group_id,
    ...Object.fromEntries(FLAGS.map((flag) => [flag, record[flag]])),
    filter_mode: record.filter_mode,
    value_order: record.value_order,
    unit_prefix: { ...record.unit_prefix },
    unit_suffix: { ...record.unit_suffix },
    precision: record.precision,
    toggle_slug: { ...record.toggle_slug },
    seo_pattern: { ...record.seo_pattern },
  }

  return { ...record.extra, ...own }
}

function answer(record: PropertyRecord): Record<string, unknown> {
  return {
    data: {
      property: propertyRow(record),
      values: formValues(record),
      intervals: intervalsOf(record.id).map(intervalRow),
    },
  }
}

/* ---------------------------------------------------------------- saving a property ----- */

type Draft = Omit<PropertyRecord, Flag | 'type' | 'filter_mode'> &
  Record<Flag, boolean | null> & { type: string; filter_mode: string | null }

/**
 * The model's save: what was sent laid over the record, the defaults of a new one, the flags its
 * type has no use for put out rather than refused, codes made of the names, then the checks.
 */
function saveProperty(
  existing: PropertyRecord | null,
  input: Record<string, unknown>,
  locale: string,
  fail: Fail,
): PropertyRecord {
  const draft: Draft = existing
    ? structuredClone(existing)
    : {
        ...property(0, 'select', {}, {}),
        type: '',
        filter_mode: null,
        ...(Object.fromEntries(FLAGS.map((flag) => [flag, null])) as Record<Flag, null>),
      }

  for (const [name, sent] of Object.entries(input)) {
    if ((TRANSLATED as readonly string[]).includes(name)) {
      const field = name as Translated

      draft[field] = layOver(draft[field], sent, locale, false)
    } else if ((FLAGS as readonly string[]).includes(name)) {
      draft[name as Flag] = Boolean(sent)
    } else if (name === 'type') {
      draft.type = typeof sent === 'string' ? sent : ''
    } else if (name === 'group_id') {
      draft.group_id = sent === null || sent === '' || sent === undefined ? null : Number(sent)
    } else if (name === 'precision') {
      draft.precision = Number(sent ?? 0) || 0
    } else if (name === 'filter_mode') {
      draft.filter_mode = typeof sent === 'string' ? sent : null
    } else if (name === 'value_order') {
      draft.value_order = typeof sent === 'string' ? sent : ''
    } else {
      // Not the property's own: a field a project patched onto the screen (`HasExtra`).
      draft.extra[name] = sent
    }
  }

  if (!(TYPES as readonly string[]).includes(draft.type)) {
    const message = say(locale, 'errors.type', { types: TYPES.join(', ') })

    throw fail(422, message, { type: [message] })
  }

  if (existing && draft.type !== existing.type) {
    const message = say(locale, 'errors.type-fixed')

    throw fail(422, message, { type: [message] })
  }

  const isSelect = draft.type === 'select'
  const isNumber = draft.type === 'number'

  if (!existing) {
    draft.is_searchable ??= isSelect || draft.type === 'text'
    draft.is_indexable ??= isSelect || (isNumber && draft.filter_mode === 'intervals')
    if (isNumber) draft.filter_mode ??= 'slider'
  }

  for (const flag of FLAGS) draft[flag] ??= flag === 'on_page'

  if (!isSelect) {
    for (const flag of ['is_multiple', 'is_tree', 'leaves_only', 'has_color', 'has_image'] as const)
      draft[flag] = false
  }

  if (!draft.is_tree) draft.leaves_only = false

  // A text is never a facet (decision 4), and a range has no page of its own (§5.1).
  if (draft.type === 'text') {
    draft.is_filterable = false
    draft.is_indexable = false
  }

  if (isNumber) {
    if (draft.filter_mode !== 'slider' && draft.filter_mode !== 'intervals')
      draft.filter_mode = 'slider'
    draft.precision = Math.max(0, Math.min(6, Math.trunc(draft.precision)))
    draft.value_order = 'alpha'
  } else {
    draft.filter_mode = null
    draft.precision = 0
    draft.unit_prefix = {}
    draft.unit_suffix = {}
  }

  const intervalsOn = isNumber && draft.filter_mode === 'intervals'

  if (draft.type === 'bool' || (isNumber && !intervalsOn)) draft.is_indexable = false
  if (draft.value_order !== 'alpha' && draft.value_order !== 'manual') draft.value_order = 'alpha'
  if (draft.type !== 'bool') draft.toggle_slug = {}
  if (!isSelect && !intervalsOn) draft.seo_pattern = {}

  // A language with a name and no code gets one made of the name (§3.4).
  for (const [code, title] of Object.entries(draft.title)) {
    if (!draft.code[code]) draft.code[code] = slugOf(title).slice(0, CODE_LENGTH)
  }

  for (const [code, text] of Object.entries(draft.code))
    draft.code[code] = text.trim().toLowerCase()

  const errors: Record<string, string[]> = {}

  for (const [code, text] of Object.entries(draft.code)) {
    if (text === '') continue

    if (!CODE.test(text) || text.length > CODE_LENGTH) {
      errors[`code.${code}`] = [say(locale, 'errors.code', { max: CODE_LENGTH })]
      continue
    }

    const other = liveProperties().find((one) => one.id !== existing?.id && one.code[code] === text)
    const core = TAKEN.find(([taken]) => taken === text)
    const holder = other ? displayName(other, code) : core ? lineOf(code, core[1], core[2]) : null

    if (holder !== null) errors[`code.${code}`] = [say(locale, 'errors.code-taken', { holder })]
  }

  for (const [code, text] of Object.entries(draft.toggle_slug)) {
    if (!CODE.test(text)) errors[`toggle_slug.${code}`] = [say(locale, 'errors.slug')]
  }

  if (
    draft.group_id !== null &&
    !groups.some((one) => one.id === draft.group_id && one.deleted_at === null)
  ) {
    errors.group_id = [say(locale, 'errors.group')]
  }

  if (Object.keys(errors).length > 0) throw fail(422, Object.values(errors)[0]![0]!, errors)

  const saved = draft as PropertyRecord

  if (existing) {
    Object.assign(existing, saved)

    return existing
  }

  saved.id = nextId(properties)
  saved.position = Math.max(0, ...properties.map((one) => one.position)) + 1
  properties.push(saved)

  return saved
}

/* ------------------------------------------------------------------ saving a value ----- */

function fillValue(
  record: ValueRecord,
  body: Record<string, unknown>,
  locale: string,
  fail: Fail,
): void {
  const draft: ValueRecord = structuredClone(record)

  if ('title' in body) draft.title = layOver(draft.title, body.title, locale)
  if ('slug' in body) draft.slug = layOver(draft.slug, body.slug, locale)
  if ('color' in body) draft.color = typeof body.color === 'string' ? body.color : null
  if ('image_id' in body) {
    draft.image_id =
      body.image_id === null || body.image_id === '' ? null : Number(body.image_id) || null
  }

  const errors: Record<string, string[]> = {}

  if (draft.image_id !== null && !fileById(draft.image_id)) {
    errors.image_id = ['The selected image id is invalid.']
  }

  const taken = (slug: string, code: string) =>
    values.some(
      (one) =>
        one.property_id === draft.property_id && one.id !== draft.id && one.slug[code] === slug,
    )

  // An empty slug is made of the name; one taken in the property gets `-2` (a value may).
  for (const [code, title] of Object.entries(draft.title)) {
    if (draft.slug[code]) continue

    const base = slugOf(title).slice(0, SLUG_LENGTH - 4) || 'value'
    let free = base

    for (let n = 2; taken(free, code); n++) free = `${base}-${n}`

    draft.slug[code] = free
  }

  for (const [code, slug] of Object.entries(draft.slug)) {
    const lower = slug.trim().toLowerCase()

    draft.slug[code] = lower

    if (!CODE.test(lower) || lower.length > SLUG_LENGTH) {
      errors[`slug.${code}`] = [say(locale, 'errors.slug')]
    } else if (taken(lower, code)) {
      errors[`slug.${code}`] = [say(locale, 'errors.slug-taken')]
    }
  }

  const color = draft.color?.trim().toLowerCase() ?? ''

  draft.color = color === '' ? null : color

  if (draft.color !== null && !/^#[0-9a-f]{6}$/.test(draft.color)) {
    errors.color = [say(locale, 'errors.color')]
  }

  if (Object.keys(errors).length > 0) throw fail(422, Object.values(errors)[0]![0]!, errors)

  Object.assign(record, draft)
}

/** Put a value among the children of `parent`, before `before` (none — last). */
function place(record: ValueRecord, parent: number | null, before: ValueRecord | null): void {
  const old = childrenOf(record.property_id, record.parent_id).filter((one) => one !== record)

  old.forEach((one, index) => (one.position = index))

  const siblings = childrenOf(record.property_id, parent).filter((one) => one !== record)
  const at = before ? siblings.indexOf(before) : -1

  siblings.splice(at < 0 ? siblings.length : at, 0, record)
  record.parent_id = parent
  siblings.forEach((one, index) => (one.position = index))
}

/* ------------------------------------------------------------- a product's values ----- */

function heldOf(product: number): Map<number, Held> {
  let held = stored.get(product)

  if (!held) {
    held = new Map()
    stored.set(product, held)
  }

  return held
}

function textIn(text: Words, locale: string): string | null {
  for (const code of [locale, ...Object.keys(text)]) if (text[code]) return text[code]!

  return null
}

/** The value as words — a line of the journal, a cell of the list: `Black, Grey`, `1,35 кг`. */
function format(record: PropertyRecord, one: Held | null, locale: string): string | null {
  if (one === null || (Array.isArray(one) && one.length === 0)) return null

  switch (record.type) {
    case 'select': {
      const ids = Array.isArray(one) ? one : [one as number]

      return treeOrder(record.id)
        .filter((each) => ids.includes(each.id))
        .map((each) => valueName(each, locale))
        .join(', ')
    }
    case 'number':
      return formatNumber(record, one as number, locale)
    case 'bool':
      return say(locale, 'product.yes')
    default:
      return textIn(one as Words, locale)
  }
}

/** `ProductValues::normalise()`: what is sent in the type's shape, or a refusal by message. */
function normalise(
  record: PropertyRecord,
  sent: unknown,
  before: Held | null,
  add: boolean,
  locale: string,
): Held | null | { refused: string } {
  if (sent === null || sent === undefined || sent === '' || (Array.isArray(sent) && !sent.length))
    return null

  switch (record.type) {
    case 'select': {
      const raw = Array.isArray(sent) ? sent : [sent]

      if (
        !raw.every((one) => Number.isInteger(one) || (typeof one === 'string' && /^\d+$/.test(one)))
      )
        return { refused: 'errors.unknown-value' }

      let ids = [...new Set(raw.map(Number))]

      if (add && record.is_multiple) {
        ids = [...new Set([...(Array.isArray(before) ? before : []), ...ids])]
      }

      if (!record.is_multiple && ids.length > 1) return { refused: 'errors.one-value' }

      const found = treeOrder(record.id).filter((one) => ids.includes(one.id))

      if (found.length !== ids.length) return { refused: 'errors.unknown-value' }

      if (record.leaves_only && found.some((one) => childrenOf(record.id, one.id).length > 0)) {
        return { refused: 'errors.leaves-only' }
      }

      const inOrder = found.map((one) => one.id)

      return record.is_multiple ? inOrder : inOrder[0]!
    }
    case 'number':
      return typeof sent === 'number' ||
        (typeof sent === 'string' && sent.trim() !== '' && !isNaN(Number(sent)))
        ? Number(sent)
        : { refused: 'errors.number' }
    case 'bool':
      return [true, 1, '1', 'true', 'on', 'yes'].includes(sent as never) ? true : null
    default: {
      if (typeof sent !== 'string' && (typeof sent !== 'object' || Array.isArray(sent)))
        return { refused: 'errors.text' }

      const text = layOver(
        before && typeof before === 'object' && !Array.isArray(before) ? before : {},
        sent,
        locale,
      )

      return Object.keys(text).length > 0 ? text : null
    }
  }
}

function same(a: Held | null, b: Held | null): boolean {
  if (typeof a === 'number' && typeof b === 'number') return Math.abs(a - b) < 0.0000005

  const sorted = (one: Held | null) =>
    one && typeof one === 'object' && !Array.isArray(one)
      ? JSON.stringify(Object.fromEntries(Object.entries(one).sort()))
      : JSON.stringify(one)

  return sorted(a) === sorted(b)
}

/** `ProductValues::put()`: write one property of one product, the journal's line back. */
function put(
  product: number,
  record: PropertyRecord,
  sent: unknown,
  locale: string,
  add = false,
): HistoryChange[] | { refused: string } {
  const held = heldOf(product)
  const before = held.get(record.id) ?? null
  const after = normalise(record, sent, before, add, locale)

  if (after !== null && typeof after === 'object' && 'refused' in after) {
    return { refused: say(locale, after.refused, { property: displayName(record, locale) }) }
  }

  if (same(before, after)) return []

  if (after === null) held.delete(record.id)
  else held.set(record.id, after)

  return [
    {
      field: `properties.values.${record.id}`,
      label: displayName(record, locale),
      from: format(record, before, locale),
      to: format(record, after, locale),
    },
  ]
}

/* ------------------------------------------------------------------ catalogue's seams ----- */

/** `read()` of the part: the set in force in its order, and apart from it what is outside. */
export function propertyValues(product: number, category: number | null): Record<string, unknown> {
  const held = new Map(
    [...(stored.get(product) ?? new Map<number, Held>())].filter(
      ([id]) => propertyById(id) !== undefined,
    ),
  )
  const inSet: Record<string, Held> = {}

  for (const id of effective(category)) {
    if (held.has(id)) {
      inSet[id] = structuredClone(held.get(id)!)
      held.delete(id)
    }
  }

  return {
    'properties.values': inSet,
    'properties.outside': Object.fromEntries(
      [...held].map(([id, one]) => [id, structuredClone(one)]),
    ),
  }
}

/** The part's fields; `outside` is read only — sent back, it is not written. */
export const PROPERTY_FIELDS = ['properties.values', 'properties.outside']

/**
 * The part's checks against the set the product will have — the main category may be changing in
 * the same save. Under `properties.values.{id}`; nothing is written until every field passes.
 */
export function checkPropertyValues(
  product: number,
  parts: Record<string, unknown>,
  category: number | null,
  locale: string,
): Record<string, string[]> {
  const sent = parts['properties.values']
  const errors: Record<string, string[]> = {}

  if (sent === undefined || sent === null || typeof sent !== 'object' || Array.isArray(sent))
    return errors

  const set = effective(category)

  for (const [key, one] of Object.entries(sent as Record<string, unknown>)) {
    const record = /^\d+$/.test(key) ? propertyById(Number(key)) : undefined

    if (!record || !set.includes(record.id)) {
      errors[`properties.values.${key}`] = [say(locale, 'errors.outside-set')]
      continue
    }

    const after = normalise(record, one, stored.get(product)?.get(record.id) ?? null, false, locale)

    if (after !== null && typeof after === 'object' && 'refused' in after) {
      errors[`properties.values.${key}`] = [
        say(locale, after.refused, { property: displayName(record, locale) }),
      ]
    }
  }

  return errors
}

/** `write()` of the part, after the core's fields: the journal's lines of the one save. */
export function writePropertyValues(
  product: number,
  parts: Record<string, unknown>,
  locale: string,
): HistoryChange[] {
  const sent = parts['properties.values']

  if (sent === undefined || sent === null || typeof sent !== 'object' || Array.isArray(sent))
    return []

  const changes: HistoryChange[] = []

  for (const [key, one] of Object.entries(sent as Record<string, unknown>)) {
    const record = propertyById(Number(key))
    const written = record ? put(product, record, one, locale) : []

    if (Array.isArray(written)) changes.push(...written)
  }

  return changes
}

/** `ProductColumns`: a column for every property marked «in the list». No sort. */
export function propertyColumns(locale: string): ProductColumnInfo[] {
  return liveProperties()
    .filter((one) => one.in_list)
    .map((one) => ({ key: `p.${one.id}`, label: displayName(one, locale), sort: null }))
}

/** One row's words of those columns — only of the set in force, as everywhere (decision 6). */
export function propertyCells(
  product: number,
  category: number | null,
  locale: string,
): Record<string, unknown> {
  const cells: Record<string, unknown> = {}
  const set = effective(category)

  for (const [id, one] of stored.get(product) ?? []) {
    const record = propertyById(id)

    if (!record || !record.in_list || !set.includes(id)) continue

    const words = format(record, one, locale)

    if (words !== null) cells[`p.${id}`] = words
  }

  return cells
}

/** The kind of a property's facet (§5.1); none for a text or a property out of the filter. */
function kindOf(record: PropertyRecord): FacetInfo['kind'] | null {
  if (!record.is_filterable) return null

  switch (record.type) {
    case 'select':
      return record.is_tree ? 'tree' : 'terms'
    case 'number':
      return usesIntervals(record) ? 'terms' : 'range'
    case 'bool':
      return 'toggle'
    default:
      return null
  }
}

function filterable(): PropertyRecord[] {
  return liveProperties().filter((one) => kindOf(one) !== null)
}

/** `Facets`: `p.{id}`, the code in the default language, the kind by the type. */
export function propertyFacets(locale: string): (FacetInfo & { indexable: boolean })[] {
  return filterable().map((one) => ({
    key: `p.${one.id}`,
    code: codeIn(one, DEFAULT),
    kind: kindOf(one)!,
    label: displayName(one, locale),
    indexable: one.is_indexable,
  }))
}

/** What the facets of properties choose: ids of values or intervals, bounds, «yes». */
export type PropertyChoice = Record<number, { ids?: number[]; min?: number; max?: number }>

/**
 * Out of `facets[p.N][]` (a list) or `facets[p.N][min|max]` — `read` hands either, the list's
 * query or the bulk selection's.
 */
export function propertyChoice(read: (key: string) => unknown): PropertyChoice {
  const choice: PropertyChoice = {}

  for (const one of filterable()) {
    const raw = read(`p.${one.id}`)

    if (Array.isArray(raw)) {
      const ids = raw.map(Number).filter((id) => id > 0)

      if (ids.length > 0) choice[one.id] = { ids }
    } else if (raw && typeof raw === 'object') {
      const { min, max } = raw as { min?: unknown; max?: unknown }
      const bound = (edge: unknown) =>
        edge === null || edge === undefined || edge === '' || isNaN(Number(edge))
          ? undefined
          : Number(edge)

      if (bound(min) !== undefined || bound(max) !== undefined)
        choice[one.id] = { min: bound(min), max: bound(max) }
    }
  }

  return choice
}

/** Whether a product's value of this property is one the choice asks for. */
function fits(
  record: PropertyRecord,
  one: Held | undefined,
  wanted: { ids?: number[]; min?: number; max?: number },
): boolean {
  if (one === undefined) return false

  switch (record.type) {
    case 'select': {
      const own = Array.isArray(one) ? one : [one as number]
      // A node chosen in a tree takes whatever is under it.
      const reach = record.is_tree
        ? own.flatMap((id) => {
            const node = valueById(id)

            return node ? [id, ...ancestorsOf(node).map((each) => each.id)] : [id]
          })
        : own

      return reach.some((id) => wanted.ids?.includes(id))
    }
    case 'number': {
      const number = one as number

      if (usesIntervals(record)) {
        return intervalsOf(record.id).some(
          (each) =>
            wanted.ids?.includes(each.id) &&
            (each.min === null || number >= each.min) &&
            (each.max === null || number < each.max),
        )
      }

      return (
        (wanted.min === undefined || number >= wanted.min) &&
        (wanted.max === undefined || number <= wanted.max)
      )
    }
    case 'bool':
      return one === true && (wanted.ids?.includes(1) ?? false)
  }

  return false
}

/** A product against the choice, one facet left out — the way the engine counts a facet. */
export function propertyMatch(
  product: number,
  category: number | null,
  choice: PropertyChoice,
  without?: number,
): boolean {
  const entries = Object.entries(choice).filter(([id]) => Number(id) !== without)

  if (entries.length === 0) return true

  const set = effective(category)
  const held = stored.get(product)

  return entries.every(([id, wanted]) => {
    const record = propertyById(Number(id))

    return (
      record !== undefined && set.includes(record.id) && fits(record, held?.get(record.id), wanted)
    )
  })
}

/** The facets of properties counted over `pool`, each without its own choice. */
export function propertyCounts(
  pool: { id: number; category_id: number | null }[],
  choice: PropertyChoice,
  locale: string,
): Record<string, unknown> {
  const counted: Record<string, unknown> = {}

  for (const record of filterable()) {
    const key = `p.${record.id}`
    const kind = kindOf(record)!
    // The products this facet counts: the others' choices applied, the property in their set.
    const inPool = pool
      .filter((one) => propertyMatch(one.id, one.category_id, choice, record.id))
      .filter((one) => effective(one.category_id).includes(record.id))
      .map((one) => stored.get(one.id)?.get(record.id))
      .filter((one): one is Held => one !== undefined)
    const count = (wanted: { ids: number[] }) =>
      inPool.filter((one) => fits(record, one, wanted)).length

    if (record.type === 'select') {
      const book = ordered(
        record,
        values.filter((one) => one.property_id === record.id),
        locale,
      )

      counted[key] = {
        key,
        kind,
        values: (record.is_tree ? treeOrder(record.id) : book)
          .map((one) => ({
            value: String(one.id),
            label: valueName(one, locale),
            count: count({ ids: [one.id] }),
          }))
          .filter((one) => one.count > 0),
      }
    } else if (kind === 'terms') {
      counted[key] = {
        key,
        kind,
        values: intervalsOf(record.id)
          .map((one) => ({
            value: String(one.id),
            label: word(one.title, locale),
            count: count({ ids: [one.id] }),
          }))
          .filter((one) => one.count > 0),
      }
    } else if (kind === 'range') {
      const numbers = inPool.filter((one): one is number => typeof one === 'number')

      counted[key] = {
        key,
        kind,
        min: numbers.length > 0 ? Math.min(...numbers) : null,
        max: numbers.length > 0 ? Math.max(...numbers) : null,
      }
    } else {
      counted[key] = { key, kind, count: count({ ids: [1] }) }
    }
  }

  return counted
}

/* ---------------------------------------------------------------------- bulk actions ----- */

const ACTIONS = ['set-property', 'remove-property', 'clear-outside-set']

/** `GET /bulk`'s lines of the properties, `PartField`s as the php half sends them. */
export function propertyActions(locale: string): unknown[] {
  return ACTIONS.map((key) => ({
    key,
    label: say(locale, `bulk.${key}`),
    permission: 'catalog.manage',
    trashed: false,
    params:
      key === 'clear-outside-set'
        ? []
        : [
            {
              name: 'property_id',
              type: 'id',
              label: say(locale, 'product.property'),
              rules: ['required', 'integer'],
              values: 'catalog_properties_list',
              source: SOURCE,
            },
            {
              name: 'value',
              type: 'mixed',
              label: say(locale, 'product.value'),
              rules: [key === 'set-property' ? 'required' : 'nullable'],
            },
          ],
  }))
}

export function isPropertyAction(key: unknown): boolean {
  return ACTIONS.includes(key as string)
}

/** `rules()` of the action: a property that is there, and a value to set. */
export function checkPropertyAction(
  key: string,
  params: Record<string, unknown>,
  locale: string,
): Record<string, string[]> | null {
  if (key === 'clear-outside-set') return null

  if (!propertyById(Number(params.property_id))) {
    return { property_id: [say(locale, 'errors.unknown-property')] }
  }

  if (
    key === 'set-property' &&
    (params.value === null || params.value === undefined || params.value === '')
  ) {
    return { value: ['The value field is required.'] }
  }

  return null
}

/**
 * `apply()` on one product: its journal lines, or a refusal for this product alone — setting a
 * property its category does not have would keep a value shown nowhere.
 */
export function applyPropertyAction(
  key: string,
  product: number,
  category: number | null,
  params: Record<string, unknown>,
  locale: string,
): HistoryChange[] | { refused: string } {
  const set = effective(category)

  if (key === 'clear-outside-set') {
    const held = stored.get(product)
    const changes: HistoryChange[] = []

    for (const [id, one] of held ?? []) {
      const record = propertyById(id)

      // A property in the bin keeps its values: a restore brings them back (§3.3).
      if (!record || set.includes(id)) continue

      changes.push({
        field: `properties.values.${id}`,
        label: displayName(record, locale),
        from: format(record, one, locale),
        to: null,
      })
      held!.delete(id)
    }

    return changes
  }

  const record = propertyById(Number(params.property_id))

  if (!record) return { refused: say(locale, 'errors.unknown-property') }

  if (key === 'remove-property') {
    const before = stored.get(product)?.get(record.id)
    const named = params.value

    if (named === null || named === undefined || named === '' || record.type !== 'select')
      return put(product, record, null, locale)

    const drop = (Array.isArray(named) ? named : [named]).map(Number)

    if (!record.is_multiple) {
      return typeof before === 'number' && drop.includes(before)
        ? put(product, record, null, locale)
        : []
    }

    return put(
      product,
      record,
      (Array.isArray(before) ? before : []).filter((id) => !drop.includes(id)),
      locale,
    )
  }

  if (!set.includes(record.id)) return { refused: say(locale, 'errors.outside-set') }

  return put(product, record, params.value, locale, true)
}

/* ------------------------------------------------------------------------ the journal ----- */

/** Empty pages for now: the records keep a journal on the server, the playground does not yet. */
export function propertyHistory(type: string): HistoryPage | null {
  if (!['catalog.property', 'catalog.property-value', 'catalog.property-group'].includes(type))
    return null

  return { data: [], current_page: 1, last_page: 1, total: 0 }
}

/* ------------------------------------------------------------------------- the routes ----- */

/** The groups: the shared category API (`CategoryRoutes`), counting properties, not products. */
function mountGroups(on: On, fail: Fail): void {
  const count = (record: GroupRecord) =>
    properties.filter((one) => one.group_id === record.id && one.deleted_at === null).length
  const row = (record: GroupRecord, locale: string) => ({
    id: record.id,
    name: word(record.title, locale) || `#${record.id}`,
    title: { ...record.title },
    slug: null,
    path: null,
    url: null,
    is_visible: record.is_visible,
    position: record.position,
    properties_count: count(record),
    deleted_at: record.deleted_at,
  })
  const detail = (record: GroupRecord, locale: string) => ({
    category: row(record, locale),
    values: { ...record.extra, title: { ...record.title }, is_visible: record.is_visible },
    prefix: null,
  })
  const find = (id: string | undefined, trashed = false) => {
    const found = groups.find(
      (one) =>
        one.id === Number(id) && (trashed ? one.deleted_at !== null : one.deleted_at === null),
    )

    if (!found) throw fail(404, 'Not found.')

    return found
  }

  on('GET', `/${GROUPS}`, ({ query, locale }) => {
    const term = (query.get('q') ?? '').trim().toLowerCase()

    return {
      data: groups
        .filter((one) => one.deleted_at === null)
        .filter((one) => query.get('visible') !== '1' || one.is_visible)
        .filter(
          (one) =>
            term === '' ||
            Object.values(one.title).some((text) => text.toLowerCase().includes(term)),
        )
        .sort((a, b) => a.position - b.position || a.id - b.id)
        .map((one) => row(one, locale)),
      prefix: null,
    }
  })

  on('POST', `/${GROUPS}`, ({ body, locale }) => {
    const title = typeof body.title === 'string' ? body.title.trim() : ''

    if (title === '') throw fail(422, 'A name is needed.', { title: ['A name is needed.'] })

    const record = group(nextId(groups), '', '')

    record.title = { [locale]: title }
    record.position = Math.max(0, ...groups.map((one) => one.position)) + 1
    groups.push(record)

    return { data: row(record, locale) }
  })

  on('POST', `/${GROUPS}/reorder`, ({ body }) => {
    const ids = Array.isArray(body.ids) ? body.ids.map(Number) : []

    ids.forEach((id, index) => {
      const one = groups.find((each) => each.id === id)

      if (one) one.position = index + 1
    })

    return new Reply(204, undefined)
  })

  on('GET', `/${GROUPS}/(\\d+)`, ({ params, locale }) => ({
    data: detail(find(params[0]), locale),
  }))

  on('PUT', `/${GROUPS}/(\\d+)`, ({ params, body, locale }) => {
    const record = find(params[0])

    for (const [name, sent] of Object.entries((body.values ?? {}) as Record<string, unknown>)) {
      if (name === 'title') {
        const next = layOver(record.title, sent, locale)

        if (Object.keys(next).length === 0) {
          throw fail(422, 'A name is needed in at least one language.', {
            [`title.${locale}`]: ['A name is needed in at least one language.'],
          })
        }

        record.title = next
      } else if (name === 'is_visible') {
        record.is_visible = sent === true
      } else {
        record.extra[name] = sent
      }
    }

    return { data: detail(record, locale) }
  })

  on('DELETE', `/${GROUPS}/(\\d+)`, ({ params, locale }) => {
    const record = find(params[0])
    const held = count(record)

    if (held > 0) throw fail(422, say(locale, 'errors.group-in-use', { count: held }))

    record.deleted_at = new Date().toISOString()

    return new Reply(204, undefined)
  })

  on('POST', `/${GROUPS}/(\\d+)/restore`, ({ params, locale }) => {
    const record = find(params[0], true)

    record.deleted_at = null

    return new Reply(200, { data: row(record, locale) })
  })
}

function mountProperties(on: On, fail: Fail): void {
  const find = (id: string | undefined, trashed: 'live' | 'with' | 'only' = 'live') => {
    const found = properties.find(
      (one) =>
        one.id === Number(id) &&
        (trashed === 'with' ||
          (trashed === 'only' ? one.deleted_at !== null : one.deleted_at === null)),
    )

    if (!found) throw fail(404, 'No such property.')

    return found
  }

  on('GET', `/${SOURCE}`, ({ query }) => {
    const term = (query.get('q') ?? '').trim().toLowerCase()
    const type = query.get('type')
    const owner = query.get('group')
    const ids = query.getAll('ids[]').map(Number)
    const trashed = ['1', 'true'].includes(query.get('trashed') ?? '')

    if (type && !(TYPES as readonly string[]).includes(type)) {
      throw fail(422, 'The selected type is invalid.', { type: ['The selected type is invalid.'] })
    }

    const rows = properties
      .filter((one) => (one.deleted_at !== null) === trashed)
      .filter(
        (one) =>
          term === '' ||
          [...Object.values(one.title), ...Object.values(one.code)].some((text) =>
            text.toLowerCase().includes(term),
          ),
      )
      .filter((one) => !type || one.type === type)
      .filter((one) => ids.length === 0 || ids.includes(one.id))
      .filter((one) => !owner || one.group_id === Number(owner))
      .sort((a, b) => a.position - b.position || a.id - b.id)
      .map(propertyRow)

    return paginate(rows, query, SOURCE)
  })

  on('POST', `/${SOURCE}`, ({ body, locale }) => {
    if (!body.values || typeof body.values !== 'object') {
      throw fail(422, 'The values field is required.', {
        values: ['The values field is required.'],
      })
    }

    return answer(saveProperty(null, body.values as Record<string, unknown>, locale, fail))
  })

  on('POST', `/${SOURCE}/reorder`, ({ body }) => {
    const ids = Array.isArray(body.ids) ? body.ids.map(Number) : []

    ids.forEach((id, index) => {
      const one = properties.find((each) => each.id === id)

      if (one) one.position = index + 1
    })

    return new Reply(200, { data: { ids } })
  })

  on('GET', `/${SOURCE}/(\\d+)`, ({ params }) => answer(find(params[0], 'with')))

  on('PUT', `/${SOURCE}/(\\d+)`, ({ params, body, locale }) => {
    if (!body.values || typeof body.values !== 'object') {
      throw fail(422, 'The values field is required.', {
        values: ['The values field is required.'],
      })
    }

    return answer(
      saveProperty(find(params[0]), body.values as Record<string, unknown>, locale, fail),
    )
  })

  on('DELETE', `/${SOURCE}/(\\d+)`, ({ params }) => {
    find(params[0]).deleted_at = new Date().toISOString()

    return new Reply(204, undefined)
  })

  on('POST', `/${SOURCE}/(\\d+)/restore`, ({ params }) => {
    const record = find(params[0], 'only')

    record.deleted_at = null

    return new Reply(200, answer(record))
  })

  on('PUT', `/${SOURCE}/(\\d+)/intervals`, ({ params, body, locale }) => {
    const owner = find(params[0])

    if (!Array.isArray(body.intervals)) {
      throw fail(422, 'The intervals field must be present.', {
        intervals: ['The intervals field must be present.'],
      })
    }

    if (owner.type !== 'number') {
      const message = say(locale, 'errors.intervals-number')

      throw fail(422, message, { intervals: [message] })
    }

    const rows = body.intervals as Record<string, unknown>[]
    const map = (sent: unknown) => layOver({}, sent, locale)
    const edge = (sent: unknown) =>
      sent === null || sent === undefined || sent === '' ? null : Number(sent)
    const errors: Record<string, string[]> = {}
    const seen: Record<string, Set<string>> = {}

    rows.forEach((row, index) => {
      for (const [code, slug] of Object.entries(map(row.slug))) {
        const key = `intervals.${index}.slug.${code}`

        seen[code] ??= new Set()

        if (!CODE.test(slug)) (errors[key] ??= []).push(say(locale, 'errors.slug'))
        else if (seen[code].has(slug)) (errors[key] ??= []).push(say(locale, 'errors.slug-taken'))

        seen[code].add(slug)
      }

      const min = edge(row.min)
      const max = edge(row.max)

      if ((min !== null && isNaN(min)) || (max !== null && isNaN(max))) {
        errors[`intervals.${index}.${min !== null && isNaN(min) ? 'min' : 'max'}`] = [
          'The field must be a number.',
        ]
      } else if (min !== null && max !== null && min >= max) {
        errors[`intervals.${index}.max`] = [say(locale, 'errors.interval-ends')]
      }
    })

    if (Object.keys(errors).length > 0) throw fail(422, Object.values(errors)[0]![0]!, errors)

    const kept: IntervalRecord[] = rows.map((row, position) => {
      const id = Number(row.id)
      const found = intervals.find((one) => one.id === id && one.property_id === owner.id)
      const record: IntervalRecord = found ?? {
        id: nextId(intervals),
        property_id: owner.id,
        title: {},
        slug: {},
        min: null,
        max: null,
        position,
      }

      Object.assign(record, {
        title: map(row.title),
        slug: map(row.slug),
        min: edge(row.min),
        max: edge(row.max),
        position,
      })

      if (!found) intervals.push(record)

      return record
    })

    for (let index = intervals.length - 1; index >= 0; index--) {
      const one = intervals[index]!

      if (one.property_id === owner.id && !kept.includes(one)) intervals.splice(index, 1)
    }

    return { data: intervalsOf(owner.id).map(intervalRow) }
  })
}

function mountValues(on: On, fail: Fail): void {
  const owner = (id: string | undefined, trashed = true) => {
    const found = propertyById(Number(id), trashed)

    if (!found) throw fail(404, 'No such property.')

    return found
  }
  const node = (record: PropertyRecord, id: unknown) => {
    const found = values.find((one) => one.id === Number(id) && one.property_id === record.id)

    if (!found) throw fail(404, 'No such value.')

    return found
  }
  const refuse = (field: string, message: string) => fail(422, message, { [field]: [message] })

  on('GET', `/${SOURCE}/(\\d+)/values`, ({ params, query, locale }) => {
    const record = owner(params[0])
    const book = values.filter((one) => one.property_id === record.id)
    const ids = query.getAll('ids[]').map(Number)

    // The values a product holds, by id and at any depth, named by their path.
    if (ids.length > 0) {
      return {
        data: ordered(
          record,
          book.filter((one) => ids.includes(one.id)),
          locale,
        ).map((one) => ({
          ...valueRow(one, false),
          ancestors: ancestorsOf(one).map((each) => valueRow(each, false)),
        })),
      }
    }

    const term = (query.get('q') ?? '').trim().toLowerCase()

    if (term !== '') {
      const found = book.filter((one) =>
        [...Object.values(one.title), ...Object.values(one.slug)].some((text) =>
          text.toLowerCase().includes(term),
        ),
      )

      return paginate(
        ordered(record, found, locale).map((one) => valueRow(one, false)),
        query,
        `${SOURCE}/${record.id}/values`,
      )
    }

    const parent = query.get('parent_id')

    return {
      data: ordered(
        record,
        book.filter((one) => one.parent_id === (parent ? Number(parent) : null)),
        locale,
      ).map((one) => valueRow(one, false)),
    }
  })

  on('POST', `/${SOURCE}/(\\d+)/values`, ({ params, body, locale }) => {
    const record = owner(params[0], false)

    if (record.type !== 'select') throw refuse('property', say(locale, 'errors.values-select'))

    const parent =
      body.parent_id === null || body.parent_id === undefined ? null : node(record, body.parent_id)

    if (parent && !record.is_tree) throw refuse('parent_id', say(locale, 'errors.not-tree'))

    const fresh: ValueRecord = {
      id: nextId(values),
      property_id: record.id,
      parent_id: parent?.id ?? null,
      position: childrenOf(record.id, parent?.id ?? null).length,
      title: {},
      slug: {},
      color: null,
      image_id: null,
    }

    fillValue(fresh, body, locale, fail)
    values.push(fresh)

    return { data: valueRow(fresh) }
  })

  on('PUT', `/${SOURCE}/(\\d+)/values/(\\d+)`, ({ params, body, locale }) => {
    const one = node(owner(params[0]), params[1])

    fillValue(one, body, locale, fail)

    return { data: valueRow(one) }
  })

  on('POST', `/${SOURCE}/(\\d+)/values/(\\d+)/move`, ({ params, body, locale }) => {
    const record = owner(params[0])
    const one = node(record, params[1])
    const parent =
      body.parent_id === null || body.parent_id === undefined ? null : node(record, body.parent_id)
    const before =
      body.before_id === null || body.before_id === undefined ? null : node(record, body.before_id)

    if (parent && !record.is_tree) throw refuse('parent_id', say(locale, 'errors.not-tree'))

    const inside = subtreeIds(one)

    if (
      (parent && inside.includes(parent.id)) ||
      (before && before !== one && inside.includes(before.id))
    ) {
      throw refuse('parent_id', say(locale, 'errors.move-into-itself'))
    }

    if (before && before !== one) place(one, before.parent_id, before)
    else place(one, parent?.id ?? null, null)

    return new Reply(200, { data: valueRow(one) })
  })

  on('POST', `/${SOURCE}/(\\d+)/values/(\\d+)/merge`, ({ params, body, locale }) => {
    const record = owner(params[0])
    const from = node(record, params[1])

    if (body.into === undefined || body.into === null) {
      throw refuse('into', 'The into field is required.')
    }

    const into = node(record, body.into)

    if (from === into) throw refuse('into', say(locale, 'errors.merge-other'))
    if (subtreeIds(from).includes(into.id)) {
      throw refuse('into', say(locale, 'errors.merge-descendant'))
    }

    const holders = productsHolding(record.id, [from.id])

    if (body.dry_run === true || body.dry_run === 1 || body.dry_run === '1') {
      return new Reply(200, { data: { moved: holders.length } })
    }

    // A product holding both keeps one of `B`; the children of `A` move under `B`.
    for (const product of holders) {
      const held = stored.get(product)!
      const one = held.get(record.id)!

      if (Array.isArray(one)) {
        const next = [...new Set(one.map((id) => (id === from.id ? into.id : id)))]
        const order = treeOrder(record.id).map((each) => each.id)

        held.set(
          record.id,
          next.sort((a, b) => order.indexOf(a) - order.indexOf(b)),
        )
      } else {
        held.set(record.id, into.id)
      }
    }

    for (const child of childrenOf(record.id, from.id)) place(child, into.id, null)

    place(from, null, null)
    values.splice(values.indexOf(from), 1)
    childrenOf(record.id, null).forEach((one, index) => (one.position = index))

    return new Reply(200, { data: { moved: holders.length } })
  })

  on('DELETE', `/${SOURCE}/(\\d+)/values/(\\d+)`, ({ params, locale }) => {
    const record = owner(params[0])
    const one = node(record, params[1])
    const inside = subtreeIds(one)
    const count = productsHolding(record.id, inside).length

    if (count > 0) {
      const message = say(locale, 'errors.value-in-use', { count })

      throw fail(422, message, { value: [message] }, { meta: { products: count } })
    }

    place(one, null, null)

    for (const id of inside) values.splice(values.indexOf(valueById(id)!), 1)

    childrenOf(record.id, null).forEach((each, index) => (each.position = index))

    return new Reply(204, undefined)
  })
}

function mountSets(on: On, fail: Fail): void {
  const find = (id: string | undefined) => {
    const found = categoryOf(Number(id))

    if (!found || found.deleted_at !== null) throw fail(404, 'No such category.')

    return found
  }
  const show = (id: number, locale: string) => {
    const category = categoryOf(id)!

    return {
      data: {
        inherited: effectiveRows(category.parent_id).map((row) => ({
          property: propertyRow(propertyById(row.property)!),
          from: { id: row.from, name: word(categoryOf(row.from)?.name ?? {}, locale) },
        })),
        own: (sets.get(id) ?? [])
          .map((one) => propertyById(one))
          .filter((one): one is PropertyRecord => one !== undefined)
          .map(propertyRow),
      },
    }
  }

  on('GET', '/catalog/categories/(\\d+)/properties', ({ params, locale }) => {
    find(params[0])

    return show(Number(params[0]), locale)
  })

  on('PUT', '/catalog/categories/(\\d+)/properties', ({ params, body, locale }) => {
    const category = find(params[0])
    const id = Number(params[0])

    if (!Array.isArray(body.ids)) {
      throw fail(422, 'The ids field must be present.', { ids: ['The ids field must be present.'] })
    }

    const ids = [...new Set(body.ids.map(Number))]

    if (ids.some((one) => !propertyById(one))) {
      const message = say(locale, 'errors.unknown-property')

      throw fail(422, message, { ids: [message] })
    }

    for (const row of effectiveRows(category.parent_id)) {
      if (ids.includes(row.property)) {
        const message = say(locale, 'errors.inherited', {
          property: displayName(propertyById(row.property)!, locale),
          category: word(categoryOf(row.from)?.name ?? {}, locale) || `#${row.from}`,
        })

        throw fail(422, message, { ids: [message] })
      }
    }

    if (ids.length > 0) sets.set(id, ids)
    else sets.delete(id)

    return show(id, locale)
  })

  // The set in force, cut by the groups of the card; without a group — last, no heading.
  on('GET', '/catalog/property-sets/(\\d+)', ({ params }) => {
    find(params[0])

    const byGroup = new Map<number, PropertyRecord[]>()

    for (const id of effective(Number(params[0]))) {
      const record = propertyById(id)!
      const key = record.group_id ?? 0

      byGroup.set(key, [...(byGroup.get(key) ?? []), record])
    }

    const blocks: { group: unknown; properties: unknown[] }[] = []

    for (const one of groups
      .filter((each) => each.deleted_at === null && byGroup.has(each.id))
      .sort((a, b) => a.position - b.position || a.id - b.id)) {
      blocks.push({
        group: { id: one.id, title: { ...one.title } },
        properties: byGroup.get(one.id)!.map(propertyRow),
      })
      byGroup.delete(one.id)
    }

    const rest = [...byGroup.values()].flat()

    if (rest.length > 0) blocks.push({ group: null, properties: rest.map(propertyRow) })

    return { data: { groups: blocks } }
  })
}

/** The properties' routes under the panel's API, as `routes/api.php` of the package lays them. */
export function registerProperties(
  on: On,
  fail: Fail,
  line: Line,
  categories: CategoryLookup,
): void {
  lineOf = line
  categoryOf = categories

  mountGroups(on, fail)
  mountProperties(on, fail)
  mountValues(on, fail)
  mountSets(on, fail)
}
