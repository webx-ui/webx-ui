/*
 * The catalogue's feeds (WEBX_UI_CATALOG_FEEDS.md §11) in memory: the list, the form with its
 * preview, the selection's count, the exclusions, the mappings and a journal whose builds end by a
 * timer, as the exchange's do. The products are the catalogue's own fixtures.
 *
 * The check of a save is the browser's renderer and parsers over the sample: enough to polish the
 * screens on. What a real server says about a template is `FeedTrial`, and the parity of the two
 * renderers is held by `tests/fixtures/preview.json`, not here.
 */
import {
  escapeForFormat,
  renderTemplate,
  TemplateRefusal,
} from '../../../../packages/core/src/components/CodeEditor/template'
import { catalogFacets, categoryRows, searchProducts } from './catalog'

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

type Format = 'xml' | 'json' | 'csv' | 'text'
type Values = Record<string, unknown>

interface BuildRecord {
  id: number
  status: 'queued' | 'running' | 'done' | 'failed'
  trigger: string
  queued_at: string
  started_at: string | null
  finished_at: string | null
  selected: number
  written: number
  filtered: number
  excluded: number
  rejected: Record<string, number>
  warnings: Record<string, number>
  error: string | null
  bytes: number | null
}

interface FeedRecord {
  id: number
  key: string
  key_issued_at: string
  values: Values
  builds: BuildRecord[]
  current: number | null
  excluded: Set<number>
  mapped: Map<number, { item: number | null; value: string | null }>
  reissues: { at: string; by: string }[]
  updated_at: string
}

const FORMATS: Format[] = ['xml', 'json', 'csv', 'text']
const EXTENSIONS: Record<Format, string> = { xml: 'xml', json: 'json', csv: 'csv', text: 'txt' }
const CURRENCY = 'EUR'
const SAMPLE = 5
const BUILD_MS = 2500

/* The shape of the server's presets, shortened: what the form shows, not what a marketplace takes. */
const PRESETS = [
  {
    key: 'google',
    name: 'Google Merchant',
    description: 'RSS 2.0 with the g: attributes.',
    values: {
      format: 'xml',
      head:
        '<?xml version="1.0" encoding="UTF-8"?>\n' +
        '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">\n<channel>\n' +
        '<title>{{shop.name}}</title>\n<link>{{shop.url}}</link>\n',
      item:
        '<item>\n  <g:id>{{id}}</g:id>\n  <g:title>{{name}}</g:title>\n' +
        '  <g:link>{{url}}</g:link>\n  <g:price>{{price_with_currency}}</g:price>\n' +
        '  <g:availability>{{availability}}</g:availability>\n</item>',
      foot: '\n</channel>\n</rss>\n',
      separator: '\n',
      taxonomy: 'google',
    },
  },
  {
    key: 'json',
    name: 'JSON',
    description: 'A flat list of products.',
    values: {
      format: 'json',
      head: '{"products":[',
      item: '{"id":"{{id}}","name":"{{name}}","price":"{{price}}"}',
      foot: ']}',
      separator: ',',
    },
  },
  {
    key: 'xml',
    name: 'XML',
    description: 'A flat list of products.',
    values: {
      format: 'xml',
      head: '<?xml version="1.0" encoding="UTF-8"?>\n<products currency="{{feed.currency}}">\n',
      item: '<product id="{{id}}"><name>{{name}}</name><price>{{price}}</price></product>',
      foot: '\n</products>\n',
      separator: '\n',
    },
  },
  {
    key: 'csv',
    name: 'CSV',
    description: 'One line per product.',
    values: {
      format: 'csv',
      head: 'id,name,price\n',
      item: '{{id}},{{name}},{{price}}',
      foot: '\n',
      separator: '\n',
      options: { delimiter: ',' },
    },
  },
]

const ITEM_VARIABLES = [
  ['id', 'ID'],
  ['sku', 'SKU'],
  ['name', 'Name'],
  ['url', 'Address'],
  ['price', 'Price'],
  ['price_with_currency', 'Price with currency'],
  ['availability', 'Availability'],
  ['platform_category', 'Category of the marketplace'],
  ['property.material', 'Material'],
  ['property.color', 'Colour'],
]

const FRAME_VARIABLES = [
  ['shop.name', 'Shop name'],
  ['shop.url', 'Shop address'],
  ['feed.name', 'Feed name'],
  ['feed.locale', 'Feed language'],
  ['feed.currency', 'Feed currency'],
  ['generated_at', 'Built at, ISO 8601'],
  ['products_count', 'Products in the file'],
]

