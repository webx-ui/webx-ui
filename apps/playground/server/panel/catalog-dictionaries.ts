import type { Patch } from '../../../../packages/schema/src/types'
import type { FacetInfo, ProductColumnInfo } from '../../../../packages/module-catalog/src/types'
import type {
  HistoryChange,
  HistoryEntry,
  HistoryPage,
} from '../../../../packages/module-admin/src/history'

/**
 * The catalogue's three reference books in the fake server (WEBX_UI_CATALOG_DICTIONARIES.md):
 * labels, stock statuses and brands — their lists through the shared category API, and what each
 * adds to the catalogue through its registries: a field of the product form, a column and a facet
 * of the list, a bulk action. `catalog.ts` asks this file at each of those points, the way the
 * core asks `ProductParts`, `ProductColumns`, `Facets` and `BulkActions`.
 *
 * The shapes are the php half's (`LabelsColumn`, `StockColumn`, `BrandColumn`, `CategoryResource`),
 * and so are its refusals: a record still on products is not deleted, the default status neither,
 * a code is `[a-z0-9-]`, a brand's address has no `_`.
 */

type Map = Record<string, string>
type Line = (locale: string, namespace: string, path: string) => string
type Fail = (status: number, message: string, errors?: Record<string, string[]>) => Error
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

const SITE = 'https://shop.webx-demo.test'
const DEFAULT = 'ru'
const TONES = ['neutral', 'primary', 'success', 'warning', 'danger', 'info']

interface Common {
  id: number
  title: Map
  is_visible: boolean
  position: number
  extra: Record<string, unknown>
  deleted_at: string | null
}

interface LabelRecord extends Common {
  code: string
  color: string
  is_badge: boolean
}

interface StatusRecord extends Common {
  code: string
  color: string
  is_purchasable: boolean
  is_default: boolean
}

interface BrandRecord extends Common {
  slug: Map
  logo: { path: string } | null
  description: Map
  is_featured: boolean
  seo: Record<string, unknown>
}

/* ------------------------------------------------------------------------ fixtures ----- */

const labels: LabelRecord[] = [
  label(1, 'Хит', 'Top', 'top', 'primary'),
  label(2, 'Скидка', 'Sale', 'sale', 'danger'),
  label(3, 'Новинка', 'New', 'new', 'success'),
  // §7: a service label — neither a badge nor a choice of the filter.
  label(4, 'Для рассылки', 'Newsletter', 'newsletter', 'neutral', {
    is_badge: false,
    is_visible: false,
  }),
]

const statuses: StatusRecord[] = [
  status(1, 'В наличии', 'In stock', 'in-stock', 'success', true, true),
  status(2, 'Нет в наличии', 'Out of stock', 'out-of-stock', 'danger', false),
  status(3, 'Под заказ', 'On order', 'on-order', 'warning', true),
]

/* §7: eight made-up brands, three featured, one taken off the site. */
const brands: BrandRecord[] = [
  brand(1, 'Northwind', 'northwind', { is_featured: true }),
  brand(2, 'Contoso', 'contoso', { is_featured: true }),
  brand(3, 'Fabrikam', 'fabrikam'),
  brand(4, 'Tailspin', 'tailspin', { is_featured: true }),
  brand(5, 'Litware', 'litware'),
  brand(6, 'Adatum', 'adatum'),
  brand(7, 'Proseware', 'proseware', { is_visible: false }),
  brand(8, 'Woodgrove', 'woodgrove'),
]

function label(
  id: number,
  ru: string,
  en: string,
  code: string,
  color: string,
  extra: Partial<LabelRecord> = {},
): LabelRecord {
  return {
    id,
    title: { ru, en },
    code,
    color,
    is_badge: true,
    is_visible: true,
    position: id,
    extra: {},
    deleted_at: null,
    ...extra,
  }
}

function status(
  id: number,
  ru: string,
  en: string,
  code: string,
  color: string,
  purchasable: boolean,
  isDefault = false,
): StatusRecord {
  return {
    id,
    title: { ru, en },
    code,
    color,
    is_purchasable: purchasable,
    is_default: isDefault,
    is_visible: true,
    position: id,
    extra: {},
    deleted_at: null,
  }
}

