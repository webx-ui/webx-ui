/*
 * The catalogue's collections (WEBX_UI_CATALOG_COLLECTIONS.md §6.5) in memory: the list with its
 * filters, the form, the facets of a base and the preview. The products are the catalogue's own
 * fixtures: a filter's preview is the products' list asked with the set, so the two never disagree.
 */
import type { HistoryPage } from '../../../../packages/module-admin/src/history'
import { catalogFacets, productWords, searchProducts } from './catalog'

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

type Choice = { values: string[] } | { min: number | null; max: number | null }
type Filters = Record<string, Choice>

interface CollectionRecord {
  id: number
  key: string
  title: Map
  source: 'manual' | 'filter'
  category_id: number | null
  filters: Filters
  on_sale: boolean
  sort: string | null
  only_purchasable: boolean
  limit: number
  view: 'grid' | 'carousel'
  link: Record<string, unknown> | null
  link_text: Map
  is_enabled: boolean
  attention: 'value-deleted' | null
  products: number[]
  updated_at: string
  deleted_at: string | null
}

const NS = 'webx-catalog-collections'
const MAX = 48

const PRESETS = [
  { key: 'new', sort: 'new', on_sale: false, facet: null },
  { key: 'on-sale', sort: null, on_sale: true, facet: null },
  { key: 'label', sort: null, on_sale: false, facet: 'label' },
]

let lineOf: Line = (_locale, _namespace, path) => path

const now = () => new Date().toISOString()
const word = (map: Map | null | undefined, locale: string) =>
  map?.[locale] || Object.values(map ?? {}).find(Boolean) || ''

function collection(id: number, extra: Partial<CollectionRecord>): CollectionRecord {
  return {
    id,
    key: `collection-${id}`,
    title: {},
    source: 'manual',
    category_id: null,
    filters: {},
    on_sale: false,
    sort: null,
    only_purchasable: true,
    limit: 8,
    view: 'grid',
    link: null,
    link_text: {},
    is_enabled: true,
    attention: null,
    products: [],
    updated_at: now(),
    deleted_at: null,
    ...extra,
  }
}

/* A preset, a hand-picked list, one turned off, one marked, one in the bin: the states of §6.1. */
const collections: CollectionRecord[] = [
  collection(1, {
    key: 'new-arrivals',
    title: { ru: 'Новинки', en: 'New arrivals' },
    source: 'filter',
    sort: 'new',
  }),
  collection(2, {
    key: 'for-beginners',
    title: { ru: 'Для новичка', en: 'For beginners' },
    products: [1, 2, 3, 4],
    limit: 3,
    view: 'carousel',
  }),
  collection(3, {
    key: 'on-sale',
    title: { ru: 'Со скидкой', en: 'On sale' },
    source: 'filter',
    on_sale: true,
    is_enabled: false,
  }),
  collection(4, {
    key: 'by-brand',
    title: { ru: 'Лучшее от бренда', en: 'Best of the brand' },
    source: 'filter',
    attention: 'value-deleted',
  }),
  collection(5, {
    key: 'old-winter',
    title: { ru: 'Прошлая зима', en: 'Last winter' },
    deleted_at: now(),
  }),
]

function presetOf(record: CollectionRecord): string | null {
  if (record.source !== 'filter') return null

  const facets = Object.keys(record.filters).join()
  const found = PRESETS.find(
    (one) =>
      one.sort === record.sort && one.on_sale === record.on_sale && facets === (one.facet ?? ''),
  )

  return found?.key ?? null
}

/** `CollectionUsage`: the blocks that place a collection, by key. */
const PLACES: Record<string, Array<Record<string, unknown>>> = {
  'new-arrivals': [
    place(1, 'Home', { url: '/' }),
    place(2, 'About the shop', { live: false, draft: true, url: '/about' }),
    place(3, 'Region: Footer', { model: 'WebxUi\\Blocks\\Models\\Region', region: 'footer' }),
  ],
  'for-beginners': [place(4, 'Spring sale', { published: false })],
}

function place(id: number, title: string, rest: Record<string, unknown> = {}) {
  return {
    model: 'App\\Models\\Page',
    id,
    title,
    published: true,
    live: true,
    draft: false,
    url: null,
    region: null,
    ...rest,
  }
}

function row(record: CollectionRecord) {
  return {
    id: record.id,
    key: record.key,
    title: record.title,
    source: record.source,
    preset: presetOf(record),
    view: record.view,
    limit: record.limit,
    products_count: record.products.length,
    places: PLACES[record.key]?.length ?? 0,
    is_enabled: record.is_enabled,
    attention: record.attention,
    deleted_at: record.deleted_at,
    updated_at: record.updated_at,
  }
}

