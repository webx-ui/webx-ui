import type {
  CategoryNode,
  CategoryRow,
  FacetInfo,
  FacetSetting,
  ProductImage,
  ProductRow,
} from '../../../../packages/module-catalog/src/types'
import type {
  HistoryChange,
  HistoryEntry,
  HistoryPage,
} from '../../../../packages/module-admin/src/history'

/**
 * The catalogue's half of the fake server (§11.2 of WEBX_UI_MODULE_CATALOG.md): products,
 * categories as a tree, the gallery, «Deleted», the facet registry, and the journal of both.
 *
 * Only what the panel's screens ask for — the bulk actions and MCP come with K4. The shapes are
 * the server's (`ProductResource`, `CategoryTree`, `DeletedController`), and so are its refusals:
 * a taken article number names its holder, a category that is not empty says how much is in it,
 * a product without a main category cannot be published.
 */

type Map = Record<string, string>

interface ProductRecord {
  id: number
  name: Map
  slug: Map
  sku: string | null
  barcode: string | null
  summary: Map
  description: Map
  category_id: number | null
  categories: number[]
  price: number | null
  old_price: number | null
  unit: string | null
  priority: number
  is_published: boolean
  seo: Record<string, unknown>
  created_at: string
  updated_at: string
  deleted_at: string | null
}

interface CategoryRecord {
  id: number
  parent_id: number | null
  position: number
  name: Map
  slug: Map
  description: Map
  cover: { path: string } | null
  is_published: boolean
  facets: FacetSetting[] | null
  seo: Record<string, unknown>
  created_at: string
  updated_at: string
  deleted_at: string | null
}

interface ImageRecord {
  id: number
  product_id: number
  path: string
  alt: Map
  title: Map
  width: number
  height: number
  size: number
  position: number
}

export type Handler = (context: {
  params: string[]
  query: URLSearchParams
  body: Record<string, unknown>
  locale: string
}) => unknown

type Fail = (
  status: number,
  message: string,
  errors?: Record<string, string[]>,
  extra?: Record<string, unknown>,
) => Error

type Line = (locale: string, namespace: string, path: string) => string

const SITE = 'https://shop.webx-demo.test'
const PANEL = '/panel'
const DEFAULT = 'ru'

const at = (days: number, hours = 10) =>
  new Date(Date.UTC(2026, 8, 28 - days, hours, 0, 0)).toISOString()

/* ------------------------------------------------------------------------ fixtures ----- */

const categories: CategoryRecord[] = []

function category(
  id: number,
  parent: number | null,
  position: number,
  ru: string,
  en: string,
  slug: string,
  extra: Partial<CategoryRecord> = {},
): void {
  categories.push({
    id,
    parent_id: parent,
    position,
    name: { ru, en },
    slug: { ru: slug, en: slug },
    description: {},
    cover: null,
    is_published: true,
    facets: null,
    seo: {},
    created_at: at(60),
    updated_at: at(30),
    deleted_at: null,
    ...extra,
  })
}

category(1, null, 1, 'Электроника', 'Electronics', 'elektronika')
category(2, 1, 1, 'Ноутбуки', 'Laptops', 'noutbuki', {
  // Its own setting: the price first, the category tree hidden — a leaf has nothing below it.
  facets: [
    { key: 'price', visible: true },
    { key: 'category', visible: false },
  ],
})
category(3, 1, 2, 'Смартфоны', 'Smartphones', 'smartfony')
category(4, 3, 1, 'Android', 'Android', 'android')
category(5, 3, 2, 'iPhone', 'iPhone', 'iphone')
category(6, null, 2, 'Бытовая техника', 'Home appliances', 'bytovaya-tehnika')
category(7, 6, 1, 'Чайники', 'Kettles', 'chayniki')
category(8, 6, 2, 'Пылесосы', 'Vacuum cleaners', 'pylesosy')
category(9, null, 3, 'Аксессуары', 'Accessories', 'aksessuary', { is_published: false })
category(10, 9, 1, 'Чехлы', 'Cases', 'chehly')
category(11, null, 4, 'Распродажа', 'Sale', 'rasprodazha', { deleted_at: at(3) })

const products: ProductRecord[] = []