function brand(
  id: number,
  name: string,
  slug: string,
  extra: Partial<BrandRecord> = {},
): BrandRecord {
  return {
    id,
    title: { ru: name, en: name },
    slug: { ru: slug, en: slug },
    logo: null,
    description: {},
    is_visible: true,
    is_featured: false,
    position: id,
    extra: {},
    seo: {},
    deleted_at: null,
    ...extra,
  }
}

/*
 * What is on the demo products, by their number — the same every start, and roughly §7's shares:
 * about eight in ten in stock (most without a row of their own, which is the default), one in ten
 * out of it, one in ten on order; a brand on most, none on every sixth.
 */
const labelsOf = new Map<number, number[]>()
const stockOf = new Map<number, number>()
const brandOf = new Map<number, number>()

for (let id = 1; id <= 400; id++) {
  const on = [
    id % 7 === 0 ? 1 : null,
    id % 5 === 0 ? 2 : null,
    id % 9 === 1 ? 3 : null,
    id % 13 === 0 ? 4 : null,
  ].filter((one): one is number => one !== null)

  if (on.length > 0) labelsOf.set(id, on)
  if (id % 10 === 3) stockOf.set(id, 2)
  else if (id % 10 === 7) stockOf.set(id, 3)
  else if (id % 4 === 0) stockOf.set(id, 1)
  if (id % 6 !== 0) brandOf.set(id, (id % 8) + 1)
}

/* ------------------------------------------------------------------------ helpers ----- */

function word(map: Map, locale: string): string {
  return map[locale] || map[DEFAULT] || map.en || Object.values(map).find(Boolean) || ''
}

function live<T extends Common>(list: T[]): T[] {
  return list.filter((one) => one.deleted_at === null).sort((a, b) => a.position - b.position)
}

function asMap(value: unknown, before: Map = {}): Map {
  if (value === null || typeof value !== 'object') return before

  const next = { ...before }

  for (const [code, text] of Object.entries(value as Record<string, unknown>)) {
    next[code] = typeof text === 'string' ? text : ''
  }

  return next
}

function slugOf(text: string): string {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 32)
}

function defaultStatus(): StatusRecord | undefined {
  return statuses.find((one) => one.is_default && one.deleted_at === null)
}

/** A product without a row of its own is in the default status (§2.2). */
function statusOf(product: number): StatusRecord | undefined {
  const row = stockOf.get(product)

  return row === undefined ? defaultStatus() : statuses.find((one) => one.id === row)
}

function labelsOn(product: number): LabelRecord[] {
  const ids = labelsOf.get(product) ?? []

  return labels.filter((one) => ids.includes(one.id)).sort((a, b) => a.position - b.position)
}

function brandOn(product: number): BrandRecord | undefined {
  const id = brandOf.get(product)

  return id === undefined ? undefined : brands.find((one) => one.id === id)
}

/* ------------------------------------------------------------------ catalogue's seams ----- */

/**
 * The satellites' cards in `catalog.product-form`, as their providers put them there: after the
 * core's «Placement», in the order the providers boot — labels, stock, brand — each landing right
 * after `placement`, so the last one booted stands first.
 */
export const productFormPatches: Patch[] = [
  [
    {
      op: 'add',
      target: 'main',
      position: 'after:placement',
      node: {
        id: 'labels-card',
        type: 'wx-card',
        label: 'trans::webx-catalog-labels::product.labels',
        children: [
          {
            id: 'labels-ids',
            type: 'wx-categories',
            name: 'labels.ids',
            help: 'trans::webx-catalog-labels::product.labels-help',
            props: {
              source: 'catalog/labels',
              main: false,
              addText: 'trans::webx-catalog-labels::product.labels-add',
              removeText: 'trans::webx-catalog-labels::product.labels-remove',
              emptyText: 'trans::webx-catalog-labels::product.labels-empty',
              noneLeftText: 'trans::webx-catalog-labels::product.labels-none-left',
            },
          },
        ],
      },
    },
  ],
  [
    {
      op: 'add',
      target: 'main',
      position: 'after:placement',
      node: {
        id: 'stock-card',
        type: 'wx-card',
        label: 'trans::webx-catalog-stock::module.title',
        children: [
          {
            id: 'stock-status',
            type: 'wx-select',
            name: 'stock.status',
            label: 'trans::webx-catalog-stock::product.status',
            help: 'trans::webx-catalog-stock::product.status-help',
            props: {
              source: 'catalog/stock',
              clearable: true,
              placeholder: 'trans::webx-catalog-stock::product.status-empty',
            },
          },
        ],
      },
    },
  ],
  [
    {
      op: 'add',
      target: 'main',
      position: 'after:placement',
      node: {
        id: 'brand-card',
        type: 'wx-card',
        label: 'trans::webx-catalog-brands::product.brand',
        children: [
          {
            id: 'brand-id',
            type: 'wx-select',
            name: 'brand.id',
            help: 'trans::webx-catalog-brands::product.brand-help',
            props: {
              source: 'catalog/brands',
              filterable: true,
              clearable: true,
              placeholder: 'trans::webx-catalog-brands::product.brand-empty',
            },
          },
        ],
      },
    },
  ],
]