function detail(record: CollectionRecord, locale: string) {
  return {
    ...row(record),
    category_id: record.category_id,
    filters: record.filters,
    filter_labels: {},
    on_sale: record.on_sale,
    sort: record.sort,
    only_purchasable: record.only_purchasable,
    link: record.link,
    link_text: record.link_text,
    products: record.products,
    product_items: record.products.flatMap((id) => productWords(id, locale) ?? []),
  }
}

function question(categoryId: number | null, filters: Filters, perPage: number): URLSearchParams {
  const query = new URLSearchParams({ per_page: String(perPage) })

  if (categoryId !== null) query.append('facets[category][]', String(categoryId))

  for (const [key, choice] of Object.entries(filters)) {
    if ('values' in choice) {
      for (const value of choice.values) query.append(`facets[${key}][]`, value)
    } else {
      if (choice.min !== null) query.set(`facets[${key}][min]`, String(choice.min))
      if (choice.max !== null) query.set(`facets[${key}][max]`, String(choice.max))
    }
  }

  return query
}

type Counted = {
  values?: { value: string; label: string; count: number }[]
  min?: number | null
  max?: number | null
}

function facetsOf(categoryId: number | null, locale: string) {
  const answer = searchProducts(question(categoryId, {}, 1), locale)

  return catalogFacets(locale)
    .filter((facet) => facet.key !== 'category' && facet.kind !== 'toggle')
    .map((facet) => {
      const counted = (answer.facets[facet.key] ?? {}) as Counted

      return {
        key: facet.key,
        label: facet.label,
        kind: facet.kind,
        min: facet.kind === 'range' ? (counted.min ?? null) : null,
        max: facet.kind === 'range' ? (counted.max ?? null) : null,
        values: facet.kind === 'range' ? [] : (counted.values ?? []).filter((one) => one.count > 0),
      }
    })
}

type Listed = { id: number; name: string; sku: string | null; price?: number | null }

/* The second of a hand-picked list is always out, so the form has a reason to show. */
function preview(body: Record<string, unknown>, locale: string) {
  const limit = Math.min(Math.max(Number(body.limit) || 1, 1), MAX)

  if (body.source === 'manual') {
    const picked = ((body.products ?? []) as number[]).map(Number)
    const fit = picked.filter((_, at) => at !== 1)
    const named = (id: number) => {
      const found = productWords(id, locale)

      return { id, name: found?.name ?? `#${id}`, sku: found?.sku ?? null }
    }

    return {
      products: fit.slice(0, limit).map((id) => ({ ...named(id), image: null, price: 1990 })),
      count: fit.length,
      more: false,
      limit,
      dropped: [
        ...picked
          .filter((_, at) => at === 1)
          .map((id) => ({ ...named(id), reason: 'status', status: 'Out of stock' })),
        ...fit.slice(limit).map((id) => ({ ...named(id), reason: 'over-limit', status: null })),
      ],
    }
  }

  const categoryId =
    body.category_id === null || body.category_id === undefined ? null : Number(body.category_id)
  const answer = searchProducts(
    question(categoryId, (body.filters ?? {}) as Filters, MAX + 1),
    locale,
  )
  const rows = answer.data as Listed[]

  return {
    products: rows.slice(0, limit).map((one) => ({
      id: one.id,
      name: one.name,
      sku: one.sku,
      image: null,
      price: one.price ?? null,
      old_price: body.on_sale ? Math.round((one.price ?? 0) * 1.2) : null,
    })),
    count: Math.min(answer.total, MAX),
    more: answer.total > MAX,
    limit,
    dropped: [],
  }
}

function slugOf(title: Map): string {
  const text = Object.values(title).find(Boolean) ?? ''
  const slug = text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')

  return slug || 'collection'
}