/* A few lines of the real list, shaped like it: the panel only searches it. */
const TAXONOMY = [
  { id: 1, external_id: '1604', path: 'Apparel & Accessories > Clothing' },
  { id: 2, external_id: '5181', path: 'Apparel & Accessories > Clothing > Outerwear' },
  { id: 3, external_id: '888', path: 'Vehicles & Parts > Vehicle Parts & Accessories' },
  { id: 4, external_id: '632', path: 'Hardware' },
  { id: 5, external_id: '3237', path: 'Animals & Pet Supplies > Live Animals' },
]

const feeds: FeedRecord[] = []
let taxonomyImported: string | null = null
let nextFeed = 1
let nextBuild = 1

const variable = ([name, label]: string[]) => ({ name, label, example: '', fields: [] })

function address(feed: FeedRecord): string {
  const format = feed.values.format as Format

  return `https://shop.example.com/feeds/${feed.key}.${EXTENSIONS[format]}`
}

function newKey(): string {
  return Array.from({ length: 40 }, () => Math.floor(Math.random() * 36).toString(36)).join('')
}

function row(feed: FeedRecord) {
  const last = feed.builds[0] ?? null

  return {
    id: feed.id,
    name: feed.values.name,
    preset: feed.values.preset ?? null,
    format: feed.values.format,
    driver: null,
    locale: feed.values.locale,
    currency: CURRENCY,
    schedule: feed.values.schedule,
    is_active: feed.values.is_active,
    address: address(feed),
    last_build: last
      ? { status: last.status, written: last.written, finished_at: last.finished_at }
      : null,
    updated_at: feed.updated_at,
  }
}

function detail(feed: FeedRecord) {
  return { ...feed.values, ...row(feed), key_issued_at: feed.key_issued_at }
}

function blank(locale: string): Values {
  return {
    name: '',
    preset: null,
    format: 'xml',
    locale,
    head: '',
    item: '',
    foot: '',
    separator: '\n',
    filter: {},
    stock_map: {},
    taxonomy: null,
    custom_labels: {},
    sources: { mpn: { type: 'field' } },
    options: {},
    schedule: 'manual',
    schedule_at: null,
    rebuild_on_change: false,
    is_active: true,
  }
}

/** The filter as the products' list hears it. */
function questionOf(filter: Values): URLSearchParams {
  const query = new URLSearchParams()
  const categories = (filter.categories ?? {}) as { include?: number[] }
  const price = (filter.price ?? {}) as { min?: number | null; max?: number | null }

  for (const id of categories.include ?? []) query.append('facets[category][]', String(id))
  for (const id of (filter.brands ?? []) as string[]) query.append('facets[brand][]', String(id))
  if (price.min != null) query.set('facets[price][min]', String(price.min))
  if (price.max != null) query.set('facets[price][max]', String(price.max))

  return query
}

function selected(values: Values, locale: string, feed: FeedRecord | null) {
  const query = questionOf((values.filter ?? {}) as Values)

  query.set('per_page', '200')

  const found = searchProducts(query, locale)

  return {
    total: found.total,
    products: found.data.filter((product) => !feed?.excluded.has(product.id)),
  }
}

function valuesOf(product: { id: number; name: string; sku: string | null }, feed: Values) {
  const price = (10 + (product.id % 40) * 2.5).toFixed(2)

  return {
    id: String(product.id),
    sku: product.sku ?? '',
    name: product.name,
    url: `https://shop.example.com/product-${product.id}`,
    price,
    price_with_currency: `${price} ${CURRENCY}`,
    availability: 'in_stock',
    platform_category: feed.taxonomy === null ? '' : '888',
    'property.material': 'Steel',
    'property.color': 'Grey',
  }
}

function frameOf(values: Values, written: number) {
  return {
    'shop.name': 'Spare parts shop',
    'shop.url': 'https://shop.example.com',
    'feed.name': String(values.name ?? ''),
    'feed.locale': String(values.locale ?? ''),
    'feed.currency': CURRENCY,
    generated_at: new Date().toISOString(),
    products_count: String(written),
  }
}

function sampleOf(values: Values, locale: string, feed: FeedRecord | null, ids: number[]) {
  const { products } = selected(values, locale, feed)
  const chosen =
    ids.length > 0
      ? products.filter((product) => ids.includes(product.id))
      : products.slice(0, SAMPLE)

  return {
    products: chosen.map((product) => ({
      id: product.id,
      name: product.name,
      sku: product.sku,
      values: valuesOf(product, values),
    })),
    frame: frameOf(values, chosen.length),
    errors: [],
  }
}