/** `Facets`: three terms facets, the brand's indexable (decision 17 of the architecture). */
export function dictionaryFacets(
  locale: string,
  line: Line,
): (FacetInfo & { indexable: boolean })[] {
  return [
    {
      key: 'label',
      code: 'label',
      kind: 'terms',
      label: line(locale, 'webx-catalog-labels', 'product.labels'),
      indexable: false,
    },
    {
      key: 'stock',
      code: 'stock',
      kind: 'terms',
      label: line(locale, 'webx-catalog-stock', 'product.status'),
      indexable: false,
    },
    {
      key: 'brand',
      code: 'brand',
      kind: 'terms',
      label: line(locale, 'webx-catalog-brands', 'product.brand'),
      indexable: true,
    },
  ]
}

/** `ProductColumns`: the keys the rows carry their values under. No sort for any of them. */
export function dictionaryColumns(locale: string, line: Line): ProductColumnInfo[] {
  return [
    { key: 'labels', label: line(locale, 'webx-catalog-labels', 'product.labels'), sort: null },
    { key: 'stock', label: line(locale, 'webx-catalog-stock', 'product.status'), sort: null },
    { key: 'brand', label: line(locale, 'webx-catalog-brands', 'product.brand'), sort: null },
  ]
}

/** One row's values of those columns — service labels too: the list is where one checks them. */
export function dictionaryCells(product: number, locale: string): Record<string, unknown> {
  const cells: Record<string, unknown> = {}
  const on = labelsOn(product)
  const current = statusOf(product)
  const owner = brandOn(product)

  if (on.length > 0) {
    cells.labels = on.map((one) => ({
      id: one.id,
      name: word(one.title, locale),
      code: one.code,
      color: one.color,
    }))
  }

  if (current) {
    cells.stock = {
      id: current.id,
      name: word(current.title, locale),
      code: current.code,
      color: current.color,
      purchasable: current.is_purchasable,
    }
  }

  if (owner)
    cells.brand = { id: owner.id, name: word(owner.title, locale), visible: owner.is_visible }

  return cells
}

/** What the facets choose, out of `facets[key][]` of the list or `selection.query.facets`. */
export type DictionaryChoice = Partial<Record<'label' | 'stock' | 'brand', number[]>>

export function dictionaryChoice(read: (key: string) => unknown): DictionaryChoice {
  const choice: DictionaryChoice = {}

  for (const key of ['label', 'stock', 'brand'] as const) {
    const raw = read(key)
    const ids = (Array.isArray(raw) ? raw : []).map(Number).filter((id) => id > 0)

    if (ids.length > 0) choice[key] = ids
  }

  return choice
}

/** A product against the choice, one facet left out — the way the engine counts a facet. */
export function dictionaryMatch(
  product: number,
  choice: DictionaryChoice,
  without?: string,
): boolean {
  const wanted = (key: keyof DictionaryChoice) => (key === without ? undefined : choice[key])
  const byLabel = wanted('label')
  const byStock = wanted('stock')
  const byBrand = wanted('brand')

  // Several labels are any of them; a filter that names only hidden ones still picks by them.
  if (byLabel && !labelsOn(product).some((one) => byLabel.includes(one.id))) return false
  if (byStock && !byStock.includes(statusOf(product)?.id ?? 0)) return false
  if (byBrand && !byBrand.includes(brandOn(product)?.id ?? 0)) return false

  return true
}

/**
 * The three facets counted over `pool` — each without its own choice — in the reference book's
 * order (`OrderedFacet`): the position an editor gave, not the count. Records out of the filter
 * and brands taken off the site are not choices.
 */
