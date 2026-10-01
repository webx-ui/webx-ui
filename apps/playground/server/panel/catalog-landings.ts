/*
 * The catalogue's landings (WEBX_UI_CATALOG_LANDINGS.md §8.4) in memory: the list with its
 * filters, a landing's form, the live count, the facets of a base and «Create in bulk». The
 * products are the catalogue's own fixtures — a count is the products' list asked with the set as
 * its filter, so the two never disagree.
 */
import type { HistoryPage } from '../../../../packages/module-admin/src/history'
import type { CategoryLookup } from './catalog-properties'
import { catalogFacets, categoryWords, productWords, searchProducts } from './catalog'

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

interface LandingRecord {
  id: number
  category_id: number | null
  filters: Filters
  name: Map
  slug: Map
  h1: Map
  text_above: Map
  text_below: Map
  sort: string | null
  on_category: boolean
  position: number
  is_published: boolean
  attention: 'value_removed' | 'duplicate' | 'empty_set' | null
  recommended: number[]
  seo: Record<string, unknown>
  products_count: number | null
  updated_at: string
  deleted_at: string | null
}

interface RunRecord {
  id: number
  rows: Record<string, unknown>[]
  total: number
  done: number
  skipped: number
  failed: number
  errors: { slug: string; name: string; message: string }[]
  status: 'queued' | 'running' | 'done' | 'failed'
  created_at: string
  finished_at: string | null
}

const LOCALES = ['ru', 'en']
const SITE = 'https://shop.webx-demo.test'
const NS = 'webx-catalog-landings'

let lineOf: Line = (_locale, _namespace, path) => path
let categoryOf: CategoryLookup = () => undefined

const now = () => new Date().toISOString()
const word = (map: Map | null | undefined, locale: string) =>
  map?.[locale] || Object.values(map ?? {}).find(Boolean) || ''

function landing(id: number, extra: Partial<LandingRecord>): LandingRecord {
  return {
    id,
    category_id: null,
    filters: {},
    name: {},
    slug: {},
    h1: {},
    text_above: {},
    text_below: {},
    sort: null,
    on_category: true,
    position: id,
    is_published: true,
    attention: null,
    recommended: [],
    seo: {},
    products_count: null,
    updated_at: now(),
    deleted_at: null,
    ...extra,
  }
}

/* Laptops of two brands, a price range on smartphones, one over the whole catalogue, an empty
   one, one that lost a value — the states §8.1's list has to show. */
const landings: LandingRecord[] = [
  landing(1, {
    category_id: 2,
    filters: { brand: { values: ['4'] } },
    name: { ru: 'Ноутбуки Tailspin', en: 'Tailspin laptops' },
    slug: { ru: 'noutbuki-tailspin', en: 'laptops-tailspin' },
    text_above: { ru: '<p>Лёгкие ноутбуки Tailspin для работы и учёбы.</p>' },
    recommended: [2, 3],
    seo: { title: { ru: 'Ноутбуки Tailspin — купить' } },
  }),
  landing(2, {
    category_id: 2,
    filters: { brand: { values: ['2', '3'] } },
    name: { ru: 'Ноутбуки Contoso и Fabrikam', en: 'Contoso and Fabrikam laptops' },
    slug: { ru: 'noutbuki-contoso-fabrikam', en: 'laptops-contoso-fabrikam' },
  }),
  landing(3, {
    category_id: 3,
    filters: { price: { min: null, max: 30000 } },
    name: { ru: 'Смартфоны до 30 000', en: 'Smartphones under 30,000' },
    slug: { ru: 'smartfony-do-30000', en: 'smartphones-under-30000' },
    sort: 'price_asc',
  }),
  landing(4, {
    filters: { brand: { values: ['6'] } },
    name: { ru: 'Всё от Adatum', en: 'Everything by Adatum' },
    slug: { ru: 'adatum', en: 'adatum' },
    on_category: false,
  }),
  landing(5, {
    category_id: 7,
    filters: { brand: { values: ['8'] }, price: { min: 900000, max: null } },
    name: { ru: 'Дорогие чайники Woodgrove', en: 'Expensive Woodgrove kettles' },
    slug: { ru: 'chayniki-woodgrove-dorogie', en: 'kettles-woodgrove-expensive' },
    is_published: false,
  }),
  landing(6, {
    category_id: 13,
    filters: { brand: { values: ['3'] } },
    name: { ru: 'Наушники Fabrikam', en: 'Fabrikam headphones' },
    slug: { ru: 'naushniki-fabrikam', en: 'headphones-fabrikam' },
    attention: 'value_removed',
    is_published: false,
  }),
]