const CATALOGUE: [string, string, number | null, number, string][] = [
  ['Ноутбук Aero 14', 'Aero 14 laptop', 2, 89990, 'pcs'],
  ['Ноутбук Aero 16', 'Aero 16 laptop', 2, 119990, 'pcs'],
  ['Ноутбук Slate 13', 'Slate 13 laptop', 2, 74990, 'pcs'],
  ['Смартфон Nova 8', 'Nova 8 phone', 4, 32990, 'pcs'],
  ['Смартфон Nova 8 Pro', 'Nova 8 Pro phone', 4, 45990, 'pcs'],
  ['iPhone 17', 'iPhone 17', 5, 99990, 'pcs'],
  ['iPhone 17 Pro', 'iPhone 17 Pro', 5, 129990, 'pcs'],
  ['Чайник Aqua 1,7 л', 'Aqua kettle 1.7 l', 7, 3490, 'pcs'],
  ['Чайник Steel', 'Steel kettle', 7, 4990, 'pcs'],
  ['Чайник Glass с подсветкой', 'Glass kettle with light', 7, 5990, 'pcs'],
  ['Пылесос Cyclone', 'Cyclone vacuum', 8, 15990, 'pcs'],
  ['Пылесос Robo S', 'Robo S robot vacuum', 8, 24990, 'pcs'],
  ['Чехол для Nova 8', 'Nova 8 case', 10, 990, 'pcs'],
  ['Чехол для iPhone 17', 'iPhone 17 case', 10, 1490, 'pcs'],
  ['Кабель USB-C, 1 м', 'USB-C cable, 1 m', 1, 590, 'pcs'],
  ['Кабель USB-C, 2 м', 'USB-C cable, 2 m', 1, 790, 'pcs'],
  ['Удлинитель на 5 розеток', '5-socket extension lead', 6, 1290, 'pcs'],
  ['Фильтр для чайника', 'Kettle filter', 7, 390, 'pack'],
  ['Мешки для пылесоса', 'Vacuum cleaner bags', 8, 690, 'pack'],
  ['Провод медный', 'Copper wire', null, 120, 'm'],
  ['Термопаста', 'Thermal paste', null, 450, 'g'],
  ['Подставка для ноутбука', 'Laptop stand', 2, 2490, 'pcs'],
  ['Защитное стекло Nova 8', 'Nova 8 screen protector', 4, 690, 'pcs'],
  ['Сетевой фильтр', 'Surge protector', 6, 1890, 'pcs'],
  ['Наушники Pulse', 'Pulse earphones', 1, 5990, 'pcs'],
  ['Колонка Boom Mini', 'Boom Mini speaker', 1, 3990, 'pcs'],
  ['Power bank 10 000 мА·ч', 'Power bank 10,000 mAh', 1, 2290, 'pcs'],
  ['Зарядное устройство 65 Вт', '65 W charger', 1, 2790, 'pcs'],
]

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
    ц: 'ts',
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

CATALOGUE.forEach(([ru, en, main, price, unit], index) => {
  const id = index + 1

  products.push({
    id,
    name: { ru, en },
    slug: { ru: slugOf(ru), en: slugOf(en) },
    sku: `WX-${String(1000 + id * 7)}`,
    barcode: index % 3 === 0 ? `46000000${String(10000 + id)}` : null,
    summary: {},
    description: {},
    category_id: main,
    // A few filed twice: a cable is electronics and an accessory both.
    categories: id === 15 || id === 16 ? [10] : id === 23 ? [10] : [],
    price,
    old_price: index % 5 === 0 ? Math.round(price * 1.15) : null,
    unit,
    priority: id === 1 || id === 6 ? 10 : 0,
    // Two drafts, two without a category (which cannot be published), one hidden by its shelf.
    is_published: main !== null && id !== 3 && id !== 9,
    seo: {},
    created_at: at(40 - index),
    updated_at: at(Math.max(0, 20 - index)),
    deleted_at: null,
  })
})

/* Two in «Deleted»: one whose category is still there, one whose category went too. */
products.push({
  ...products[0]!,
  id: 90,
  name: { ru: 'Ноутбук Aero 13 (2024)', en: 'Aero 13 laptop (2024)' },
  slug: { ru: 'noutbuk-aero-13-2024', en: 'aero-13-laptop-2024' },
  sku: 'WX-0090',
  deleted_at: at(5),
})
products.push({
  ...products[7]!,
  id: 91,
  name: { ru: 'Чайник «Распродажа»', en: 'Sale kettle' },
  slug: { ru: 'chaynik-rasprodazha', en: 'sale-kettle' },
  sku: 'WX-0091',
  category_id: 11,
  deleted_at: at(2),
})

const images: ImageRecord[] = []

/* A picture per product that has one: drawn, not fetched, so the playground works offline. */
for (const product of products.filter((one) => one.id % 4 !== 0)) {
  images.push({
    id: product.id * 10,
    product_id: product.id,
    path: `catalog/0/${product.id}/main.svg`,
    alt: { ru: product.name.ru ?? '', en: '' },
    title: {},
    width: 800,
    height: 800,
    size: 2048,
    position: 1,
  })

  if (product.id === 1) {
    images.push({
      id: 11,
      product_id: 1,
      path: 'catalog/0/1/side.svg',
      alt: { ru: 'Вид сбоку', en: 'Side view' },
      title: {},
      width: 800,
      height: 800,
      size: 2048,
      position: 2,
    })
  }
}

/** Uploaded bytes, by path. Everything else under `catalog/` is drawn on request. */
const uploads = new Map<string, { mime: string; bytes: Buffer }>()

const journal: HistoryEntry[] = []

/* ------------------------------------------------------------------------ helpers ----- */

function word(map: Map, locale: string): string {
  return map[locale] || map[DEFAULT] || Object.values(map).find((text) => text) || ''
}

function live<T extends { deleted_at: string | null }>(list: T[]): T[] {
  return list.filter((one) => one.deleted_at === null)
}

function categoryById(id: number, withDeleted = false): CategoryRecord | undefined {
  return categories.find((one) => one.id === id && (withDeleted || one.deleted_at === null))
}

function productById(id: number, withDeleted = false): ProductRecord | undefined {
  return products.find((one) => one.id === id && (withDeleted || one.deleted_at === null))
}