export function dictionaryCounts(
  pool: number[],
  choice: DictionaryChoice,
  locale: string,
): Record<string, unknown> {
  const count = (key: string, has: (product: number) => boolean) =>
    pool.filter((product) => dictionaryMatch(product, choice, key) && has(product)).length

  const values = <T extends Common>(
    key: string,
    list: T[],
    has: (product: number, one: T) => boolean,
  ) =>
    live(list)
      .filter((one) => one.is_visible)
      .map((one) => ({
        value: String(one.id),
        label: word(one.title, locale),
        count: count(key, (product) => has(product, one)),
      }))
      .filter((one) => one.count > 0)

  return {
    label: {
      key: 'label',
      kind: 'terms',
      values: values('label', labels, (product, one) => labelsOn(product).includes(one)),
    },
    stock: {
      key: 'stock',
      kind: 'terms',
      values: values('stock', statuses, (product, one) => statusOf(product) === one),
    },
    brand: {
      key: 'brand',
      kind: 'terms',
      values: values('brand', brands, (product, one) => brandOn(product) === one),
    },
  }
}

/** `read()` of the three parts: one key with a dot each, as `values` of the form carries it. */
export function dictionaryValues(product: number): Record<string, unknown> {
  return {
    'labels.ids': (labelsOf.get(product) ?? []).slice(),
    'stock.status': statusOf(product)?.id ?? null,
    'brand.id': brandOf.get(product) ?? null,
  }
}

export const DICTIONARY_FIELDS = ['labels.ids', 'stock.status', 'brand.id']

/** `rules()` of the parts: the refusals under their field, before anything is written. */
export function checkDictionaryValues(
  values: Record<string, unknown>,
  locale: string,
  line: Line,
): Record<string, string[]> {
  const errors: Record<string, string[]> = {}
  const known = <T extends Common>(list: T[], id: unknown, trashed: boolean) =>
    list.some((one) => one.id === Number(id) && (trashed || one.deleted_at === null))

  if ('labels.ids' in values) {
    const ids = values['labels.ids']

    // A label in the bin is still on its products, so keeping it is not refused.
    if (ids !== null && (!Array.isArray(ids) || ids.some((id) => !known(labels, id, true)))) {
      errors['labels.ids'] = [line(locale, 'webx-catalog-labels', 'errors.unknown')]
    }
  }

  const status = values['stock.status']

  if (status !== undefined && status !== null && status !== '' && !known(statuses, status, false)) {
    errors['stock.status'] = [line(locale, 'webx-catalog-stock', 'errors.unknown')]
  }

  const owner = values['brand.id']

  if (owner !== undefined && owner !== null && owner !== '' && !known(brands, owner, true)) {
    errors['brand.id'] = [line(locale, 'webx-catalog-brands', 'errors.unknown')]
  }

  return errors
}

/**
 * `write()` of the parts, after the core's fields: what changed, as the journal's lines of the
 * one row the save writes. Named, not numbered — "Sale, New", not `[2, 3]`.
 */
export function writeDictionaryValues(
  product: number,
  values: Record<string, unknown>,
  locale: string,
  line: Line,
): HistoryChange[] {
  const changes: HistoryChange[] = []

  if ('labels.ids' in values) {
    const before = labelsOn(product).map((one) => word(one.title, locale))
    const ids = [...new Set(((values['labels.ids'] as unknown[] | null) ?? []).map(Number))]

    if (ids.length > 0) labelsOf.set(product, ids)
    else labelsOf.delete(product)

    const after = labelsOn(product).map((one) => word(one.title, locale))

    if (JSON.stringify(before) !== JSON.stringify(after)) {
      changes.push({
        field: 'labels.ids',
        label: line(locale, 'webx-catalog-labels', 'product.labels'),
        from: before,
        to: after,
      })
    }
  }

  if ('stock.status' in values) {
    const before = statusOf(product)
    const chosen = values['stock.status']

    // Empty takes the row away — "whatever the default is"; a chosen status is written even when
    // it is the default, so that moving the default later does not move this product (§2.2).
    if (chosen === null || chosen === '') stockOf.delete(product)
    else stockOf.set(product, Number(chosen))

    const after = statusOf(product)

    if (before !== after) {
      changes.push({
        field: 'stock.status',
        label: line(locale, 'webx-catalog-stock', 'product.status'),
        from: before ? word(before.title, locale) : null,
        to: after ? word(after.title, locale) : null,
      })
    }
  }

  if ('brand.id' in values) {
    const before = brandOn(product)
    const chosen = values['brand.id']

    if (chosen === null || chosen === '') brandOf.delete(product)
    else brandOf.set(product, Number(chosen))

    const after = brandOn(product)

    if (before !== after) {
      changes.push({
        field: 'brand.id',
        label: line(locale, 'webx-catalog-brands', 'product.brand'),
        from: before ? word(before.title, locale) : null,
        to: after ? word(after.title, locale) : null,
      })
    }
  }

  return changes
}