const runs: RunRecord[] = []

/* ----------------------------------------------------------------- the counting --- */

function question(categoryId: number | null, filters: Filters, perPage = 1): URLSearchParams {
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

function count(categoryId: number | null, filters: Filters, locale: string): number {
  return searchProducts(question(categoryId, filters), locale).total
}

type Counted = {
  values?: { value: string; label: string; count: number }[]
  min?: number | null
  max?: number | null
}

function facetsOf(categoryId: number | null, locale: string) {
  const answer = searchProducts(question(categoryId, {}), locale)

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

/** The words of every value of every facet, over the whole catalogue: the chips' text. */
function labels(locale: string): Map {
  const words: Map = {}

  for (const facet of facetsOf(null, locale)) {
    for (const value of facet.values) words[`${facet.key}:${value.value}`] = value.label
  }

  return words
}

const slugOf = (text: string) =>
  text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')

function suggest(categoryId: number | null, filters: Filters, words: Map): Map {
  const suggested: Map = {}

  for (const locale of LOCALES) {
    const pieces = Object.entries(filters)
      .filter(([, choice]) => 'values' in choice)
      .flatMap(([key, choice]) =>
        (choice as { values: string[] }).values.map((value) =>
          slugOf(words[`${key}:${value}`] ?? value),
        ),
      )

    if (pieces.length === 0) continue

    const base = categoryId === null ? '' : (categoryWords(categoryId, locale)?.slug ?? '')
    suggested[locale] = [base, ...pieces].filter(Boolean).join('-')
  }

  return suggested
}

/* ------------------------------------------------------------------- the shapes --- */

function setKey(categoryId: number | null, filters: Filters): string {
  const sorted = Object.keys(filters)
    .sort()
    .map((key) => {
      const choice = filters[key]!

      return [key, 'values' in choice ? [...choice.values].sort() : [choice.min, choice.max]]
    })

  return `${categoryId ?? 'root'}|${JSON.stringify(sorted)}`
}

function clean(raw: unknown): Filters {
  const filters: Filters = {}

  for (const [key, choice] of Object.entries(
    (raw ?? {}) as Record<string, Record<string, unknown>>,
  )) {
    if (Array.isArray(choice?.values)) {
      const values = choice.values.map(String).filter(Boolean)
      if (values.length > 0) filters[key] = { values }
    } else if (choice) {
      const min =
        choice.min === null || choice.min === undefined || choice.min === ''
          ? null
          : Number(choice.min)
      const max =
        choice.max === null || choice.max === undefined || choice.max === ''
          ? null
          : Number(choice.max)
      if (min !== null || max !== null) filters[key] = { min, max }
    }
  }

  return filters
}

function chips(record: LandingRecord, locale: string, words: Map) {
  const known = new globalThis.Map(catalogFacets(locale).map((facet) => [facet.key, facet.label]))

  return Object.entries(record.filters).map(([key, choice]) => ({
    key,
    label: known.get(key) ?? key,
    text:
      'values' in choice
        ? choice.values.map((value) => words[`${key}:${value}`] ?? `#${value}`).join(', ')
        : `${choice.min ?? ''} – ${choice.max ?? ''}`.trim(),
  }))
}

function row(record: LandingRecord, locale: string, words: Map) {
  if (record.products_count === null)
    record.products_count = count(record.category_id, record.filters, locale)

  const slug = word(record.slug, locale)

  return {
    id: record.id,
    category_id: record.category_id,
    category:
      record.category_id === null
        ? null
        : (categoryWords(record.category_id, locale)?.name ?? null),
    name: { ...record.name },
    slug: { ...record.slug },
    url: slug ? `${SITE}/${locale === 'ru' ? '' : `${locale}/`}${slug}/` : null,
    filters: record.filters,
    chips: chips(record, locale, words),
    products_count: record.products_count,
    counted_at: now(),
    is_published: record.is_published,
    on_category: record.on_category,
    position: record.position,
    attention: record.attention,
    deleted_at: record.deleted_at,
    updated_at: record.updated_at,
  }
}

function detail(record: LandingRecord, locale: string) {
  return {
    ...row(record, locale, labels(locale)),
    h1: { ...record.h1 },
    text_above: { ...record.text_above },
    text_below: { ...record.text_below },
    sort: record.sort,
    recommended: [...record.recommended],
    recommended_items: record.recommended
      .map((id) => productWords(id, locale))
      .filter((one) => one !== undefined),
    seo: record.seo,
  }
}

function inside(categoryId: number | null, ancestor: number): boolean {
  let current = categoryId

  while (current !== null) {
    if (current === ancestor) return true
    current = categoryOf(current)?.parent_id ?? null
  }

  return false
}

function paginate<T>(rows: T[], query: URLSearchParams) {
  const perPage = Math.min(200, Math.max(1, Number(query.get('per_page') ?? 50) || 50))
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

/* ----------------------------------------------------------------- the writing --- */

function slugHolder(slug: string, locale: string, except: number | null): string | null {
  for (const one of landings) {
    if (one.id !== except && one.slug[locale] === slug) return word(one.name, locale)
  }

  for (let id = 1; id <= 100; id++) {
    const category = categoryWords(id, locale)
    if (category && category.slug === slug) return category.name
  }

  return null
}

function setHolder(
  categoryId: number | null,
  filters: Filters,
  except: number | null,
): LandingRecord | undefined {
  const key = setKey(categoryId, filters)

  return landings.find(
    (one) =>
      one.id !== except &&
      one.attention !== 'duplicate' &&
      setKey(one.category_id, one.filters) === key,
  )
}

function write(
  record: LandingRecord,
  body: Record<string, unknown>,
  locale: string,
  fail: Fail,
): void {
  const next: LandingRecord = { ...record }
  const errors: Record<string, string[]> = {}
  const t = (path: string, swap: Record<string, string> = {}) =>
    Object.entries(swap).reduce(
      (text, [key, value]) => text.replace(`:${key}`, value),
      lineOf(locale, NS, path),
    )

  for (const field of ['name', 'slug', 'h1', 'text_above', 'text_below'] as const) {
    if (field in body) next[field] = { ...((body[field] ?? {}) as Map) }
  }

  if ('category_id' in body)
    next.category_id = body.category_id === null ? null : Number(body.category_id)
  if ('filters' in body) next.filters = clean(body.filters)
  if ('sort' in body) next.sort = (body.sort as string | null) || null
  if ('on_category' in body) next.on_category = Boolean(body.on_category)
  if ('position' in body) next.position = Number(body.position ?? 0)
  if ('is_published' in body) next.is_published = Boolean(body.is_published)
  if ('recommended' in body) next.recommended = ((body.recommended ?? []) as unknown[]).map(Number)
  if ('seo' in body) next.seo = (body.seo ?? {}) as Record<string, unknown>

  if (!Object.values(next.name).some(Boolean))
    errors.name = [lineOf(locale, 'webx-admin', 'validation.required') || 'Required.']

  if (!Object.values(next.slug).some(Boolean)) {
    errors.slug = [lineOf(locale, 'webx-admin', 'validation.required') || 'Required.']
  } else {
    for (const [language, slug] of Object.entries(next.slug)) {
      if (slug.includes('_')) errors.slug = [t('errors.slug-underscore')]
      const holder = slug ? slugHolder(slug, language, record.id || null) : null
      if (holder) errors.slug = [`/${slug}/ — ${holder}`]
    }
  }

  if (Object.keys(next.filters).length === 0) {
    errors.filters = [t('errors.set-empty')]
  } else {
    const holder = setHolder(next.category_id, next.filters, record.id || null)
    if (holder) errors.filters = [t('errors.set-taken', { name: word(holder.name, locale) })]
  }

  if (Object.keys(errors).length > 0) {
    throw fail(422, Object.values(errors)[0]![0]!, errors)
  }

  Object.assign(record, next, { attention: null, products_count: null, updated_at: now() })
}

/* --------------------------------------------------------------- the generation --- */

function plan(body: Record<string, unknown>, locale: string, fail: Fail) {
  const facetKey = String(body.facet ?? '')
  const facet = catalogFacets(locale).find((one) => one.key === facetKey)

  if (!facet || facet.key === 'category' || facet.kind === 'range') {
    throw fail(422, lineOf(locale, NS, 'errors.generate-facet').replace(':key', facetKey), {
      facet: [lineOf(locale, NS, 'errors.generate-facet').replace(':key', facetKey)],
    })
  }

  const slugTemplate = String(body.slug || '{category}-{value}')

  if (!slugTemplate.includes('{value}')) {
    throw fail(422, lineOf(locale, NS, 'errors.generate-template'), {
      slug: [lineOf(locale, NS, 'errors.generate-template')],
    })
  }

  let ids = ((body.categories ?? []) as unknown[]).map(Number)

  if (body.subtree) {
    const below: number[] = []
    for (let id = 1; id <= 100; id++) {
      if (categoryOf(id) && ids.some((root) => inside(id, root))) below.push(id)
    }
    ids = [...new Set([...ids, ...below])]
  }

  const chosen =
    Array.isArray(body.values) && body.values.length > 0 ? body.values.map(String) : null
  const least = Math.max(1, Number(body.min_products ?? 1) || 1)
  const claimed = new Set<string>()
  const rows: Record<string, unknown>[] = []
  const fill = (template: string, category: string, value: string) =>
    template.replace('{category}', category).replace('{value}', value).trim()

  for (const categoryId of ids) {
    if (!categoryOf(categoryId) || categoryOf(categoryId)?.deleted_at) continue

    const counted = facetsOf(categoryId, locale).find((one) => one.key === facetKey)?.values ?? []
    const values = chosen ?? counted.filter((one) => one.count >= least).map((one) => one.value)

    for (const value of values) {
      const found = counted.find((one) => one.value === value)
      const label = found?.label ?? `#${value}`
      const filters: Filters = { [facetKey]: { values: [value] } }
      const slug: Map = {}
      const name: Map = {}
      const h1: Map = {}

      for (const language of LOCALES) {
        const category = categoryWords(categoryId, language)!
        slug[language] = fill(slugTemplate, category.slug, slugOf(label)).replace(/^-|-$/g, '')
        name[language] = fill(String(body.name || '{category} {value}'), category.name, label)
        if (body.h1) h1[language] = fill(String(body.h1), category.name, label)
      }

      const holder = setHolder(categoryId, filters, null)
      const taken = Object.entries(slug).find(
        ([language, one]) => claimed.has(`${language}|${one}`) || slugHolder(one, language, null),
      )
      const productCount = found?.count ?? 0
      let conflict: string | null = null
      let message: string | null = null

      if (holder) {
        conflict = 'set-taken'
        message = lineOf(locale, NS, 'generate.conflict-set-taken').replace(
          ':name',
          word(holder.name, locale),
        )
      } else if (taken) {
        conflict = 'slug-taken'
        message = lineOf(locale, NS, 'generate.conflict-slug-taken').replace(':slug', taken[1])
      } else if (productCount === 0) {
        conflict = 'empty'
        message = lineOf(locale, NS, 'generate.conflict-empty')
      } else {
        for (const [language, one] of Object.entries(slug)) claimed.add(`${language}|${one}`)
      }

      rows.push({
        category_id: categoryId,
        category: categoryWords(categoryId, locale)?.name ?? '',
        value,
        label,
        filters,
        slug,
        name,
        h1,
        count: productCount,
        conflict,
        message,
      })
    }
  }

  return rows
}

function make(row: Record<string, unknown>, publish: boolean): void {
  landings.push(
    landing(Math.max(0, ...landings.map((one) => one.id)) + 1, {
      category_id: row.category_id as number,
      filters: row.filters as Filters,
      name: row.name as Map,
      slug: row.slug as Map,
      h1: row.h1 as Map,
      on_category: false,
      is_published: publish,
    }),
  )
}

function runShape(run: RunRecord) {
  const shape: Partial<RunRecord> = { ...run }

  delete shape.rows

  return shape
}

/* ------------------------------------------------------------------- the routes --- */

export function landingHistory(type: string): HistoryPage | null {
  return type === 'catalog.landing' ? { data: [], current_page: 1, last_page: 1, total: 0 } : null
}

export function registerLandings(on: On, fail: Fail, line: Line, categories: CategoryLookup): void {
  lineOf = line
  categoryOf = categories

  const find = (id: string | number, trashed = false) => {
    const found = landings.find(
      (one) => one.id === Number(id) && (trashed || one.deleted_at === null),
    )

    if (!found) throw fail(404, 'No such landing.')

    return found
  }

  on('GET', '/catalog/landings', ({ query, locale }) => {
    const term = (query.get('q') ?? '').trim().toLowerCase()
    const category = query.get('category')
    const flag = (key: string) => (query.has(key) ? query.get(key) === '1' : null)
    const words = labels(locale)

    let found = landings.filter((one) =>
      flag('trashed') ? one.deleted_at !== null : one.deleted_at === null,
    )

    if (term) {
      found = found.filter((one) =>
        [...Object.values(one.name), ...Object.values(one.slug)].some((text) =>
          text.toLowerCase().includes(term),
        ),
      )
    }

    if (category === 'root') found = found.filter((one) => one.category_id === null)
    else if (category) found = found.filter((one) => inside(one.category_id, Number(category)))

    const attention = flag('attention')
    if (attention !== null) found = found.filter((one) => (one.attention !== null) === attention)

    const published = flag('published')
    if (published !== null) found = found.filter((one) => one.is_published === published)

    const rows = found
      .sort((a, b) => a.position - b.position || a.id - b.id)
      .map((one) => row(one, locale, words))

    const empty = flag('empty')

    return paginate(
      empty === null ? rows : rows.filter((one) => (one.products_count === 0) === empty),
      query,
    )
  })

  on('GET', '/catalog/landings/facets', ({ query, locale }) => {
    const category = query.get('category')

    return { data: facetsOf(category ? Number(category) : null, locale) }
  })

  on('POST', '/catalog/landings/count', ({ body, locale }) => {
    const categoryId =
      body.category_id === null || body.category_id === undefined ? null : Number(body.category_id)
    const filters = clean(body.filters)

    if (Object.keys(filters).length === 0) {
      const text = lineOf(locale, NS, 'errors.set-empty')
      throw fail(422, text, { filters: [text] })
    }

    const holder = setHolder(categoryId, filters, body.except ? Number(body.except) : null)

    return {
      data: {
        count: count(categoryId, filters, locale),
        taken: holder ? { id: holder.id, name: word(holder.name, locale) } : null,
        suggested: suggest(categoryId, filters, labels(locale)),
      },
    }
  })

  on('POST', '/catalog/landings/generate', ({ body, locale }) => {
    const rows = plan(body, locale, fail)
    const free = rows.filter((one) => one.conflict === null)

    if (body.dry_run) return { data: { rows, total: rows.length, free: free.length } }

    const run: RunRecord = {
      id: runs.length + 1,
      rows: free,
      total: rows.length,
      done: 0,
      skipped: rows.length - free.length,
      failed: 0,
      errors: [],
      status: 'queued',
      created_at: now(),
      finished_at: null,
    }

    // A few at once, as `sync_limit` does; more go «to the queue» and move on with each poll.
    if (free.length <= 5) {
      for (const one of free) make(one, Boolean(body.publish))
      return {
        data: {
          ...runShape({ ...run, done: free.length, status: 'done', finished_at: now() }),
          id: null,
        },
      }
    }

    ;(run as RunRecord & { publish?: boolean }).publish = Boolean(body.publish)
    runs.push(run)

    return { data: runShape(run) }
  })

  on('GET', '/catalog/landings/generate/(\\d+)', ({ params }) => {
    const run = runs.find((one) => one.id === Number(params[0]))

    if (!run) throw fail(404, 'No such run.')

    if (run.status === 'queued' || run.status === 'running') {
      for (const one of run.rows.slice(run.done, run.done + 3)) {
        make(one, Boolean((run as RunRecord & { publish?: boolean }).publish))
        run.done++
      }
      run.status = run.done >= run.rows.length ? 'done' : 'running'
      if (run.status === 'done') run.finished_at = now()
    }

    return { data: runShape(run) }
  })

  on('GET', '/catalog/landings/(\\d+)', ({ params, locale }) => ({
    data: detail(find(params[0]!, true), locale),
  }))

  on('POST', '/catalog/landings', ({ body, locale }) => {
    const record = landing(0, { is_published: false, on_category: false, position: 0 })

    write(record, body, locale, fail)
    record.id = Math.max(0, ...landings.map((one) => one.id)) + 1
    landings.push(record)

    return { data: detail(record, locale) }
  })

  on('PUT', '/catalog/landings/(\\d+)', ({ params, body, locale }) => {
    const record = find(params[0]!)

    write(record, body, locale, fail)

    return { data: detail(record, locale) }
  })

  on('DELETE', '/catalog/landings/(\\d+)', ({ params }) => {
    find(params[0]!).deleted_at = now()

    return null
  })

  on('POST', '/catalog/landings/(\\d+)/restore', ({ params, locale }) => {
    const record = find(params[0]!, true)

    record.deleted_at = null

    return { data: detail(record, locale) }
  })

  on('POST', '/catalog/landings/(\\d+)/(publish|unpublish)', ({ params, locale }) => {
    const record = find(params[0]!)

    if (
      params[1] === 'publish' &&
      (Object.keys(record.filters).length === 0 || record.attention === 'duplicate')
    ) {
      const text = lineOf(locale, NS, 'errors.set-empty')
      throw fail(422, text, { filters: [text] })
    }

    record.is_published = params[1] === 'publish'
    record.updated_at = now()

    return { data: detail(record, locale) }
  })
}