function childrenOf(id: number | null): CategoryRecord[] {
  return live(categories)
    .filter((one) => one.parent_id === id)
    .sort((a, b) => a.position - b.position)
}

/** The category and everything under it. */
function subtree(id: number): number[] {
  return [id, ...childrenOf(id).flatMap((child) => subtree(child.id))]
}

function categoryVisible(record: CategoryRecord): boolean {
  if (!record.is_published || record.deleted_at !== null) return false
  if (record.parent_id === null) return true

  const parent = categoryById(record.parent_id)

  return parent === undefined ? true : categoryVisible(parent)
}

function productVisible(record: ProductRecord): boolean {
  if (!record.is_published || record.deleted_at !== null) return false

  return [record.category_id, ...record.categories].some((id) => {
    const found = id === null ? undefined : categoryById(id)

    return found !== undefined && categoryVisible(found)
  })
}

function productsIn(id: number): number {
  const ids = new Set(subtree(id))

  return live(products).filter(
    (one) =>
      (one.category_id !== null && ids.has(one.category_id)) ||
      one.categories.some((extra) => ids.has(extra)),
  ).length
}

function depthOf(record: CategoryRecord): number {
  let depth = 0
  let parent = record.parent_id === null ? undefined : categoryById(record.parent_id, true)

  while (parent) {
    depth += 1
    parent = parent.parent_id === null ? undefined : categoryById(parent.parent_id, true)
  }

  return depth
}

function imageUrl(path: string): string {
  return `/fixtures/${path}`
}

function imagesOf(product: number): ImageRecord[] {
  return images.filter((one) => one.product_id === product).sort((a, b) => a.position - b.position)
}

function imageRow(record: ImageRecord): ProductImage {
  return {
    id: record.id,
    path: record.path,
    url: imageUrl(record.path),
    thumb: imageUrl(record.path),
    alt: record.alt,
    title: record.title,
    width: record.width,
    height: record.height,
    size: record.size,
    position: record.position,
  }
}

function state(record: ProductRecord): ProductRow['state'] {
  if (record.deleted_at !== null) return 'deleted'

  return record.is_published ? 'published' : 'unpublished'
}

function productRow(record: ProductRecord, locale: string): ProductRow {
  const main = record.category_id === null ? undefined : categoryById(record.category_id, true)
  const first = imagesOf(record.id)[0]

  return {
    id: record.id,
    name: word(record.name, locale),
    sku: record.sku,
    barcode: record.barcode,
    price: record.price,
    old_price: record.old_price,
    unit: record.unit,
    priority: record.priority,
    is_published: record.is_published,
    state: state(record),
    visible: productVisible(record),
    category: main
      ? { id: main.id, name: word(main.name, locale), deleted: main.deleted_at !== null }
      : null,
    image: first ? { id: first.id, url: imageUrl(first.path), thumb: imageUrl(first.path) } : null,
    url:
      record.deleted_at === null
        ? `${SITE}/${locale === DEFAULT ? '' : `${locale}/`}${word(record.slug, locale)}-${record.id}`
        : null,
    created_at: record.created_at,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
  }
}

function productValues(record: ProductRecord): Record<string, unknown> {
  return {
    name: record.name,
    slug: record.slug,
    sku: record.sku,
    barcode: record.barcode,
    summary: record.summary,
    description: record.description,
    category_id: record.category_id,
    categories: record.categories,
    price: record.price,
    old_price: record.old_price,
    unit: record.unit,
    priority: record.priority,
    is_published: record.is_published,
    seo: record.seo,
  }
}

function productDetail(record: ProductRecord, locale: string) {
  return {
    product: productRow(record, locale),
    values: productValues(record),
    images: imagesOf(record.id).map(imageRow),
  }
}

function categoryUrl(record: CategoryRecord, locale: string): string {
  return `${SITE}/${locale === DEFAULT ? '' : `${locale}/`}${word(record.slug, locale)}/`
}

function categoryRow(record: CategoryRecord, locale: string): CategoryRow {
  return {
    id: record.id,
    parent_id: record.parent_id,
    name: word(record.name, locale),
    slug: word(record.slug, locale) || null,
    depth: depthOf(record),
    is_published: record.is_published,
    visible: categoryVisible(record),
    products_count: record.deleted_at === null ? productsIn(record.id) : 0,
    url: categoryUrl(record, locale),
    created_at: record.created_at,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
  }
}

function node(record: CategoryRecord, locale: string): CategoryNode {
  const row: Partial<CategoryRow> = categoryRow(record, locale)

  // A node of the tree is the row without its dates, as `CategoryTree` builds it.
  delete row.created_at
  delete row.updated_at
  delete row.deleted_at

  return {
    ...(row as CategoryNode),
    children: childrenOf(record.id).map((child) => node(child, locale)),
  }
}

/** Roots, and anything whose parent is gone — the way `CategoryTree` puts orphans at the top. */
function treeOf(locale: string): CategoryNode[] {
  return live(categories)
    .filter((one) => one.parent_id === null || categoryById(one.parent_id) === undefined)
    .sort((a, b) => a.position - b.position)
    .map((one) => node(one, locale))
}

function categoryValues(record: CategoryRecord): Record<string, unknown> {
  return {
    name: record.name,
    slug: record.slug,
    description: record.description,
    cover: record.cover,
    is_published: record.is_published,
    facets: record.facets,
    seo: record.seo,
  }
}

