import type { IncomingMessage, ServerResponse } from 'node:http'
import type { Handler } from './catalog'
import { Reply } from './reply'
import { claimUpload, peekUpload } from './uploads'

/**
 * The exchange's half of the fake server (§12 of WEBX_UI_MODULE_CATALOG_EXCHANGE.md): columns, a
 * look at a file, imports and exports as runs, their errors, their files, and the profiles.
 *
 * What is real: `inspect` reads the CSV that was uploaded — its BOM, UTF-8 or the fallback
 * encoding, the separator of its first line, quotes — and guesses the mapping the way
 * `Importer::suggest()` does; a row goes through the same write as the product form, so its
 * refusals are the form's. What is not: the run moves on a timer, a few rows a tick, so that the
 * progress is there to be looked at; XLSX is refused (a zip reader is not worth it here); an
 * address is downloaded only when it is the demo one; the columns are the core's (§7.1) — the
 * satellites' columns are checked on `webx-cms.local`. `images` and `external_id` are read and
 * kept, but no picture is fetched.
 */

type Map = Record<string, string>

interface ProductLike {
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
  deleted_at: string | null
}

interface CategoryLike {
  id: number
  parent_id: number | null
  name: Map
  deleted_at: string | null
}

/** What the catalogue's fixture lends the exchange: its records and its one way of writing them. */
export interface ExchangeStore {
  products: () => ProductLike[]
  blank: () => ProductLike
  /** The product form's save; `dry` stops after its checks. Throws its 422 on a refusal. */
  write: (product: never, values: Record<string, unknown>, locale: string, dry?: boolean) => void
  categories: () => CategoryLike[]
  addCategory: (parent: number | null, name: string) => number
  /** The list's selection as ids, the way the bulk actions read it. */
  select: (selection: Record<string, unknown>, trashed: boolean) => number[]
  /** Journal rows written inside `work` carry the run. */
  inRun: <T>(run: { id: number; summary: Record<string, unknown> }, work: () => T) => T
  defaultLocale: string
}

type Fail = (
  status: number,
  message: string,
  errors?: Record<string, string[]>,
  extra?: Record<string, unknown>,
) => Error

type Line = (locale: string, namespace: string, path: string) => string

interface Column {
  key: string
  field: string
  localized: boolean
  /** The dictionary path of its words. */
  label: string
}

const COLUMNS: Column[] = [
  { key: 'id', field: 'id', localized: false, label: 'exchange.columns.id' },
  { key: 'sku', field: 'sku', localized: false, label: 'product.sku' },
  { key: 'barcode', field: 'barcode', localized: false, label: 'product.barcode' },
  {
    key: 'external_id',
    field: 'external_id',
    localized: false,
    label: 'exchange.columns.external_id',
  },
  { key: 'name', field: 'name', localized: true, label: 'product.name' },
  { key: 'slug', field: 'slug', localized: true, label: 'product.slug' },
  { key: 'summary', field: 'summary', localized: true, label: 'product.summary' },
  { key: 'description', field: 'description', localized: true, label: 'product.description' },
  { key: 'category', field: 'category_id', localized: false, label: 'product.category_id' },
  { key: 'categories', field: 'categories', localized: false, label: 'product.categories' },
  { key: 'price', field: 'price', localized: false, label: 'product.price' },
  { key: 'old_price', field: 'old_price', localized: false, label: 'product.old_price' },
  { key: 'unit', field: 'unit', localized: false, label: 'product.unit' },
  { key: 'priority', field: 'priority', localized: false, label: 'product.priority' },
  { key: 'is_published', field: 'is_published', localized: false, label: 'product.is_published' },
  { key: 'images', field: 'images', localized: false, label: 'product.images' },
]

const UNITS = ['pcs', 'pack', 'kg', 'g', 'l', 'm']

/* The server's chunk is 200 rows; here a tick is a few rows, so a file of twenty takes a while. */
const ROWS_PER_TICK = 3
const TICK = 900
const MAX_ERRORS = 1000

/* The one address the playground "downloads": the demo price list below. */
const DEMO_URL = 'https://shop.webx-demo.test/price/catalog-import.csv'

/**
 * The demo price list: updates of products the shop has (by article number), new ones, and three
 * rows that are refused on purpose — a price that is not a number, a category the catalogue does
 * not have (created with «Create what is missing»), and a product published without a category.
 */