function write(
  record: CollectionRecord,
  body: Record<string, unknown>,
  locale: string,
  fail: Fail,
): void {
  const errors: Record<string, string[]> = {}
  const next: CollectionRecord = { ...record, ...(body as Partial<CollectionRecord>) }
  const refuse = (field: string, path: string, swap: Record<string, string> = {}) => {
    errors[field] = [
      Object.entries(swap).reduce(
        (text, [key, value]) => text.replace(`:${key}`, value),
        lineOf(locale, NS, path),
      ),
    ]
  }

  if (!Object.values(next.title ?? {}).some(Boolean)) refuse('title', 'errors.title-required')

  if (!Number.isInteger(next.limit) || next.limit < 1 || next.limit > MAX) {
    refuse('limit', 'errors.limit-range', { max: String(MAX) })
  }

  if (next.products.length > MAX) {
    refuse('products', 'errors.products-too-many', { max: String(MAX) })
  }

  if (record.id !== 0 && next.key !== record.key) refuse('key', 'errors.key-fixed')

  if (record.id === 0) {
    next.key = next.key || slugOf(next.title)

    if (!/^[a-z0-9-]{1,64}$/.test(next.key)) {
      refuse('key', 'errors.key-shape', { max: '64' })
    } else if (collections.some((one) => one.key === next.key)) {
      refuse('key', 'errors.key-taken', { key: next.key })
    }
  }

  if (Object.keys(errors).length > 0) throw fail(422, Object.values(errors)[0]![0]!, errors)

  Object.assign(record, next, { attention: null, updated_at: now() })
}

export function collectionHistory(type: string): HistoryPage | null {
  return type === 'catalog.collection'
    ? { data: [], current_page: 1, last_page: 1, total: 0 }
    : null
}

/** `CollectionTarget`: what `wx-catalog-collection` picks, kept by `key`. */
export function collectionCandidates(locale: string) {
  return collections.map((record) => ({
    candidate: {
      id: record.id,
      title: word(record.title, locale) || record.key,
      subtitle: record.key,
      key: record.key,
      thumb: null,
      visible: record.is_enabled && record.deleted_at === null,
    },
    deleted: record.deleted_at !== null,
  }))
}

export function registerCollections(on: On, fail: Fail, line: Line): void {
  lineOf = line

  const find = (id: string | number, trashed = false) => {
    const found = collections.find(
      (one) => one.id === Number(id) && (trashed || one.deleted_at === null),
    )

    if (!found) throw fail(404, 'No such collection.')

    return found
  }

  on('GET', '/catalog/collections', ({ query }) => {
    const term = (query.get('q') ?? '').trim().toLowerCase()
    const flag = (key: string) => (query.has(key) ? query.get(key) === '1' : null)

    let found = collections.filter((one) =>
      flag('trashed') ? one.deleted_at !== null : one.deleted_at === null,
    )

    if (term) {
      found = found.filter((one) =>
        [one.key, ...Object.values(one.title)].some((text) => text.toLowerCase().includes(term)),
      )
    }

    const attention = flag('attention')
    if (attention !== null) found = found.filter((one) => (one.attention !== null) === attention)

    const enabled = flag('enabled')
    if (enabled !== null) found = found.filter((one) => one.is_enabled === enabled)

    const perPage = Math.min(200, Math.max(1, Number(query.get('per_page') ?? 50) || 50))
    const page = Math.max(1, Number(query.get('page') ?? 1) || 1)
    const from = found.length === 0 ? null : (page - 1) * perPage + 1

    return {
      data: found.slice((page - 1) * perPage, page * perPage).map(row),
      current_page: page,
      last_page: Math.max(1, Math.ceil(found.length / perPage)),
      per_page: perPage,
      total: found.length,
      from,
      to: from === null ? null : Math.min(found.length, page * perPage),
    }
  })

  on('GET', '/catalog/collections/facets', ({ query, locale }) => {
    const category = query.get('category')

    return { data: facetsOf(category ? Number(category) : null, locale), presets: PRESETS }
  })

  on('POST', '/catalog/collections/preview', ({ body, locale }) => ({
    data: preview(body, locale),
  }))

  on('GET', '/catalog/collections/(\\d+)', ({ params, locale }) => ({
    data: detail(find(params[0]!, true), locale),
  }))

  on('GET', '/catalog/collections/(\\d+)/usage', ({ params }) => ({
    data: PLACES[find(params[0]!, true).key] ?? [],
  }))

  on('POST', '/catalog/collections', ({ body, locale }) => {
    const record = collection(0, { key: '' })

    write(record, body, locale, fail)
    record.id = Math.max(0, ...collections.map((one) => one.id)) + 1
    collections.push(record)

    return { data: detail(record, locale) }
  })

  on('PUT', '/catalog/collections/(\\d+)', ({ params, body, locale }) => {
    const record = find(params[0]!)

    write(record, body, locale, fail)

    return { data: detail(record, locale) }
  })

  on('DELETE', '/catalog/collections/(\\d+)', ({ params }) => {
    find(params[0]!).deleted_at = now()

    return null
  })

  on('POST', '/catalog/collections/(\\d+)/restore', ({ params, locale }) => {
    const record = find(params[0]!, true)

    record.deleted_at = null

    return { data: detail(record, locale) }
  })
}