function paginate<T>(rows: T[], query: URLSearchParams) {
  const perPage = Math.min(100, Math.max(1, Number(query.get('per_page') ?? 20) || 20))
  const last = Math.max(1, Math.ceil(rows.length / perPage))
  const page = Math.min(last, Math.max(1, Number(query.get('page') ?? 1) || 1))
  const from = rows.length === 0 ? null : (page - 1) * perPage + 1

  return {
    data: rows.slice((page - 1) * perPage, page * perPage),
    current_page: page,
    last_page: last,
    per_page: perPage,
    total: rows.length,
    from,
    to: from === null ? null : Math.min(rows.length, page * perPage),
  }
}

function matches(term: string, ...texts: (string | null | undefined | Map)[]): boolean {
  const needle = term.trim().toLowerCase()

  if (needle === '') return true

  return texts.some((text) => {
    if (text === null || text === undefined) return false
    if (typeof text === 'string') return text.toLowerCase().includes(needle)

    return Object.values(text).some((one) => one.toLowerCase().includes(needle))
  })
}

/** Laid over what is there, language by language — the form sends every language it has. */
function mergeMap(before: Map, value: unknown): Map {
  if (value === null || typeof value !== 'object') return before

  const next = { ...before }

  for (const [code, text] of Object.entries(value as Record<string, unknown>)) {
    next[code] = typeof text === 'string' ? text : ''
  }

  return next
}

function now(): string {
  return new Date().toISOString()
}

function nextId(list: { id: number }[]): number {
  return Math.max(0, ...list.map((one) => one.id)) + 1
}

/* ------------------------------------------------------------------------ journal ----- */

const ANNA = { id: 1, name: 'Анна Ковальчук' }

function record(
  type: string,
  id: number,
  event: HistoryEntry['event'],
  changes: HistoryChange[],
): void {
  journal.unshift({
    id: journal.length + 1,
    event,
    source: 'panel',
    subject: { type, id },
    admin: ANNA,
    grant_id: null,
    changes,
    run: null,
    created_at: now(),
  })
}

function diff(
  before: Record<string, unknown>,
  after: Record<string, unknown>,
  label: (field: string) => string,
): HistoryChange[] {
  const changes: HistoryChange[] = []

  for (const field of Object.keys(after)) {
    if (field === 'seo') continue

    const from = before[field]
    const to = after[field]

    if (JSON.stringify(from) === JSON.stringify(to)) continue

    if (from && to && typeof from === 'object' && typeof to === 'object' && !Array.isArray(to)) {
      const codes = new Set([...Object.keys(from), ...Object.keys(to)])

      for (const code of codes) {
        const a = (from as Map)[code] ?? null
        const b = (to as Map)[code] ?? null

        if (a !== b) {
          changes.push({
            field: `${field}.${code}`,
            label: `${label(field)} (${code.toUpperCase()})`,
            from: a,
            to: b,
          })
        }
      }

      continue
    }

    changes.push({ field, label: label(field), from: from ?? null, to: to ?? null })
  }

  return changes
}

export function catalogHistory(type: string, id: number, page: number): HistoryPage | null {
  if (type !== 'catalog.product' && type !== 'catalog.category') return null

  const rows = journal.filter((one) => one.subject.type === type && one.subject.id === id)
  const last = Math.max(1, Math.ceil(rows.length / 20))
  const current = Math.min(Math.max(1, page), last)

  return {
    data: rows.slice((current - 1) * 20, current * 20),
    current_page: current,
    last_page: last,
    total: rows.length,
  }
}

/* ------------------------------------------------------------------------ pictures ----- */

/** A picture for a path that was never uploaded: a tile with the product's initials. */
function drawn(path: string): string {
  const id = Number(/catalog\/\d+\/(\d+)\//.exec(path)?.[1] ?? 0)
  const product = productById(id, true)
  const hue = (id * 47) % 360
  const initials = (product?.name.en ?? '?')
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0] ?? '')
    .join('')
    .toUpperCase()
  const side = path.endsWith('side.svg')

  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800">
  <rect width="800" height="800" fill="hsl(${hue} 55% ${side ? 82 : 88}%)"/>
  <circle cx="400" cy="360" r="${side ? 150 : 210}" fill="hsl(${hue} 50% 60%)"/>
  <text x="400" y="400" font-family="system-ui, sans-serif" font-size="${side ? 110 : 150}" font-weight="700" text-anchor="middle" fill="#fff">${initials}</text>
  <text x="400" y="700" font-family="system-ui, sans-serif" font-size="40" text-anchor="middle" fill="hsl(${hue} 40% 30%)">${side ? 'side' : 'front'}</text>