/* ---------------------------------------------------------------------- bulk actions ----- */

const ACTIONS = [
  {
    key: 'add-label',
    namespace: 'webx-catalog-labels',
    param: 'label_id',
    source: 'catalog/labels',
    required: true,
  },
  {
    key: 'remove-label',
    namespace: 'webx-catalog-labels',
    param: 'label_id',
    source: 'catalog/labels',
    required: true,
  },
  {
    key: 'set-stock',
    namespace: 'webx-catalog-stock',
    param: 'status_id',
    source: 'catalog/stock',
    required: true,
  },
  // One action for both ways, as the form's field is one: empty takes the brand off (§12, D3).
  {
    key: 'set-brand',
    namespace: 'webx-catalog-brands',
    param: 'brand_id',
    source: 'catalog/brands',
    required: false,
  },
]

const PARAM_LABELS: Record<string, string> = {
  label_id: 'bulk.label',
  status_id: 'product.status',
  brand_id: 'product.brand',
}

/** `GET /bulk`'s lines of the satellites: `PartField` with `source`, as the php half sends it. */
export function dictionaryActions(locale: string, line: Line): unknown[] {
  return ACTIONS.map((action) => ({
    key: action.key,
    label: line(locale, action.namespace, `bulk.${action.key}`),
    permission: 'catalog.manage',
    trashed: false,
    params: [
      {
        name: action.param,
        type: 'id',
        label: line(locale, action.namespace, PARAM_LABELS[action.param]!),
        rules: [action.required ? 'required' : 'nullable', 'integer'],
        values: `catalog_${action.source.split('/')[1]}_list`,
        source: action.source,
      },
    ],
  }))
}

export function isDictionaryAction(key: unknown): boolean {
  return ACTIONS.some((one) => one.key === key)
}

/** `rules()` of the action: a record that exists and is not in the bin. */
export function checkDictionaryAction(
  key: string,
  params: Record<string, unknown>,
  locale: string,
  line: Line,
): Record<string, string[]> | null {
  const action = ACTIONS.find((one) => one.key === key)!
  const value = params[action.param]

  if (!action.required && (value === null || value === undefined || value === '')) return null

  const list: Common[] = key === 'set-stock' ? statuses : key === 'set-brand' ? brands : labels
  const found = list.some((one) => one.id === Number(value) && one.deleted_at === null)

  return found ? null : { [action.param]: [line(locale, action.namespace, 'errors.unknown')] }
}

/** `apply()` of the action on one product: its changes, or none when it already is as asked. */
export function applyDictionaryAction(
  key: string,
  product: number,
  params: Record<string, unknown>,
  locale: string,
  line: Line,
): HistoryChange[] {
  switch (key) {
    case 'add-label':
    case 'remove-label': {
      const id = Number(params.label_id)
      const on = labelsOf.get(product) ?? []

      if ((key === 'add-label') === on.includes(id)) return []

      return writeDictionaryValues(
        product,
        { 'labels.ids': key === 'add-label' ? [...on, id] : on.filter((one) => one !== id) },
        locale,
        line,
      )
    }
    case 'set-stock':
      if (stockOf.get(product) === Number(params.status_id)) return []

      return writeDictionaryValues(
        product,
        { 'stock.status': Number(params.status_id) },
        locale,
        line,
      )
    case 'set-brand': {
      const chosen = params.brand_id

      return writeDictionaryValues(
        product,
        {
          'brand.id':
            chosen === null || chosen === undefined || chosen === '' ? null : Number(chosen),
        },
        locale,
        line,
      )
    }
  }

  return []
}

/* ------------------------------------------------------------------ the brands' journal ----- */

const journal: HistoryEntry[] = []