const DEMO_CSV = [
  'sku,name,name@en,price,old_price,category,is_published,unit',
  'WX-1007,Ноутбук Aero 14,Aero 14 laptop,84990,89990,Электроника/Ноутбуки,1,pcs',
  'WX-1014,Ноутбук Aero 16,Aero 16 laptop,114990,119990,Электроника/Ноутбуки,1,pcs',
  'WX-1021,Ноутбук Slate 13,Slate 13 laptop,71990,,Электроника/Ноутбуки,1,pcs',
  'WX-1028,Смартфон Nova 8,Nova 8 phone,31990,32990,Электроника/Смартфоны/Android,1,pcs',
  'WX-1035,Смартфон Nova 8 Pro,Nova 8 Pro phone,44990,,Электроника/Смартфоны/Android,1,pcs',
  '"WX-1056","Чайник Aqua 1,7 л","Aqua kettle 1.7 l",3290,3490,Бытовая техника/Чайники,1,pcs',
  'WX-1063,Чайник Steel,Steel kettle,4790,,Бытовая техника/Чайники,1,pcs',
  'WX-1077,Пылесос Cyclone,Cyclone vacuum,14990,15990,Бытовая техника/Пылесосы,1,pcs',
  'WX-9001,Смарт-часы Pulse Watch,Pulse Watch smartwatch,12990,,Электроника/Аудио,1,pcs',
  'WX-9002,Наушники Pulse Pro,Pulse Pro earphones,8990,,Электроника/Аудио/Наушники,1,pcs',
  'WX-9003,Колонка Boom Max,Boom Max speaker,9990,11990,Электроника/Аудио/Колонки,1,pcs',
  'WX-9004,Кабель Lightning 1 м,Lightning cable 1 m,690,,Аксессуары/Кабели,0,pcs',
  'WX-9005,Чехол для Aero 14,Aero 14 sleeve,двенадцать,,Аксессуары/Чехлы,0,pcs',
  'WX-9006,Планшет Slate Tab,Slate Tab tablet,39990,,Электроника/Планшеты,1,pcs',
  'WX-9007,Зарядная станция,Charging dock,3490,,,1,pcs',
  'WX-9008,Фильтр HEPA,HEPA filter,990,,Бытовая техника/Пылесосы,1,pack',
  'WX-9009,Кофеварка Brew,Brew coffee maker,7990,,Бытовая техника,1,pcs',
  'WX-9010,Термопаста Pro,Thermal paste Pro,690,,Аксессуары,0,g',
].join('\r\n')

interface RunError {
  row: number
  column: string | null
  value: string | null
  message: string
}

interface RunRecord {
  id: number
  profile_id: number | null
  direction: 'import' | 'export'
  format: 'csv' | 'xlsx'
  options: Record<string, unknown>
  mapping: Record<string, string> | string[]
  dry_run: boolean
  status: 'queued' | 'running' | 'done' | 'stopped' | 'failed'
  source: string | null
  rows_total: number
  rows_done: number
  created: number
  updated: number
  skipped: number
  failed: number
  absent: number
  history_id: number | null
  admin_id: number | null
  admin_name: string | null
  started_at: string | null
  finished_at: string | null
  created_at: string | null
  /* Not in the answer: what the run works on. */
  rows: { number: number; cells: string[] }[]
  header: string[]
  ids: number[]
  errors: RunError[]
  seen: Set<number>
  categoriesSeen: Set<number>
  file: string | null
}

interface ProfileRecord {
  id: number
  name: string
  direction: 'import' | 'export'
  format: 'csv' | 'xlsx'
  options: Record<string, unknown>
  mapping: Record<string, string> | string[]
  last_run_id: number | null
  created_at: string
  updated_at: string
}

const runs: RunRecord[] = []
const profiles: ProfileRecord[] = []
const externalIds = new Map<number, string>()
const pictures = new Map<number, string[]>()
const ADMIN = { id: 1, name: 'Анна Ковальчук' }
/* Away from the journal fixture's own runs (40-ish), which the history mock keeps. */
const HISTORY_BASE = 9000

const now = () => new Date().toISOString()

/* ------------------------------------------------------------------ reading ----- */

/** The text of a CSV: a BOM says the encoding; otherwise UTF-8 if it reads, else the fallback. */
function decode(bytes: Buffer, given?: string): { text: string; encoding: string } {
  if (bytes[0] === 0xef && bytes[1] === 0xbb && bytes[2] === 0xbf) {
    return { text: bytes.subarray(3).toString('utf8'), encoding: 'UTF-8' }
  }

  if (given) {
    return { text: new TextDecoder(given.toLowerCase()).decode(bytes), encoding: given }
  }

  try {
    return { text: new TextDecoder('utf-8', { fatal: true }).decode(bytes), encoding: 'UTF-8' }
  } catch {
    return { text: new TextDecoder('windows-1252').decode(bytes), encoding: 'Windows-1252' }
  }
}

/** The separator of the first line that splits it the most: `,`, `;` or a tab. */
function guessDelimiter(text: string): string {
  const first = text.split(/\r?\n/, 1)[0] ?? ''
  const counts = [',', ';', '\t'].map((one) => [one, first.split(one).length] as const)

  return counts.sort((a, b) => b[1] - a[1])[0]![0]
}

/** RFC 4180: quotes, doubled quotes, separators and line breaks inside quotes. */
function parseCsv(text: string, delimiter: string): string[][] {
  const rows: string[][] = []
  let row: string[] = []
  let cell = ''
  let quoted = false

  for (let i = 0; i < text.length; i++) {
    const char = text[i]!

    if (quoted) {
      if (char === '"' && text[i + 1] === '"') {
        cell += '"'
        i++
      } else if (char === '"') {
        quoted = false
      } else {
        cell += char
      }

      continue
    }

    if (char === '"' && cell === '') quoted = true
    else if (char === delimiter) {
      row.push(cell)
      cell = ''
    } else if (char === '\n' || char === '\r') {
      if (char === '\r' && text[i + 1] === '\n') i++
      row.push(cell)
      rows.push(row)
      row = []
      cell = ''
    } else cell += char
  }

  if (cell !== '' || row.length > 0) {
    row.push(cell)
    rows.push(row)
  }

  return rows
}

interface Read {
  name: string
  format: 'csv'
  options: { encoding: string; delimiter: string }
  header: string[]
  /** Rows after the header with their line numbers; empty rows are counted, not kept. */
  rows: { number: number; cells: string[] }[]
}