function textOf(values: Values, sample: ReturnType<typeof sampleOf>): string {
  const options = (values.options ?? {}) as { delimiter?: string }
  const escape = escapeForFormat(values.format as Format, { delimiter: options.delimiter })
  const part = (template: unknown, data: Values) =>
    renderTemplate(String(template ?? ''), data, escape).text

  return (
    part(values.head, sample.frame) +
    sample.products
      .map((product) => part(values.item, product.values))
      .join(String(values.separator ?? '')) +
    part(values.foot, sample.frame)
  )
}

/** A mistake of a template under its part; the rest of §8.1 is the real server's. */
function refusal(values: Values): { field: string; message: string } | null {
  if (String(values.item ?? '').trim() === '') {
    return { field: 'item', message: 'The feed has no template of a product.' }
  }

  for (const field of ['head', 'item', 'foot']) {
    try {
      renderTemplate(String(values[field] ?? ''), {}, (value) => value)
    } catch (error) {
      if (error instanceof TemplateRefusal) {
        return { field, message: `The template cannot be read: ${error.message}` }
      }

      throw error
    }
  }

  return null
}

function build(feed: FeedRecord, locale: string, trigger: string): BuildRecord {
  const going = feed.builds.find((one) => one.status === 'queued' || one.status === 'running')

  if (going) return going

  const record: BuildRecord = {
    id: nextBuild++,
    status: 'queued',
    trigger,
    queued_at: new Date().toISOString(),
    started_at: null,
    finished_at: null,
    selected: 0,
    written: 0,
    filtered: 0,
    excluded: feed.excluded.size,
    rejected: {},
    warnings: {},
    error: null,
    bytes: null,
  }

  feed.builds.unshift(record)

  setTimeout(() => {
    record.status = 'running'
    record.started_at = new Date().toISOString()
  }, BUILD_MS / 3)

  setTimeout(() => {
    const { products } = selected(feed.values, locale, feed)
    const rejected = products.length > 3 ? 1 : 0
    const sample = sampleOf(feed.values, locale, feed, [])

    record.status = 'done'
    record.finished_at = new Date().toISOString()
    record.selected = products.length
    record.written = products.length - rejected
    record.rejected = rejected > 0 ? { no_image: rejected } : {}
    record.warnings = feed.values.taxonomy ? { unmapped_category: record.written } : {}
    record.bytes = textOf(feed.values, sample).length * Math.max(1, products.length / SAMPLE)
    record.bytes = Math.round(record.bytes)
    feed.current = record.id
  }, BUILD_MS)

  return record
}

function buildRow(feed: FeedRecord, record: BuildRecord) {
  return { ...record, current: record.id === feed.current }
}