export function brandHistory(type: string, id: number, page: number): HistoryPage | null {
  if (type !== 'catalog.brand') return null

  const rows = journal.filter((one) => one.subject.id === id)
  const last = Math.max(1, Math.ceil(rows.length / 20))
  const current = Math.min(Math.max(1, page), last)

  return {
    data: rows.slice((current - 1) * 20, current * 20),
    current_page: current,
    last_page: last,
    total: rows.length,
  }
}

/* ------------------------------------------------------------ the category API of each ----- */

interface Book<T extends Common> {
  path: string
  namespace: string
  list: T[]
  /** Where a record's address starts, for brands; `null` — no address. */
  prefix: string | null
  values(record: T): Record<string, unknown>
  /** Takes one field of the screen; `false` — not the record's own, so it goes into `extra`. */
  take(
    record: T,
    name: string,
    value: unknown,
    errors: Record<string, string[]>,
    locale: string,
  ): boolean
  count(record: T): number
  fresh(id: number, title: Map): T
}

function row<T extends Common>(book: Book<T>, record: T, locale: string): Record<string, unknown> {
  const slug = 'slug' in record ? (record as unknown as BrandRecord).slug : null
  const path = slug && word(slug, locale) ? `${book.prefix}/${word(slug, locale)}` : null

  return {
    id: record.id,
    name: word(record.title, locale) || `#${record.id}`,
    title: record.title,
    slug,
    path,
    url: path === null ? null : `${SITE}/${locale === DEFAULT ? '' : `${locale}/`}${path}/`,
    is_visible: record.is_visible,
    position: record.position,
    products_count: record.deleted_at === null ? book.count(record) : 0,
    deleted_at: record.deleted_at,
  }
}

function detail<T extends Common>(
  book: Book<T>,
  record: T,
  locale: string,
): Record<string, unknown> {
  return {
    category: row(book, record, locale),
    values: { ...record.extra, ...book.values(record) },
    prefix: book.prefix,
  }
}

/* The products that are there, not in the bin — the catalogue's, handed in when the routes are. */
let liveProducts: () => number[] = () => []

function products(where: (product: number) => boolean): number {
  return liveProducts().filter(where).length
}

function code(
  record: LabelRecord | StatusRecord,
  list: (LabelRecord | StatusRecord)[],
  value: unknown,
  errors: Record<string, string[]>,
): void {
  const written = typeof value === 'string' ? value.trim() : ''
  const next =
    written === '' ? slugOf(word(record.title, 'en') || word(record.title, DEFAULT)) : written

  if (!/^[a-z0-9-]{1,32}$/.test(next)) {
    errors.code = ['Latin letters, digits and hyphens, up to 32.']
  } else if (list.some((one) => one.id !== record.id && one.code === next)) {
    errors.code = ['This code is taken.']
  } else {
    record.code = next
  }
}

function tone(
  record: LabelRecord | StatusRecord,
  value: unknown,
  errors: Record<string, string[]>,
): void {
  if (typeof value === 'string' && TONES.includes(value)) record.color = value
  else errors.color = ['One of the six tones.']
}

const labelBook: Book<LabelRecord> = {
  path: 'catalog/labels',
  namespace: 'webx-catalog-labels',
  list: labels,
  prefix: null,
  values: (record) => ({
    title: { ...record.title },
    code: record.code,
    color: record.color,
    is_badge: record.is_badge,
    is_visible: record.is_visible,
  }),
  take(record, name, value, errors) {
    switch (name) {
      case 'code':
        code(record, labels, value, errors)
        return true
      case 'color':
        tone(record, value, errors)
        return true
      case 'is_badge':
        record.is_badge = value === true
        return true
    }

    return false
  },
  count: (record) => products((id) => (labelsOf.get(id) ?? []).includes(record.id)),
  fresh: (id, title) => label(id, title.ru ?? '', title.en ?? '', '', 'neutral'),
}