function readFile(
  name: string,
  bytes: Buffer,
  given: { encoding?: string; delimiter?: string },
  fail: Fail,
  locale: string,
  line: Line,
): Read {
  const extension = name.split('.').pop()?.toLowerCase() ?? ''

  if (extension === 'xlsx') {
    throw fail(422, 'The playground reads CSV only; XLSX is read on a real site.', {
      upload_id: ['The playground reads CSV only; XLSX is read on a real site.'],
    })
  }

  if (!['csv', 'txt'].includes(extension)) {
    const message = line(locale, 'webx-catalog', 'exchange.errors.unknown-format').replace(
      ':known',
      'csv, xlsx',
    )

    throw fail(422, message, { upload_id: [message] })
  }

  const { text, encoding } = decode(bytes, given.encoding)
  const delimiter = given.delimiter || guessDelimiter(text)
  const all = parseCsv(text, delimiter)
  const header = (all[0] ?? []).map((one) => one.trim())
  const rows = all
    .slice(1)
    .map((cells, index) => ({ number: index + 2, cells }))
    .filter((row) => row.cells.some((cell) => cell.trim() !== ''))

  return { name, format: 'csv', options: { encoding, delimiter }, header, rows }
}

/* ------------------------------------------------------------------ routes ----- */

export function registerExchange(
  on: (method: string, pattern: string, handler: Handler) => void,
  fail: Fail,
  line: Line,
  store: ExchangeStore,
): void {
  const L = store.defaultLocale
  const others = ['en']
  const word = (locale: string, path: string, params: Record<string, string | number> = {}) =>
    Object.entries(params).reduce(
      (text, [key, value]) => text.replaceAll(`:${key}`, String(value)),
      line(locale, 'webx-catalog', path),
    )

  const columnsFor = (locale: string) =>
    COLUMNS.map((column) => ({
      key: column.key,
      label: word(locale, column.label),
      field: column.field,
      localized: column.localized,
      locales: column.localized ? others : [],
    }))

  /** The column of a code: `name`, `name@en`; null for what is not one. */
  const columnOf = (code: string): { column: Column; locale: string | null } | null => {
    const [key, locale = null] = code.toLowerCase().split('@')
    const column = COLUMNS.find((one) => one.key === key)

    if (!column || (locale !== null && (!column.localized || !others.includes(locale)))) {
      return null
    }

    return { column, locale }
  }

  /** `Importer::suggest()`: the code first, then the words without regard to case. */
  const suggest = (header: string[], locale: string): Record<string, string | null> => {
    const labels = new globalThis.Map(
      columnsFor(locale).map((one) => [one.label.toLowerCase(), one.key]),
    )
    const taken = new Set<string>()
    const mapping: Record<string, string | null> = {}

    for (const cell of header) {
      if (cell === '' || cell in mapping) continue

      const code = columnOf(cell) ? cell.toLowerCase() : (labels.get(cell.toLowerCase()) ?? null)

      mapping[cell] = code !== null && !taken.has(code) ? code : null
      if (code !== null) taken.add(code)
    }

    return mapping
  }

  const source = (
    body: Record<string, unknown>,
    locale: string,
    claim: boolean,
  ): { name: string; bytes: Buffer; label: string } => {
    if (typeof body.upload_id === 'string' && body.upload_id !== '') {
      const found = (claim ? claimUpload : peekUpload)(body.upload_id, 'catalog.exchange')

      if (typeof found === 'string') {
        const message = word(locale, 'exchange.errors.upload-missing')

        throw fail(422, message, { upload_id: [message] })
      }

      return { name: found.name, bytes: found.bytes, label: found.name }
    }

    const url = typeof body.url === 'string' ? body.url.trim() : ''

    if (url === '') {
      const message = word(locale, 'exchange.errors.no-source')

      throw fail(422, message, { upload_id: [message] })
    }

    if (!/^https?:\/\//i.test(url)) {
      const message = word(locale, 'exchange.errors.not-a-url', { url })

      throw fail(422, message, { url: [message] })
    }

    if (url !== DEMO_URL) {
      const message = word(locale, 'exchange.errors.unreachable', {
        reason: `the playground downloads only ${DEMO_URL}`,
      })

      throw fail(422, message, { url: [message] })
    }

    return { name: 'catalog-import.csv', bytes: Buffer.from(DEMO_CSV, 'utf8'), label: url }
  }

  const answer = (run: RunRecord) => ({
    id: run.id,
    profile_id: run.profile_id,
    direction: run.direction,
    format: run.format,
    options: run.options,
    mapping: run.mapping,
    dry_run: run.dry_run,
    status: run.status,
    source: run.source,
    rows_total: run.rows_total,
    rows_done: run.rows_done,
    created: run.created,
    updated: run.updated,
    skipped: run.skipped,
    failed: run.failed,
    absent: run.absent,
    history_id: run.history_id,
    admin_id: run.admin_id,
    admin_name: run.admin_name,
    file_url:
      run.direction === 'export' && run.status === 'done' && run.file !== null
        ? `/api/cms/catalog/exchange/download/${run.id}/catalog-export-${run.id}.csv?signature=playground`
        : null,
    started_at: run.started_at,
    finished_at: run.finished_at,
    created_at: run.created_at,
  })

  const findRun = (id: string | undefined) => {
    const run = runs.find((one) => one.id === Number(id))

    if (!run) throw fail(404, 'No such run.')

    return run
  }

  const findProfile = (id: unknown, direction?: string) => {
    const profile = profiles.find(
      (one) => one.id === Number(id) && (direction === undefined || one.direction === direction),
    )

    return profile
  }

  /* ---------------------------------------------------------------- the codecs ----- */

  class RowError extends Error {
    constructor(
      readonly column: string | null,
      readonly value: string | null,
      message: string,
    ) {
      super(message)
    }
  }

  const categoryPath = (id: number): string => {
    const all = store.categories()
    const names: string[] = []
    let at = all.find((one) => one.id === id)

    while (at) {
      names.unshift(at.name[L] ?? '')
      at = at.parent_id === null ? undefined : all.find((one) => one.id === at!.parent_id)
    }

    return names.join('/')
  }

  /** A path of names from the root, or `#id`; created on the way with «Create what is missing». */
  const categoryOf = (
    cell: string,
    code: string,
    create: boolean,
    locale: string,
    dry: boolean,
  ): number => {
    const value = cell.trim()

    if (value.startsWith('#')) {
      const id = Number(value.slice(1))

      if (!store.categories().some((one) => one.id === id && one.deleted_at === null)) {
        throw new RowError(code, cell, word(locale, 'exchange.errors.category-unknown-id', { id }))
      }

      return id
    }

    const names = value
      .split('/')
      .map((one) => one.trim())
      .filter(Boolean)

    if (names.length === 0) {
      throw new RowError(code, cell, word(locale, 'exchange.errors.category-empty'))
    }

    let parent: number | null = null

    for (const name of names) {
      const found = store
        .categories()
        .filter(
          (one) =>
            one.parent_id === parent &&
            one.deleted_at === null &&
            (one.name[L] ?? '').toLowerCase() === name.toLowerCase(),
        )

      if (found.length > 1) {
        throw new RowError(
          code,
          cell,
          word(locale, 'exchange.errors.category-ambiguous', {
            name,
            ids: found.map((one) => `#${one.id}`).join(', '),
          }),
        )
      }

      if (found.length === 1) {
        parent = found[0]!.id
        continue
      }

      if (!create) {
        throw new RowError(
          code,
          cell,
          word(locale, 'exchange.errors.category-missing', { path: value }),
        )
      }

      // A check creates nothing: the path stands for a category that would be there.
      if (dry) return -1

      parent = store.addCategory(parent, name)
    }

    return parent!
  }

  const number = (cell: string, code: string, locale: string): number => {
    const value = Number(cell.trim().replace(/\s/g, '').replace(',', '.'))

    if (!Number.isFinite(value)) {
      throw new RowError(code, cell, word(locale, 'exchange.errors.not-a-number'))
    }

    return value
  }

  const yes = (cell: string, code: string, locale: string): boolean => {
    const value = cell.trim().toLowerCase()

    if (['1', 'yes', 'true'].includes(value)) return true
    if (['0', 'no', 'false'].includes(value)) return false

    throw new RowError(code, cell, word(locale, 'exchange.errors.not-a-boolean'))
  }

  /** The row as the editor would send it, and what the row writes itself. */
  const input = (
    run: RunRecord,
    cells: string[],
    product: ProductLike | null,
    locale: string,
    dry: boolean,
  ): { values: Record<string, unknown>; external: string | null; images: string[] | null } => {
    const values: Record<string, unknown> = {}
    const mapping = run.mapping as Record<string, string>
    const clears = Boolean(run.options.empty_clears)
    const create = Boolean(run.options.create_missing)
    let external: string | null = null
    let images: string[] | null = null

    run.header.forEach((header, index) => {
      const code = mapping[header]

      if (!code) return

      const found = columnOf(code)

      if (!found) return

      const cell = cells[index] ?? ''
      const empty = cell.trim() === ''

      if (empty && !clears) return

      const { column, locale: language } = found

      switch (column.key) {
        case 'id':
          return
        case 'external_id':
          external = empty ? '' : cell.trim()

          return
        case 'images':
          images = empty
            ? []
            : cell
                .split(';')
                .map((one) => one.trim())
                .filter(Boolean)

          return
        case 'name':
        case 'slug':
        case 'summary':
        case 'description': {
          const before = (values[column.key] as Map | undefined) ?? {
            ...((product?.[column.key] as Map | undefined) ?? {}),
          }

          values[column.key] = { ...before, [language ?? L]: empty ? '' : cell.trim() }

          return
        }
        case 'sku':
        case 'barcode':
          values[column.key] = empty ? null : cell.trim()

          return
        case 'unit':
          if (!empty && !UNITS.includes(cell.trim())) {
            throw new RowError(
              code,
              cell,
              word(locale, 'exchange.errors.unknown-unit', { known: UNITS.join(', ') }),
            )
          }
          values.unit = empty ? null : cell.trim()

          return
        case 'price':
        case 'old_price':
          values[column.key] = empty ? null : number(cell, code, locale)

          return
        case 'priority':
          values.priority = empty ? 0 : Math.trunc(number(cell, code, locale))

          return
        case 'is_published':
          values.is_published = empty ? false : yes(cell, code, locale)

          return
        case 'category': {
          const id = empty ? null : categoryOf(cell, code, create, locale, dry)

          // A check that would create the category: the form has nothing to check against.
          if (id !== -1) values.category_id = id

          return
        }
        case 'categories':
          values.categories = empty
            ? []
            : cell
                .split(';')
                .filter((one) => one.trim() !== '')
                .map((one) => categoryOf(one, code, create, locale, dry))
                .filter((id) => id !== -1)
      }
    })

    return { values, external, images }
  }

  /** The column a refusal of the form belongs to: `category_id` is the cell `category`. */
  const columnOfField = (run: RunRecord, field: string): string | null => {
    const [name, language = null] = field.split('.')
    const key = COLUMNS.find((one) => one.field === name)?.key ?? name ?? ''
    const codes = Object.values(run.mapping as Record<string, string>)
    const exact = language && language !== L ? `${key}@${language}` : key

    return codes.includes(exact) ? exact : codes.includes(key) ? key : null
  }

  /* ---------------------------------------------------------------- one row ----- */

  const importRow = (run: RunRecord, row: { number: number; cells: string[] }, locale: string) => {
    const keyName = String(run.options.key ?? 'sku')
    const mapping = run.mapping as Record<string, string>
    const keyHeader = Object.keys(mapping).find((header) => mapping[header] === keyName)
    const keyCell = keyHeader ? (row.cells[run.header.indexOf(keyHeader)] ?? '').trim() : ''
    const all = store.products()
    const product =
      keyCell === ''
        ? null
        : (all.find((one) =>
            keyName === 'id'
              ? one.id === Number(keyCell)
              : keyName === 'external_id'
                ? externalIds.get(one.id) === keyCell
                : one[keyName as 'sku' | 'barcode'] === keyCell,
          ) ?? null)

    try {
      if (product?.deleted_at) {
        throw new RowError(
          keyName,
          keyCell,
          word(locale, 'exchange.errors.trashed', { id: product.id }),
        )
      }

      const mode = String(run.options.mode ?? 'upsert')

      if ((mode === 'update' && !product) || (mode === 'create' && product)) {
        run.skipped++

        return
      }

      const dry = run.dry_run
      const { values, external, images } = input(run, row.cells, product, locale, dry)
      const target = product ?? store.blank()

      // A new product without its key column is a new product with that key, as the form has it.
      if (!product && keyName !== 'id' && keyName !== 'external_id' && !(keyName in values)) {
        values[keyName] = keyCell || null
      }

      try {
        store.write(target as never, values, locale, dry)
      } catch (error) {
        const refused = (error as { errors?: Record<string, string[]> }).errors ?? {}
        const entries = Object.entries(refused)

        if (entries.length === 0) throw error

        for (const [field, messages] of entries) {
          const column = columnOfField(run, field)
          const index = column ? run.header.findIndex((header) => mapping[header] === column) : -1

          addError(run, {
            row: row.number,
            column,
            value: index >= 0 ? (row.cells[index] ?? null) : null,
            message: messages[0] ?? String(error),
          })
        }

        run.failed++

        return
      }

      if (!dry) {
        if (external !== null) externalIds.set(target.id, external)
        if (images !== null) pictures.set(target.id, images)
        run.seen.add(target.id)
        if (target.category_id !== null) run.categoriesSeen.add(target.category_id)
      }

      if (product) run.updated++
      else run.created++
    } catch (error) {
      if (!(error instanceof RowError)) throw error

      addError(run, {
        row: row.number,
        column: error.column,
        value: error.value,
        message: error.message,
      })
      run.failed++
    }
  }

  const addError = (run: RunRecord, error: RunError) => {
    if (run.errors.length < MAX_ERRORS) {
      run.errors.push({ ...error, value: error.value?.slice(0, 500) ?? null })
    }
  }

  /* ---------------------------------------------------------------- the timer ----- */

  const finish = (run: RunRecord) => {
    if (run.direction === 'import' && !run.dry_run && run.options.absent === 'unpublish') {
      const scope = run.options.absent_scope === 'all' ? null : run.categoriesSeen

      for (const product of store.products()) {
        if (product.deleted_at || !product.is_published || run.seen.has(product.id)) continue
        if (scope !== null && (product.category_id === null || !scope.has(product.category_id))) {
          continue
        }

        store.inRun({ id: run.history_id!, summary: summary(run) }, () =>
          store.write(product as never, { is_published: false }, L),
        )
        run.absent++
      }
    }

    run.status = 'done'
    run.finished_at = now()

    const profile = findProfile(run.profile_id)

    if (profile) profile.last_run_id = run.id
  }

  const summary = (run: RunRecord) => ({
    exchange: run.id,
    source: run.source,
    rows: run.rows_total,
  })

  const tick = (run: RunRecord, locale: string) => {
    if (run.status === 'queued') {
      run.status = 'running'
      run.started_at = now()
    }

    if (run.direction === 'export') {
      run.rows_done = Math.min(run.rows_total, run.rows_done + ROWS_PER_TICK * 4)

      if (run.rows_done >= run.rows_total) {
        run.file = writeExport(run)
        finish(run)
      }

      return
    }

    const chunk = run.rows.slice(run.rows_done, run.rows_done + ROWS_PER_TICK)
    const work = () => {
      for (const row of chunk) importRow(run, row, locale)
    }

    if (run.dry_run || run.history_id === null) work()
    else store.inRun({ id: run.history_id, summary: summary(run) }, work)

    run.rows_done += chunk.length

    if (run.errors.length >= MAX_ERRORS) {
      run.status = 'stopped'
      run.finished_at = now()

      return
    }

    if (run.rows_done >= run.rows_total) finish(run)
  }

  const follow = (run: RunRecord, locale: string) => {
    const timer = setInterval(() => {
      if (run.status !== 'queued' && run.status !== 'running') {
        clearInterval(timer)

        return
      }

      try {
        tick(run, locale)
      } catch {
        run.status = 'failed'
        run.finished_at = now()
      }
    }, TICK)
  }

  /* ---------------------------------------------------------------- export ----- */

  const cellOf = (product: ProductLike, column: Column, language: string | null): string => {
    switch (column.key) {
      case 'id':
        return String(product.id)
      case 'external_id':
        return externalIds.get(product.id) ?? ''
      case 'images':
        return (pictures.get(product.id) ?? []).join(';')
      case 'name':
      case 'slug':
      case 'summary':
      case 'description':
        return product[column.key][language ?? L] ?? ''
      case 'category':
        return product.category_id === null ? '' : categoryPath(product.category_id)
      case 'categories':
        return product.categories.map(categoryPath).join(';')
      case 'is_published':
        return product.is_published ? '1' : '0'
      default: {
        const value = product[column.key as keyof ProductLike]

        return value === null || value === undefined ? '' : String(value)
      }
    }
  }

  const quote = (cell: string) =>
    /[",;\r\n]/.test(cell) ? `"${cell.replaceAll('"', '""')}"` : cell

  const writeExport = (run: RunRecord): string => {
    const codes = run.mapping as string[]
    const all = store.products()
    const lines = [codes.join(',')]

    for (const id of run.ids) {
      const product = all.find((one) => one.id === id)

      if (!product) continue

      lines.push(
        codes
          .map((code) => {
            const found = columnOf(code)

            return quote(found ? cellOf(product, found.column, found.locale) : '')
          })
          .join(','),
      )
    }

    return '\uFEFF' + lines.join('\r\n') + '\r\n'
  }

  /* ---------------------------------------------------------------- the routes ----- */

  on('GET', '/catalog/exchange/columns', ({ locale }) => ({ data: columnsFor(locale) }))

  on('POST', '/catalog/exchange/inspect', ({ body, locale }) => {
    const file = source(body, locale, false)
    const read = readFile(
      file.name,
      file.bytes,
      {
        encoding: typeof body.encoding === 'string' ? body.encoding : undefined,
        delimiter: typeof body.delimiter === 'string' ? body.delimiter : undefined,
      },
      fail,
      locale,
      line,
    )

    return new Reply(200, {
      data: {
        format: read.format,
        options: read.options,
        header: read.header,
        sample: read.rows.slice(0, 5).map((row) => row.cells),
        mapping: suggest(read.header, locale),
      },
    })
  })

  const OPTION_CHOICES: Record<string, string[]> = {
    key: ['sku', 'external_id', 'barcode', 'id'],
    mode: ['upsert', 'update', 'create'],
    absent: ['keep', 'unpublish'],
    absent_scope: ['categories', 'all'],
    images: ['append', 'replace'],
  }

  const DEFAULTS = {
    key: 'sku',
    mode: 'upsert',
    empty_clears: false,
    absent: 'keep',
    absent_scope: 'categories',
    create_missing: false,
    images: 'append',
  }

  /** `Importer::options()`: the defaults, each choice checked. */
  const checkOptions = (given: Record<string, unknown>, locale: string) => {
    const options: Record<string, unknown> = { ...DEFAULTS }

    for (const [name, allowed] of Object.entries(OPTION_CHOICES)) {
      if (given[name] === undefined || given[name] === null) continue

      if (!allowed.includes(String(given[name]))) {
        const message = word(locale, 'exchange.errors.option', {
          name,
          allowed: allowed.join(', '),
        })

        throw fail(422, message, { [`options.${name}`]: [message] })
      }

      options[name] = given[name]
    }

    for (const flag of ['empty_clears', 'create_missing']) {
      if (given[flag] !== undefined) options[flag] = Boolean(given[flag])
    }

    for (const read of ['encoding', 'delimiter']) {
      if (typeof given[read] === 'string' && given[read] !== '') options[read] = given[read]
    }

    return options
  }

  /** `Importer::mapping()`: every code a column, none twice, the key among them. */
  const checkMapping = (mapping: Record<string, unknown>, key: string, locale: string) => {
    const checked: Record<string, string> = {}

    for (const [header, code] of Object.entries(mapping)) {
      if (code === null || code === '') continue

      const known = typeof code === 'string' && columnOf(code) !== null

      if (!known || Object.values(checked).includes(String(code))) {
        const message = word(
          locale,
          known ? 'exchange.errors.mapping-twice' : 'exchange.errors.mapping-unknown',
          { code: String(code) },
        )

        throw fail(422, message, { mapping: [message] })
      }

      checked[header.trim()] = String(code)
    }

    if (!Object.values(checked).includes(key)) {
      const message = word(locale, 'exchange.errors.key-not-mapped', { key })

      throw fail(422, message, { 'options.key': [message] })
    }

    return checked
  }

  on('POST', '/catalog/exchange/import', ({ body, locale }) => {
    const profile =
      body.profile_id === undefined || body.profile_id === null
        ? undefined
        : findProfile(body.profile_id, 'import')

    if (body.profile_id != null && !profile) {
      const message = word(locale, 'exchange.errors.profile-missing')

      throw fail(422, message, { profile_id: [message] })
    }

    const options = checkOptions(
      { ...(profile?.options ?? {}), ...((body.options ?? {}) as Record<string, unknown>) },
      locale,
    )
    const mapping = checkMapping(
      (body.mapping ?? profile?.mapping ?? {}) as Record<string, unknown>,
      String(options.key),
      locale,
    )
    const file = source(body, locale, true)
    const read = readFile(
      file.name,
      file.bytes,
      {
        encoding: typeof options.encoding === 'string' ? options.encoding : undefined,
        delimiter: typeof options.delimiter === 'string' ? options.delimiter : undefined,
      },
      fail,
      locale,
      line,
    )
    const dry = Boolean(body.dry_run)
    const id = runs.length + 1
    const run: RunRecord = {
      id,
      profile_id: profile?.id ?? null,
      direction: 'import',
      format: 'csv',
      options: { ...options, format: 'csv' },
      mapping,
      dry_run: dry,
      status: 'queued',
      source: file.label,
      rows_total: read.rows.length,
      rows_done: 0,
      created: 0,
      updated: 0,
      skipped: 0,
      failed: 0,
      absent: 0,
      history_id: dry ? null : HISTORY_BASE + id,
      admin_id: ADMIN.id,
      admin_name: ADMIN.name,
      started_at: null,
      finished_at: null,
      created_at: now(),
      rows: read.rows,
      header: read.header,
      ids: [],
      errors: [],
      seen: new Set(),
      categoriesSeen: new Set(),
      file: null,
    }

    runs.push(run)
    follow(run, locale)

    return new Reply(202, { data: answer(run) })
  })

  on('POST', '/catalog/exchange/export', ({ body, locale }) => {
    const selection = (body.selection ?? {}) as Record<string, unknown>
    const profile =
      body.profile_id === undefined || body.profile_id === null
        ? undefined
        : findProfile(body.profile_id, 'export')

    if (body.profile_id != null && !profile) {
      const message = word(locale, 'exchange.errors.profile-missing')

      throw fail(422, message, { profile_id: [message] })
    }

    const codes = (
      Array.isArray(body.columns) ? body.columns : ((profile?.mapping as string[]) ?? [])
    ).map(String)

    for (const code of codes) {
      if (columnOf(code) === null) {
        const message = word(locale, 'exchange.errors.mapping-unknown', { code })

        throw fail(422, message, { columns: [message] })
      }
    }

    const format = String(body.format ?? profile?.format ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx'
    const ids = store.select(selection, Boolean(selection.trashed))
    const id = runs.length + 1
    const run: RunRecord = {
      id,
      profile_id: profile?.id ?? null,
      direction: 'export',
      format,
      options: {},
      mapping: codes,
      dry_run: false,
      status: 'queued',
      source: null,
      rows_total: ids.length,
      rows_done: 0,
      created: 0,
      updated: 0,
      skipped: 0,
      failed: 0,
      absent: 0,
      history_id: null,
      admin_id: ADMIN.id,
      admin_name: ADMIN.name,
      started_at: null,
      finished_at: null,
      created_at: now(),
      rows: [],
      header: [],
      ids,
      errors: [],
      seen: new Set(),
      categoriesSeen: new Set(),
      file: null,
    }

    runs.push(run)
    follow(run, locale)

    return new Reply(202, { data: answer(run) })
  })

  on('GET', '/catalog/exchange/runs', ({ query }) => {
    const perPage = Math.min(100, Math.max(1, Number(query.get('per_page') ?? '20')))
    const sorted = [...runs].sort((a, b) => b.id - a.id)
    const last = Math.max(1, Math.ceil(sorted.length / perPage))
    const page = Math.min(Math.max(1, Number(query.get('page') ?? '1')), last)

    return {
      data: sorted.slice((page - 1) * perPage, page * perPage).map(answer),
      current_page: page,
      last_page: last,
      per_page: perPage,
      total: sorted.length,
      from: sorted.length === 0 ? null : (page - 1) * perPage + 1,
      to: Math.min(sorted.length, page * perPage),
    }
  })

  on('GET', '/catalog/exchange/runs/(\\d+)', ({ params }) => ({ data: answer(findRun(params[0])) }))

  on('GET', '/catalog/exchange/runs/(\\d+)/errors', ({ params, query }) => {
    const run = findRun(params[0])
    const perPage = Math.min(500, Math.max(1, Number(query.get('per_page') ?? '100')))
    const sorted = [...run.errors].sort((a, b) => a.row - b.row)
    const last = Math.max(1, Math.ceil(sorted.length / perPage))
    const page = Math.min(Math.max(1, Number(query.get('page') ?? '1')), last)

    return {
      data: sorted.slice((page - 1) * perPage, page * perPage),
      current_page: page,
      last_page: last,
      per_page: perPage,
      total: sorted.length,
    }
  })

  /* Profiles: what a run would refuse, a profile refuses too. */
  const profileValues = (
    body: Record<string, unknown>,
    current: ProfileRecord | undefined,
    locale: string,
  ) => {
    const direction = (current?.direction ?? body.direction) as 'import' | 'export'
    const name = typeof body.name === 'string' ? body.name.trim() : (current?.name ?? '')

    if (name === '') {
      throw fail(422, 'The name field is required.', { name: ['The name field is required.'] })
    }

    if (direction !== 'import' && direction !== 'export') {
      throw fail(422, 'The direction is import or export.', {
        direction: ['The direction is import or export.'],
      })
    }

    const format = (body.format ?? current?.format ?? 'csv') === 'xlsx' ? 'xlsx' : 'csv'

    if (direction === 'import') {
      const options = checkOptions(
        (body.options ?? current?.options ?? {}) as Record<string, unknown>,
        locale,
      )

      return {
        name,
        direction,
        format,
        options,
        mapping:
          body.mapping !== undefined || !current
            ? checkMapping(
                (body.mapping ?? {}) as Record<string, unknown>,
                String(options.key),
                locale,
              )
            : current.mapping,
      } as const
    }

    const codes = Array.isArray(body.mapping)
      ? body.mapping.map(String)
      : ((current?.mapping as string[] | undefined) ?? [])

    for (const code of codes) {
      if (columnOf(code) === null) {
        const message = word(locale, 'exchange.errors.mapping-unknown', { code })

        throw fail(422, message, { mapping: [message] })
      }
    }

    return { name, direction, format, options: {}, mapping: codes } as const
  }

  on('GET', '/catalog/exchange/profiles', ({ query }) => {
    const direction = query.get('direction')

    return {
      data: profiles
        .filter((one) => !direction || one.direction === direction)
        .sort((a, b) => a.name.localeCompare(b.name)),
    }
  })

  on('GET', '/catalog/exchange/profiles/(\\d+)', ({ params }) => {
    const profile = findProfile(params[0])

    if (!profile) throw fail(404, 'No such profile.')

    return { data: profile }
  })

  on('POST', '/catalog/exchange/profiles', ({ body, locale }) => {
    const profile: ProfileRecord = {
      id: Math.max(0, ...profiles.map((one) => one.id)) + 1,
      ...profileValues(body, undefined, locale),
      last_run_id: null,
      created_at: now(),
      updated_at: now(),
    }

    profiles.push(profile)

    return { data: profile }
  })

  on('PUT', '/catalog/exchange/profiles/(\\d+)', ({ params, body, locale }) => {
    const profile = findProfile(params[0])

    if (!profile) throw fail(404, 'No such profile.')

    Object.assign(profile, profileValues(body, profile, locale), { updated_at: now() })

    return { data: profile }
  })

  on('DELETE', '/catalog/exchange/profiles/(\\d+)', ({ params }) => {
    const index = profiles.findIndex((one) => one.id === Number(params[0]))

    if (index < 0) throw fail(404, 'No such profile.')

    profiles.splice(index, 1)

    return null
  })

  /* The demo shop comes with a profile for its price list, so that the wizard has one to offer. */
  profiles.push({
    id: 1,
    name: 'Demo import',
    direction: 'import',
    format: 'csv',
    options: { ...DEFAULTS, create_missing: false },
    mapping: {
      sku: 'sku',
      name: 'name',
      'name@en': 'name@en',
      price: 'price',
      old_price: 'old_price',
      category: 'category',
      is_published: 'is_published',
      unit: 'unit',
    },
    last_run_id: null,
    created_at: now(),
    updated_at: now(),
  })
}

/**
 * The two answers that are files, not JSON: every error of a run as CSV, and a finished export —
 * under the panel's session and by the run's signed link. Before the router, as the uploads are.
 */
export function handleExchangeFiles(
  request: IncomingMessage,
  response: ServerResponse,
  url: URL,
): boolean {
  if ((request.method ?? 'GET').toUpperCase() !== 'GET') return false

  const errors = url.pathname.match(/^\/api\/cms\/catalog\/exchange\/runs\/(\d+)\/errors$/)

  if (errors !== null && url.searchParams.get('format') === 'csv') {
    const run = runs.find((one) => one.id === Number(errors[1]))

    if (!run) return notFound(response)

    const quote = (cell: string) =>
      /[",\r\n]/.test(cell) ? `"${cell.replaceAll('"', '""')}"` : cell
    const lines = ['row,column,value,message']

    for (const error of [...run.errors].sort((a, b) => a.row - b.row)) {
      lines.push(
        [String(error.row), error.column ?? '', error.value ?? '', error.message]
          .map(quote)
          .join(','),
      )
    }

    return send(
      response,
      `\uFEFF${lines.join('\r\n')}\r\n`,
      'text/csv; charset=UTF-8',
      `exchange-${run.id}-errors.csv`,
    )
  }

  const file =
    url.pathname.match(/^\/api\/cms\/catalog\/exchange\/runs\/(\d+)\/file$/) ??
    url.pathname.match(/^\/api\/cms\/catalog\/exchange\/download\/(\d+)\/[a-z0-9.-]+$/)

  if (file !== null) {
    const run = runs.find((one) => one.id === Number(file[1]))

    if (!run || run.direction !== 'export' || run.file === null) return notFound(response)

    // CSV whatever was asked for: the playground writes no XLSX, and says so by the name.
    return send(response, run.file, 'text/csv; charset=UTF-8', `catalog-export-${run.id}.csv`)
  }

  return false
}

function send(response: ServerResponse, body: string, type: string, name: string): true {
  response.statusCode = 200
  response.setHeader('Content-Type', type)
  response.setHeader('Content-Disposition', `attachment; filename="${name}"`)
  response.end(body)

  return true
}

function notFound(response: ServerResponse): true {
  response.statusCode = 404
  response.setHeader('Content-Type', 'application/json; charset=utf-8')
  response.end(JSON.stringify({ message: 'No such file.' }))

  return true
}