export function registerFeeds(on: On, fail: Fail): void {
  const find = (id: string | undefined): FeedRecord => {
    const found = feeds.find((one) => one.id === Number(id))

    if (!found) throw fail(404, 'No such feed.')

    return found
  }

  /** What a form sent over what the feed holds. */
  const merged = (body: Values, feed: FeedRecord | null, locale: string): Values => {
    const values = { ...(feed?.values ?? blank(locale)) }

    for (const key of Object.keys(blank(locale))) {
      if (key in body) values[key] = body[key]
    }

    return values
  }

  const check = (values: Values): void => {
    if (String(values.name ?? '').trim() === '') {
      throw fail(422, 'The name is required.', { name: ['The name is required.'] })
    }

    if (!FORMATS.includes(values.format as Format)) {
      throw fail(422, 'The format is unknown.', { format: ['The format is unknown.'] })
    }

    const problem = refusal(values)

    if (problem) throw fail(422, problem.message, { [problem.field]: [problem.message] })
  }

  const context = (body: Values) => (typeof body.feed === 'number' ? find(String(body.feed)) : null)

  on('GET', '/catalog/feeds', () => ({
    data: feeds.map(row),
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: feeds.length,
    from: 1,
    to: feeds.length,
  }))

  on('GET', '/catalog/feeds/meta', () => ({
    data: {
      presets: PRESETS,
      formats: FORMATS,
      label_sources: ['none', 'label', 'price_range', 'property', 'constant'],
      currency: CURRENCY,
      stock: true,
      stock_statuses: [
        { code: 'in-stock', title: 'In stock', is_purchasable: true },
        { code: 'out-of-stock', title: 'Out of stock', is_purchasable: false },
        { code: 'on-order', title: 'On order', is_purchasable: true },
      ],
    },
  }))

  on('GET', '/catalog/feeds/variables', () => ({
    data: { item: ITEM_VARIABLES.map(variable), frame: FRAME_VARIABLES.map(variable) },
  }))

  on('GET', '/catalog/feeds/facets', ({ locale }) => ({
    data: catalogFacets(locale)
      .filter((facet) => facet.key !== 'category')
      .map((facet) => ({
        key: facet.key,
        label: facet.label,
        kind: facet.kind,
        typed: false,
        values: valuesOfFacet(facet.key, locale),
      })),
  }))

  on('POST', '/catalog/feeds/count', ({ body, locale }) => {
    const feed = context(body)
    const { total, products } = selected({ filter: body.filter }, locale, feed)

    return { data: { selected: products.length, excluded: total - products.length } }
  })

  on('POST', '/catalog/feeds/sample', ({ body, locale }) => {
    const feed = context(body)

    return {
      data: sampleOf(
        merged(body, feed, locale),
        locale,
        feed,
        (body.product_ids ?? []) as number[],
      ),
    }
  })

  on('POST', '/catalog/feeds/preview', ({ body, locale }) => {
    const feed = context(body)
    const values = merged(body, feed, locale)
    const problem = refusal(values)
    const sample = sampleOf(values, locale, feed, (body.product_ids ?? []) as number[])
    const { products } = selected(values, locale, feed)

    return {
      data: {
        ok: problem === null,
        text: problem === null ? textOf(values, sample) : '',
        errors: problem === null ? [] : [problem],
        warnings: [],
        selected: products.length,
        excluded: feed?.excluded.size ?? 0,
        looked_at: sample.products.length,
        written: sample.products.length,
        rejected: {},
        filtered: 0,
        products: [],
      },
    }
  })

  on('POST', '/catalog/feeds', ({ body, locale }) => {
    const values = merged(body, null, locale)

    check(values)

    const now = new Date().toISOString()
    const feed: FeedRecord = {
      id: nextFeed++,
      key: newKey(),
      key_issued_at: now,
      values,
      builds: [],
      current: null,
      excluded: new Set(),
      mapped: new Map(),
      reissues: [],
      updated_at: now,
    }

    feeds.push(feed)

    return { data: detail(feed), warnings: [] }
  })

  on('GET', '/catalog/feeds/(\\d+)', ({ params }) => ({ data: detail(find(params[0])) }))

  on('PUT', '/catalog/feeds/(\\d+)', ({ params, body, locale }) => {
    const feed = find(params[0])
    const values = merged(body, feed, locale)

    check(values)
    feed.values = values
    feed.updated_at = new Date().toISOString()

    return { data: detail(feed), warnings: [] }
  })

  on('DELETE', '/catalog/feeds/(\\d+)', ({ params }) => {
    feeds.splice(feeds.indexOf(find(params[0])), 1)

    return null
  })

  on('POST', '/catalog/feeds/(\\d+)/reissue-key', ({ params }) => {
    const feed = find(params[0])
    const now = new Date().toISOString()

    feed.key = newKey()
    feed.key_issued_at = now
    feed.reissues.unshift({ at: now, by: 'Administrator' })

    return { data: detail(feed) }
  })

  on('POST', '/catalog/feeds/(\\d+)/rebuild', ({ params, locale }) => {
    const feed = find(params[0])

    if (!feed.values.is_active) {
      throw fail(422, 'The feed is off: switch it on to build it.')
    }

    return { data: buildRow(feed, build(feed, locale, 'manual')) }
  })

  on('GET', '/catalog/feeds/(\\d+)/builds', ({ params }) => {
    const feed = find(params[0])

    return {
      data: feed.builds.map((record) => buildRow(feed, record)),
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: feed.builds.length,
      reissues: feed.reissues,
    }
  })

  on('GET', '/catalog/feeds/(\\d+)/builds/(\\d+)', ({ params, locale }) => {
    const feed = find(params[0])
    const record = feed.builds.find((one) => one.id === Number(params[1]))

    if (!record) throw fail(404, 'No such build.')

    const first = selected(feed.values, locale, feed).products[0]

    return {
      data: {
        ...buildRow(feed, record),
        rejects:
          (record.rejected.no_image ?? 0) > 0 && first
            ? [{ product_id: first.id, name: first.name, sku: first.sku, reason: 'no_image' }]
            : [],
      },
    }
  })

  on('GET', '/catalog/feeds/(\\d+)/exclusions', ({ params, query, locale }) => {
    const feed = find(params[0])
    const term = (query.get('q') ?? '').toLowerCase()
    const all = searchProducts(new URLSearchParams({ per_page: '200' }), locale).data
    const rows = all
      .filter((product) => feed.excluded.has(product.id))
      .filter((product) => term === '' || product.name.toLowerCase().includes(term))
      .map(({ id, name, sku }) => ({ id, name, sku }))

    return {
      data: rows,
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: rows.length,
      from: 1,
      to: rows.length,
    }
  })

  on('POST', '/catalog/feeds/(\\d+)/exclusions', ({ params, body }) => {
    const feed = find(params[0])

    for (const id of (body.product_ids ?? []) as number[]) feed.excluded.add(id)

    return null
  })

  on('DELETE', '/catalog/feeds/(\\d+)/exclusions/(\\d+)', ({ params }) => {
    find(params[0]).excluded.delete(Number(params[1]))

    return null
  })

  const taxonomyState = (locale: string) => ({
    key: 'google',
    locale,
    address: `https://www.google.com/basepages/producttype/taxonomy-with-ids.${locale}.txt`,
    list_locale: taxonomyImported === null ? null : 'en-US',
    fallback: taxonomyImported !== null && !locale.startsWith('en'),
    version: taxonomyImported === null ? null : '2021-09-21',
    imported_at: taxonomyImported,
    items: taxonomyImported === null ? 0 : TAXONOMY.length,
    status: taxonomyImported === null ? null : 'done',
    error: null,
  })

  const mappedTree = (feed: FeedRecord, locale: string) => {
    const rows = categoryRows(locale)
    const own = (id: number) => feed.mapped.get(id) ?? null
    const shown = (held: { item: number | null; value: string | null }) => {
      const item = TAXONOMY.find((one) => one.id === held.item)

      return { value: item?.external_id ?? held.value, label: item?.path ?? held.value }
    }

    const data = rows.map((category) => {
      const held = own(category.id)
      let parent = category.parent_id
      let inherited = null

      while (held === null && parent !== null && inherited === null) {
        const found = own(parent)

        if (found !== null) inherited = { category_id: parent, ...shown(found) }

        parent = rows.find((one) => one.id === parent)?.parent_id ?? null
      }

      return {
        ...category,
        taxonomy_item_id: held?.item ?? null,
        ...(held ? shown(held) : { value: null, label: null }),
        inherited,
      }
    })

    return {
      data,
      unmapped: data.filter((one) => one.value === null && one.inherited === null).length,
      taxonomy: feed.values.taxonomy === null ? null : taxonomyState(String(feed.values.locale)),
    }
  }

  on('GET', '/catalog/feeds/(\\d+)/categories', ({ params, locale }) =>
    mappedTree(find(params[0]), locale),
  )

  on('PUT', '/catalog/feeds/(\\d+)/categories', ({ params, body, locale }) => {
    const feed = find(params[0])
    const rows = (body.categories ?? []) as {
      category_id: number
      taxonomy_item_id?: number | null
      value?: string | null
    }[]

    for (const one of rows) {
      if ((one.taxonomy_item_id ?? null) === null && (one.value ?? null) === null) {
        feed.mapped.delete(one.category_id)
      } else {
        feed.mapped.set(one.category_id, {
          item: one.taxonomy_item_id ?? null,
          value: one.value ?? null,
        })
      }
    }

    return mappedTree(feed, locale)
  })

  on('GET', '/catalog/feeds/taxonomies/(\\w+)/search', ({ query }) => {
    const locale = query.get('locale') ?? 'en'
    const term = (query.get('q') ?? '').toLowerCase()

    return {
      data:
        term === '' || taxonomyImported === null
          ? []
          : TAXONOMY.filter((one) => one.path.toLowerCase().includes(term)),
      taxonomy: taxonomyState(locale),
    }
  })

  on('POST', '/catalog/feeds/taxonomies/(\\w+)/import', ({ body }) => {
    taxonomyImported = new Date().toISOString()

    return { taxonomy: taxonomyState(String(body.locale ?? 'en')) }
  })
}

/** What the products' list counted for a facet: the values a selection can name. */
function valuesOfFacet(key: string, locale: string): { value: string; label: string }[] {
  const counted = searchProducts(new URLSearchParams({ per_page: '1' }), locale).facets[key] as
    { values?: { value: string | number; label: string }[] } | undefined

  return (counted?.values ?? []).map((one) => ({ value: String(one.value), label: one.label }))
}