const statusBook: Book<StatusRecord> = {
  path: 'catalog/stock',
  namespace: 'webx-catalog-stock',
  list: statuses,
  prefix: null,
  values: (record) => ({
    title: { ...record.title },
    code: record.code,
    color: record.color,
    is_purchasable: record.is_purchasable,
    is_default: record.is_default,
    is_visible: record.is_visible,
  }),
  take(record, name, value, errors, locale) {
    switch (name) {
      case 'code':
        code(record, statuses, value, errors)
        return true
      case 'color':
        tone(record, value, errors)
        return true
      case 'is_purchasable':
        record.is_purchasable = value === true
        return true
      case 'is_default':
        // Off only by putting another one on (§2.2): the default moves, it never disappears.
        if (value === true) {
          for (const one of statuses) one.is_default = one.id === record.id
        } else if (record.is_default) {
          errors.is_default = [lineOf(locale, 'webx-catalog-stock', 'errors.default-off')]
        }
        return true
    }

    return false
  },
  count: (record) => products((id) => statusOf(id) === record),
  fresh: (id, title) => status(id, title.ru ?? '', title.en ?? '', '', 'neutral', true),
}

const brandBook: Book<BrandRecord> = {
  path: 'catalog/brands',
  namespace: 'webx-catalog-brands',
  list: brands,
  prefix: 'brands',
  values: (record) => ({
    title: { ...record.title },
    slug: { ...record.slug },
    logo: record.logo,
    description: { ...record.description },
    is_visible: record.is_visible,
    is_featured: record.is_featured,
    seo: record.seo,
  }),
  take(record, name, value, errors, locale) {
    switch (name) {
      case 'slug': {
        const next = asMap(value, record.slug)

        // `_` marks a filter in an address (`/brands/apple/category_laptops`), so a slug has none.
        if (Object.values(next).some((one) => one.includes('_'))) {
          errors.slug = [lineOf(locale, 'webx-catalog-brands', 'errors.slug-underscore')]
        } else {
          record.slug = next
        }
        return true
      }
      case 'logo':
        record.logo =
          value &&
          typeof value === 'object' &&
          typeof (value as { path?: unknown }).path === 'string'
            ? { path: (value as { path: string }).path }
            : null
        return true
      case 'description':
        record.description = asMap(value, record.description)
        return true
      case 'is_featured':
        record.is_featured = value === true
        return true
      case 'seo':
        record.seo = (value ?? {}) as Record<string, unknown>
        return true
    }

    return false
  },
  count: (record) => products((id) => brandOf.get(id) === record.id),
  fresh: (id, title) => {
    const name = title.en || title.ru || ''

    return brand(id, name, slugOf(name), { title })
  },
}

let lineOf: Line = (_locale, _namespace, path) => path

/** A brand's values before and after, as the journal lists them: every field but the order. */
function brandChanges(
  before: Record<string, unknown>,
  after: Record<string, unknown>,
  locale: string,
): HistoryChange[] {
  const changes: HistoryChange[] = []
  const said = (field: string) => {
    const words = lineOf(locale, 'webx-catalog-brands', `brand.${field}`)

    // A field of the project's (`extra`) has no words of the package: it is named by its key.
    return words === `brand.${field}` ? field : words
  }

  for (const field of new Set([...Object.keys(before), ...Object.keys(after)])) {
    if (field === 'seo') continue

    const from = before[field]
    const to = after[field]

    if (JSON.stringify(from) === JSON.stringify(to)) continue

    if (field === 'title' || field === 'slug' || field === 'description') {
      const a = (from ?? {}) as Map
      const b = (to ?? {}) as Map

      for (const code of new Set([...Object.keys(a), ...Object.keys(b)])) {
        if ((a[code] ?? '') !== (b[code] ?? '')) {
          changes.push({
            field: `${field}.${code}`,
            label: `${said(field)} (${code.toUpperCase()})`,
            from: a[code] || null,
            to: b[code] || null,
          })
        }
      }

      continue
    }

    // The logo by the file's name rather than a key nobody reads.
    const shown = (value: unknown) =>
      field === 'logo' && value && typeof value === 'object'
        ? ((value as { path: string }).path.split('/').pop() ?? null)
        : (value ?? null)

    changes.push({
      field: field === 'logo' ? 'logo_id' : field,
      label: said(field === 'logo' ? 'logo_id' : field),
      from: shown(from),
      to: shown(to),
    })
  }

  return changes
}