</svg>`
}

/** `GET /fixtures/catalog/…` — an upload's own bytes, or a drawn tile. */
export function catalogBytes(path: string): { mime: string; bytes: Buffer } | null {
  const uploaded = uploads.get(path)

  if (uploaded) return uploaded
  if (!path.startsWith('catalog/')) return null

  return { mime: 'image/svg+xml; charset=utf-8', bytes: Buffer.from(drawn(path)) }
}

const PICTURES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']

/** `POST …/products/{id}/images` with a file — the one catalogue request that is not JSON. */
export function catalogUpload(
  productId: number,
  file: { fileName: string; mime: string; bytes: Buffer } | undefined,
  fail: Fail,
): ProductImage {
  const product = productById(productId)

  if (!product) throw fail(404, 'No such product.')
  if (!file) throw fail(422, 'Expected a file.', { file: ['Expected a file.'] })

  if (!PICTURES.includes(file.mime)) {
    const message =
      'The address answered with something that is not a JPEG, PNG, WebP or GIF picture.'

    throw fail(422, message, { file: [message] })
  }

  const extension = file.mime.split('/')[1] === 'jpeg' ? 'jpg' : file.mime.split('/')[1]
  const path = `catalog/0/${productId}/${Date.now().toString(16)}.${extension}`

  uploads.set(path, { mime: file.mime, bytes: file.bytes })

  return addImage(product, path, file.bytes.length)
}

function addImage(product: ProductRecord, path: string, size: number): ProductImage {
  const record: ImageRecord = {
    id: nextId(images),
    product_id: product.id,
    path,
    alt: {},
    title: {},
    width: 800,
    height: 800,
    size,
    position: Math.max(0, ...imagesOf(product.id).map((one) => one.position)) + 1,
  }

  images.push(record)
  record_images(product.id, null, path.split('/').pop() ?? path)

  return imageRow(record)
}

function record_images(product: number, from: string | null, to: string | null): void {
  record('catalog.product', product, 'updated', [{ field: 'images', label: 'Картинки', from, to }])
}

/* ------------------------------------------------------------------------ routes ----- */

export function registerCatalog(
  on: (method: string, pattern: string, handler: Handler) => void,
  fail: Fail,
  line: Line,
): void {
  const label = (group: 'product' | 'category', locale: string) => (field: string) =>
    line(locale, 'webx-catalog', `${group}.${field}`)

  const findProduct = (id: string | number) => {
    const found = productById(Number(id))

    if (!found) throw fail(404, 'No such product.')

    return found
  }

  const findCategory = (id: string | number) => {
    const found = categoryById(Number(id))

    if (!found) throw fail(404, 'No such category.')

    return found
  }

  /* The facets of the core (§7.1): the category tree and, while prices are on, the price. */
  const facets = (locale: string): FacetInfo[] => [
    {
      key: 'category',
      code: 'category',
      kind: 'tree',
      label: line(locale, 'webx-catalog', 'module.category'),
    },
    {
      key: 'price',
      code: 'price',
      kind: 'range',
      label: line(locale, 'webx-catalog', 'product.price'),
    },
  ]

  on('GET', '/catalog/facets', ({ locale }) => ({ data: facets(locale) }))

  on('GET', '/catalog/products', ({ query, locale }) => {
    const term = query.get('q') ?? ''
    const wanted = query.getAll('facets[category][]').map(Number)
    const inside = new Set(wanted.flatMap((id) => subtree(id)))
    const min = query.get('facets[price][min]')
    const max = query.get('facets[price][max]')

    let found = live(products).filter((one) => matches(term, one.name, one.sku))

    switch (query.get('state')) {
      case 'published':
        found = found.filter((one) => one.is_published)
        break
      case 'unpublished':
        found = found.filter((one) => !one.is_published)
        break
      case 'no-category':
        found = found.filter((one) => one.category_id === null)
        break
    }

    if (inside.size > 0) {
      found = found.filter((one) =>
        [one.category_id, ...one.categories].some((id) => id !== null && inside.has(id)),
      )
    }

    if (min !== null) found = found.filter((one) => one.price !== null && one.price >= Number(min))
    if (max !== null) found = found.filter((one) => one.price !== null && one.price <= Number(max))

    const sort = query.get('sort') ?? 'default'
    const byName = (a: ProductRecord, b: ProductRecord) =>
      word(a.name, locale).localeCompare(word(b.name, locale), locale)
    const price = (one: ProductRecord) => one.price ?? Number.POSITIVE_INFINITY

    found.sort((a, b) => {
      switch (sort) {
        case 'new':
          return b.created_at.localeCompare(a.created_at) || b.id - a.id
        case 'name':
          return byName(a, b) || b.id - a.id
        case 'price_asc':
          return price(a) - price(b) || b.id - a.id
        case 'price_desc':
          return (b.price ?? -1) - (a.price ?? -1) || b.id - a.id
        default:
          return b.priority - a.priority || b.created_at.localeCompare(a.created_at) || b.id - a.id
      }
    })

    return {
      ...paginate(
        found.map((one) => productRow(one, locale)),
        query,
      ),
      counts: { no_category: live(products).filter((one) => one.category_id === null).length },
    }
  })

  on('GET', '/catalog/products/(\\d+)', ({ params, locale }) => ({
    data: productDetail(findProduct(params[0]!), locale),
  }))

  /** One door for creating and saving: the values of `catalog.product-form`, whichever were sent. */
  const writeProduct = (
    product: ProductRecord,
    values: Record<string, unknown>,
    locale: string,
  ): void => {
    const before = productValues(product)
    const next: ProductRecord = { ...product, categories: [...product.categories] }

    for (const [name, value] of Object.entries(values)) {
      switch (name) {
        case 'name':
        case 'slug':
        case 'summary':
        case 'description':
          next[name] = mergeMap(product[name], value)
          break
        case 'sku':
        case 'barcode':
        case 'unit':
          next[name] = typeof value === 'string' && value.trim() !== '' ? value.trim() : null
          break
        case 'price':
        case 'old_price':
          next[name] =
            typeof value === 'number' ? value : value === '' || value == null ? null : Number(value)
          break
        case 'priority':
          next.priority = Number(value ?? 0) || 0
          break
        case 'is_published':
          next.is_published = Boolean(value)
          break
        case 'category_id':
          next.category_id = typeof value === 'number' ? value : null
          break
        case 'categories':
          next.categories = Array.isArray(value) ? value.map(Number) : []
          break
        case 'seo':
          next.seo = (value ?? {}) as Record<string, unknown>
          break
        default:
          throw fail(422, `The field ${name} is not on the screen.`, { [name]: ['Unknown field.'] })
      }
    }

    // A new main category that was an additional one stops being additional (decision 2).
    next.categories = [...new Set(next.categories)].filter((id) => id !== next.category_id)

    const errors: Record<string, string[]> = {}

    if (Object.values(next.name).every((text) => text.trim() === '')) {
      errors[`name.${locale}`] = [line(locale, 'webx-catalog', 'errors.name-required')]
    }

    for (const id of [next.category_id, ...next.categories]) {
      if (id !== null && !categoryById(id)) {
        errors[id === next.category_id ? 'category_id' : 'categories'] = [
          line(locale, 'webx-catalog', 'errors.unknown-category'),
        ]
      }
    }

    if (next.is_published && next.category_id === null) {
      errors.category_id = [line(locale, 'webx-catalog', 'errors.publish-needs-category')]
    }

    let takenBy: Record<string, unknown> | undefined

    if (next.sku !== null) {
      // Unique across the deleted too (decision 5): orders will point at those.
      const holder = products.find((one) => one.id !== next.id && one.sku === next.sku)

      if (holder) {
        const name = word(holder.name, locale)
        const message = line(locale, 'webx-catalog', 'errors.sku-taken').replace(':name', name)

        errors.sku = [message]
        takenBy = {
          id: holder.id,
          name,
          url: `${PANEL}/catalog/products/${holder.id}`,
          deleted: holder.deleted_at !== null,
        }
      }
    }

    if (Object.keys(errors).length > 0) {
      throw fail(
        422,
        Object.values(errors)[0]![0]!,
        errors,
        takenBy ? { meta: { taken_by: takenBy } } : undefined,
      )
    }

    // Empty slug: made of the name, in every language that has one (§4).
    for (const [code, text] of Object.entries(next.name)) {
      if (!next.slug[code] && text) next.slug[code] = slugOf(text)
    }

    const created = !products.includes(product)

    Object.assign(product, next, { updated_at: now() })

    if (created) {
      products.push(product)

      return
    }

    const changes = diff(before, productValues(product), label('product', locale))
    const event =
      product.is_published && !before.is_published
        ? 'published'
        : !product.is_published && before.is_published
          ? 'unpublished'
          : 'updated'

    if (changes.length > 0) record('catalog.product', product.id, event, changes)
  }

  on('POST', '/catalog/products', ({ body, locale }) => {
    const product: ProductRecord = {
      id: nextId(products),
      name: {},
      slug: {},
      sku: null,
      barcode: null,
      summary: {},
      description: {},
      category_id: null,
      categories: [],
      price: null,
      old_price: null,
      unit: 'pcs',
      priority: 0,
      is_published: false,
      seo: {},
      created_at: now(),
      updated_at: now(),
      deleted_at: null,
    }

    writeProduct(product, (body.values ?? {}) as Record<string, unknown>, locale)

    return { data: productDetail(product, locale) }
  })

  on('PUT', '/catalog/products/(\\d+)', ({ params, body, locale }) => {
    const product = findProduct(params[0]!)

    writeProduct(product, (body.values ?? {}) as Record<string, unknown>, locale)

    return { data: productDetail(product, locale) }
  })

  on('DELETE', '/catalog/products/(\\d+)', ({ params }) => {
    const product = findProduct(params[0]!)

    product.deleted_at = now()
    record('catalog.product', product.id, 'deleted', [])

    return null
  })

  on('POST', '/catalog/products/(\\d+)/restore', ({ params, locale }) => {
    const product = productById(Number(params[0]), true)

    if (!product || product.deleted_at === null) throw fail(404, 'No such product.')

    product.deleted_at = null

    // Its main category went to «Deleted» meanwhile: back without one, and unpublished (§6.3).
    if (product.category_id !== null && !categoryById(product.category_id)) {
      product.category_id = null
      product.is_published = false
    }

    record('catalog.product', product.id, 'restored', [])

    return { data: productRow(product, locale) }
  })

  /* A picture by address: the fake server does not fetch it, it files the address as the file. */
  on('POST', '/catalog/products/(\\d+)/images', ({ params, body }) => {
    const product = findProduct(params[0]!)
    const url = typeof body.url === 'string' ? body.url.trim() : ''

    if (!/^https?:\/\//.test(url)) {
      throw fail(422, 'The address did not answer with a file.', {
        url: ['The address did not answer with a file.'],
      })
    }

    const image = addImage(
      product,
      `catalog/0/${product.id}/by-address-${Date.now().toString(16)}.svg`,
      0,
    )
    const stored = images.find((one) => one.id === image.id)!

    uploads.set(stored.path, {
      mime: 'image/svg+xml; charset=utf-8',
      bytes: Buffer.from(drawn(stored.path)),
    })

    return { data: image }
  })

  /** The whole gallery, in its order, with every picture's words (§11.2). */
  on('PUT', '/catalog/products/(\\d+)/images', ({ params, body, locale }) => {
    const product = findProduct(params[0]!)
    const sent = Array.isArray(body.images) ? (body.images as Record<string, unknown>[]) : []
    const own = imagesOf(product.id)
    const ids = sent.map((one) => Number(one.id))

    if (ids.length !== own.length || own.some((one) => !ids.includes(one.id))) {
      const message = line(locale, 'webx-catalog', 'errors.images-mismatch')

      throw fail(422, message, { images: [message] })
    }

    sent.forEach((one, index) => {
      const image = own.find((each) => each.id === Number(one.id))!

      image.position = index + 1
      image.alt = mergeMap(image.alt, one.alt)
      image.title = mergeMap(image.title, one.title)
    })

    return { data: imagesOf(product.id).map(imageRow) }
  })

  on('DELETE', '/catalog/products/(\\d+)/images/(\\d+)', ({ params }) => {
    const product = findProduct(params[0]!)
    const at = images.findIndex(
      (one) => one.id === Number(params[1]) && one.product_id === product.id,
    )

    if (at < 0) throw fail(404, 'No such picture.')

    const [gone] = images.splice(at, 1)

    uploads.delete(gone!.path)
    record_images(product.id, gone!.path.split('/').pop() ?? gone!.path, null)

    return null
  })

  on('GET', '/catalog/categories', ({ locale }) => ({ data: treeOf(locale) }))

  on('GET', '/catalog/categories/(\\d+)', ({ params, locale }) => {
    const found = findCategory(params[0]!)

    return { data: { category: categoryRow(found, locale), values: categoryValues(found) } }
  })

  const writeCategory = (
    target: CategoryRecord,
    values: Record<string, unknown>,
    locale: string,
  ): void => {
    const before = categoryValues(target)
    const next: CategoryRecord = { ...target }

    for (const [name, value] of Object.entries(values)) {
      switch (name) {
        case 'name':
        case 'slug':
        case 'description':
          next[name] = mergeMap(target[name], value)
          break
        case 'cover':
          next.cover = value && typeof value === 'object' ? (value as { path: string }) : null
          break
        case 'is_published':
          next.is_published = Boolean(value)
          break
        case 'facets':
          if (value !== null && !Array.isArray(value)) {
            throw fail(422, line(locale, 'webx-catalog', 'errors.facet-shape'), {
              facets: [line(locale, 'webx-catalog', 'errors.facet-shape')],
            })
          }

          next.facets =
            value === null
              ? null
              : (value as FacetSetting[]).map((row) => ({
                  key: row.key,
                  visible: Boolean(row.visible),
                }))
          break
        case 'seo':
          next.seo = (value ?? {}) as Record<string, unknown>
          break
        default:
          throw fail(422, `The field ${name} is not on the screen.`, { [name]: ['Unknown field.'] })
      }
    }

    for (const [code, text] of Object.entries(next.name)) {
      if (!next.slug[code] && text) next.slug[code] = slugOf(text)
    }

    const errors: Record<string, string[]> = {}

    if (Object.values(next.name).every((text) => text.trim() === '')) {
      errors[`name.${locale}`] = [line(locale, 'webx-catalog', 'errors.name-required')]
    }

    for (const [code, slug] of Object.entries(next.slug)) {
      if (slug.includes('_')) {
        errors[`slug.${code}`] = [line(locale, 'webx-catalog', 'errors.slug-underscore')]
        continue
      }

      // Flat at the root of the site (decision 23): a slug taken by another category is refused
      // under the field, naming who holds it — `routing` does the same with a page.
      const holder = categories.find(
        (one) => one.id !== next.id && one.deleted_at === null && one.slug[code] === slug,
      )

      if (holder) {
        errors[`slug.${code}`] = [
          `The address /${slug}/ is taken by «${word(holder.name, locale)}».`,
        ]
      }
    }

    if (Object.keys(errors).length > 0) throw fail(422, Object.values(errors)[0]![0]!, errors)

    const created = !categories.includes(target)

    Object.assign(target, next, { updated_at: now() })

    if (created) {
      categories.push(target)

      return
    }

    const changes = diff(before, categoryValues(target), label('category', locale))
    const event =
      target.is_published && !before.is_published
        ? 'published'
        : !target.is_published && before.is_published
          ? 'unpublished'
          : 'updated'

    if (changes.length > 0) record('catalog.category', target.id, event, changes)
  }

  on('POST', '/catalog/categories', ({ body, locale }) => {
    const parent = typeof body.parent_id === 'number' ? findCategory(body.parent_id) : null
    const target: CategoryRecord = {
      id: nextId(categories),
      parent_id: parent?.id ?? null,
      position: Math.max(0, ...childrenOf(parent?.id ?? null).map((one) => one.position)) + 1,
      name: {},
      slug: {},
      description: {},
      cover: null,
      is_published: false,
      facets: null,
      seo: {},
      created_at: now(),
      updated_at: now(),
      deleted_at: null,
    }

    writeCategory(target, (body.values ?? {}) as Record<string, unknown>, locale)

    return { data: { category: categoryRow(target, locale), values: categoryValues(target) } }
  })

  on('PUT', '/catalog/categories/(\\d+)', ({ params, body, locale }) => {
    const target = findCategory(params[0]!)

    writeCategory(target, (body.values ?? {}) as Record<string, unknown>, locale)

    return { data: { category: categoryRow(target, locale), values: categoryValues(target) } }
  })

  /** `{ parent_id, before_id }` → the whole tree, as `CategoryController::move()` answers. */
  on('POST', '/catalog/categories/(\\d+)/move', ({ params, body, locale }) => {
    const target = findCategory(params[0]!)
    const parent = typeof body.parent_id === 'number' ? findCategory(body.parent_id) : null
    const before = typeof body.before_id === 'number' ? findCategory(body.before_id) : null

    if (parent && subtree(target.id).includes(parent.id)) {
      const message = line(locale, 'webx-catalog', 'errors.move-into-itself')

      throw fail(422, message, { parent_id: [message] })
    }

    if (before && before.parent_id !== (parent?.id ?? null)) {
      const message = line(locale, 'webx-catalog', 'errors.unknown-category')

      throw fail(422, message, { before_id: [message] })
    }

    const from = target.parent_id
    const siblings = childrenOf(parent?.id ?? null).filter((one) => one.id !== target.id)
    const index = before ? siblings.findIndex((one) => one.id === before.id) : siblings.length

    siblings.splice(index, 0, target)
    siblings.forEach((one, position) => (one.position = position + 1))
    target.parent_id = parent?.id ?? null

    if (from !== target.parent_id) {
      const name = (id: number | null) =>
        id === null ? null : word(categoryById(id, true)?.name ?? {}, locale)

      record('catalog.category', target.id, 'updated', [
        {
          field: 'parent',
          label: line(locale, 'webx-catalog', 'category.parent'),
          from: name(from),
          to: name(target.parent_id),
        },
      ])
    }

    return { data: treeOf(locale) }
  })

  on('DELETE', '/catalog/categories/(\\d+)', ({ params, locale }) => {
    const target = findCategory(params[0]!)
    const inside = live(products).filter(
      (one) => one.category_id === target.id || one.categories.includes(target.id),
    ).length
    const children = childrenOf(target.id).length

    if (inside > 0 || children > 0) {
      const key =
        children > 0 && inside === 0
          ? 'errors.category-has-children'
          : 'errors.category-has-products'
      const message = line(locale, 'webx-catalog', key).replace(
        ':count',
        String(inside > 0 ? inside : children),
      )

      throw fail(422, message, {}, { meta: { products: inside, children } })
    }

    target.deleted_at = now()
    record('catalog.category', target.id, 'deleted', [])

    return null
  })

  on('POST', '/catalog/categories/(\\d+)/restore', ({ params, locale }) => {
    const target = categories.find((one) => one.id === Number(params[0]) && one.deleted_at !== null)

    if (!target) throw fail(404, 'No such category.')

    for (const [code, slug] of Object.entries(target.slug)) {
      const holder = categories.find(
        (one) => one.id !== target.id && one.deleted_at === null && one.slug[code] === slug,
      )

      if (holder) {
        throw fail(422, `The address /${slug}/ is taken by «${word(holder.name, locale)}».`, {
          [`slug.${code}`]: ['Taken.'],
        })
      }
    }

    target.deleted_at = null
    record('catalog.category', target.id, 'restored', [])

    return { data: categoryRow(target, locale) }
  })

  on('GET', '/catalog/deleted', ({ query, locale }) => {
    const term = query.get('q') ?? ''
    const newest = <T extends { deleted_at: string | null; id: number }>(a: T, b: T) =>
      (b.deleted_at ?? '').localeCompare(a.deleted_at ?? '') || b.id - a.id

    if (query.get('type') === 'categories') {
      const rows = categories
        .filter((one) => one.deleted_at !== null && matches(term, one.name, one.slug))
        .sort(newest)
        .map((one) => {
          const parent = one.parent_id === null ? undefined : categoryById(one.parent_id, true)

          return {
            id: one.id,
            name: word(one.name, locale),
            slug: word(one.slug, locale),
            parent: parent
              ? {
                  id: parent.id,
                  name: word(parent.name, locale),
                  deleted: parent.deleted_at !== null,
                }
              : null,
            deleted_at: one.deleted_at,
          }
        })

      return paginate(rows, query)
    }

    const rows = products
      .filter((one) => one.deleted_at !== null && matches(term, one.name, one.sku))
      .sort(newest)
      .map((one) => {
        const main = one.category_id === null ? undefined : categoryById(one.category_id, true)

        return {
          id: one.id,
          name: word(one.name, locale),
          sku: one.sku,
          category: main
            ? { id: main.id, name: word(main.name, locale), deleted: main.deleted_at !== null }
            : null,
          deleted_at: one.deleted_at,
        }
      })

    return paginate(rows, query)
  })
}