function mount<T extends Common>(on: On, fail: Fail, book: Book<T>): void {
  const find = (id: string | undefined, trashed = false) => {
    const found = book.list.find(
      (one) => one.id === Number(id) && (trashed || one.deleted_at === null),
    )

    if (!found) throw fail(404, 'Not found.')

    return found
  }

  on('GET', `/${book.path}`, ({ query, locale }) => {
    const term = (query.get('q') ?? '').trim().toLowerCase()

    return {
      data: live(book.list)
        .filter((one) => query.get('visible') !== '1' || one.is_visible)
        .filter(
          (one) =>
            term === '' ||
            Object.values(one.title).some((text) => text.toLowerCase().includes(term)),
        )
        .map((one) => row(book, one, locale)),
      prefix: book.prefix,
    }
  })

  on('POST', `/${book.path}`, ({ body, locale }) => {
    const title = typeof body.title === 'string' ? body.title.trim() : ''

    if (title === '') throw fail(422, 'A name is needed.', { title: ['A name is needed.'] })

    const record = book.fresh(Math.max(0, ...book.list.map((one) => one.id)) + 1, {
      [locale]: title,
    })

    record.position = Math.max(0, ...book.list.map((one) => one.position)) + 1

    if ('code' in record)
      (record as unknown as LabelRecord).code = slugOf(title) || `x-${record.id}`

    book.list.push(record)

    return { data: row(book, record, locale) }
  })

  on('POST', `/${book.path}/reorder`, ({ body }) => {
    const ids = Array.isArray(body.ids) ? body.ids.map(Number) : []

    ids.forEach((id, index) => {
      const one = book.list.find((record) => record.id === id)

      if (one) one.position = index + 1
    })

    return { data: null }
  })

  on('GET', `/${book.path}/(\\d+)`, ({ params, locale }) => ({
    data: detail(book, find(params[0]), locale),
  }))

  on('PUT', `/${book.path}/(\\d+)`, ({ params, body, locale }) => {
    const record = find(params[0])
    const before = { ...record.extra, ...book.values(record) }
    const draft = structuredClone(record)
    const errors: Record<string, string[]> = {}

    for (const [name, value] of Object.entries((body.values ?? {}) as Record<string, unknown>)) {
      if (name === 'title') {
        const next = asMap(value, draft.title)

        if (Object.values(next).every((text) => !text.trim())) {
          errors[`title.${locale}`] = ['A name is needed in at least one language.']
        } else {
          draft.title = next
        }
      } else if (name === 'is_visible') {
        draft.is_visible = value === true
      } else if (!book.take(draft, name, value, errors, locale)) {
        // Not the record's own: a field a project patched on, kept in `extra` (`HasExtra`).
        draft.extra[name] = value
      }
    }

    if (Object.keys(errors).length > 0) throw fail(422, Object.values(errors)[0]![0]!, errors)

    // The default status's switch moved the others' in the draft's list, not the draft itself.
    Object.assign(record, draft)

    if (book === (brandBook as unknown as Book<T>)) {
      const changes = brandChanges(before, { ...record.extra, ...book.values(record) }, locale)

      if (changes.length > 0) {
        journal.unshift({
          id: journal.length + 1,
          event: 'updated',
          source: 'panel',
          subject: { type: 'catalog.brand', id: record.id },
          admin: { id: 1, name: 'Анна Ковальчук' },
          grant_id: null,
          changes,
          run: null,
          created_at: new Date().toISOString(),
        })
      }
    }

    return { data: detail(book, record, locale) }
  })

  on('DELETE', `/${book.path}/(\\d+)`, ({ params, locale }) => {
    const record = find(params[0])
    const count = book.count(record)

    if ('is_default' in record && (record as unknown as StatusRecord).is_default) {
      throw fail(422, lineOf(locale, 'webx-catalog-stock', 'errors.default-delete'))
    }

    if (count > 0) {
      throw fail(
        422,
        lineOf(locale, book.namespace, 'errors.in-use').replace(':count', String(count)),
      )
    }

    record.deleted_at = new Date().toISOString()

    return { data: null }
  })

  on('POST', `/${book.path}/(\\d+)/restore`, ({ params, locale }) => {
    const record = find(params[0], true)

    record.deleted_at = null

    return { data: row(book, record, locale) }
  })
}

/** The three lists under the panel's API, as `CategoryRoutes::register()` gives each of them. */
export function registerDictionaries(
  on: On,
  fail: Fail,
  line: Line,
  productIds: () => number[],
): void {
  lineOf = line
  liveProducts = productIds

  mount(on, fail, labelBook)
  mount(on, fail, statusBook)
  mount(on, fail, brandBook)
}
