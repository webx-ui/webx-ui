import type { IncomingMessage, ServerResponse } from 'node:http'

/**
 * Interlinking of `module-seo` (§18.4 of WEBX_UI_MODULE_SEO.md) for the playground: donors and
 * their blocks, the import with its preview, the export, the heading in bulk, and the addresses
 * an acceptor field suggests.
 *
 * What is real: the checks of a link — another host, a link to itself, a duplicate, an address
 * that redirects (replaced, with a warning), an address nothing answers (an error in a file, a
 * broken link in a form) — and the grouping of a file by donor in the order of its lines. What
 * is not: the site is a fixed list of addresses below rather than the routing registry, a donor
 * is never bound to an entity, and XLSX is refused both ways (CSV is enough to look at the
 * screens; XLSX is checked on `webx-cms.local`).
 */

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
type Fail = (status: number, message: string, errors?: Record<string, string[]>) => Error
type Line = (locale: string, namespace: string, path: string) => string

interface Item {
  id: number
  path: string
  anchor: string
}

interface Block {
  id: number
  path: string
  heading: string | null
  is_active: boolean
  items: Item[]
  updated_at: string
}

interface Problem {
  line: number
  field: string
  code: string
  level: 'error' | 'warning'
  message: string
}

/** The site, as far as interlinking can see it: an address and what answers there. */
const ADDRESSES: Record<string, string> = {
  '/': 'page',
  '/about': 'page',
  '/contacts': 'page',
  '/blog': 'page',
  '/catalog': 'catalog.category',
  '/catalog/phones': 'catalog.category',
  '/catalog/phones/apple': 'catalog.landing',
  '/catalog/phones/samsung': 'catalog.landing',
  '/catalog/laptops': 'catalog.category',
  '/catalog/laptops/apple': 'catalog.landing',
  '/catalog/tech': 'catalog.category',
  '/catalog/tech/kettles': 'catalog.category',
  '/catalog/tech/vacuums': 'catalog.category',
  '/catalog/tech/irons': 'catalog.category',
  '/services': 'page',
  '/faq': 'page',
  '/en/catalog/phones': 'catalog.category',
  '/en/catalog/laptops': 'catalog.category',
}

/** Old addresses that redirect: a brief written before a rename still names them. */
const REDIRECTS: Record<string, string> = {
  '/catalog/smartphones': '/catalog/phones',
  '/catalog/notebooks': '/catalog/laptops',
}

const OWN_HOSTS = new Set(['localhost:5174', 'webx-demo.test'])

let nextBlock = 1
let nextItem = 1

const stamp = () => new Date().toISOString()

const blocks: Block[] = []

function seed(path: string, heading: string | null, links: [string, string][]): void {
  blocks.push({
    id: nextBlock++,
    path,
    heading,
    is_active: true,
    items: links.map(([acceptor, anchor]) => ({ id: nextItem++, path: acceptor, anchor })),
    updated_at: stamp(),
  })
}

seed('/catalog/phones', null, [
  ['/catalog/phones/apple', 'iPhone'],
  ['/catalog/phones/samsung', 'Samsung phones'],
  ['/catalog/laptops', 'Laptops'],
])
seed('/catalog/laptops', 'Also worth a look', [
  ['/catalog/laptops/apple', 'MacBook'],
  ['/catalog/phones', 'Phones'],
  // Nothing answers here any more: drawn as broken, kept until somebody fixes it.
  ['/catalog/tablets', 'Tablets'],
])
seed('/catalog/tech/kettles', 'Similar appliances', [
  ['/catalog/tech/vacuums', 'Vacuum cleaners'],
  ['/catalog/tech/irons', 'Irons'],
])
seed('/catalog/tech/vacuums', 'Similar appliances', [
  ['/catalog/tech/kettles', 'Kettles'],
  ['/catalog/tech/irons', 'Irons'],
])

/** `/en/…` is English; everything else is the site's default language. */
const localeOf = (path: string): string => (/^\/en(\/|$)/.test(path) ? 'en' : 'ru')

/**
 * An address as the server keeps it: no host of ours, a slash in front, none behind. `null` for
 * another site's host — interlinking is internal.
 */
function normalise(raw: string): string | null {
  let value = raw.trim()
  const absolute = /^https?:\/\/([^/]+)(.*)$/i.exec(value)

  if (absolute !== null) {
    if (!OWN_HOSTS.has(absolute[1]!.toLowerCase())) return null

    value = absolute[2] || '/'
  }

  if (!value.startsWith('/')) value = `/${value}`
  if (value.length > 1) value = value.replace(/\/+$/, '')

  return value
}

const broken = (path: string): boolean => ADDRESSES[path] === undefined

function describeTarget(path: string) {
  return {
    url: path,
    saved_url: path,
    locale: localeOf(path),
    entity_type: ADDRESSES[path] ?? null,
    entity_id: null,
    broken: broken(path),
  }
}

function describe(block: Block, withItems = true) {
  const items = block.items.map((item, position) => ({
    id: item.id,
    acceptor: describeTarget(item.path),
    anchor: item.anchor,
    position,
  }))

  return {
    id: block.id,
    donor: describeTarget(block.path),
    heading: block.heading,
    is_active: block.is_active,
    links_count: items.length,
    broken_count: items.filter((item) => item.acceptor.broken).length,
    updated_at: block.updated_at,
    ...(withItems ? { items } : {}),
  }
}

/** The words of one problem, in the panel's language, with Laravel's `:placeholders` filled. */
function say(line: Line, locale: string, code: string, replace: Record<string, string> = {}) {
  let text = line(locale, 'webx-seo', `links.${code}`)

  for (const [key, value] of Object.entries(replace)) text = text.replaceAll(`:${key}`, value)

  return text
}

interface Plan {
  donor: string | null
  items: { path: string; anchor: string }[]
  problems: Problem[]
}

/**
 * The checks every door of writing shares. `strict` is the import's: an address nothing answers
 * is an error there, while a form keeps it and draws it broken (§18.8).
 */
function plan(
  line: Line,
  locale: string,
  donorRaw: string,
  items: { acceptor: string; anchor: string; line: number }[],
  strict: boolean,
  donorLine: number,
  into: Block | null = null,
): Plan {
  const problems: Problem[] = []
  const problem = (
    at: number,
    field: string,
    code: string,
    level: 'error' | 'warning',
    replace = {},
  ) => problems.push({ line: at, field, code, level, message: say(line, locale, code, replace) })

  let donor: string | null = null

  if (donorRaw.trim() === '') {
    problem(donorLine, 'donor', 'empty', 'error')
  } else {
    donor = normalise(donorRaw)

    if (donor === null) {
      problem(donorLine, 'donor', 'foreign-host', 'error')
    } else if (REDIRECTS[donor] !== undefined) {
      problem(donorLine, 'donor', 'redirected', 'warning', { from: donor, to: REDIRECTS[donor]! })
      donor = REDIRECTS[donor]!
    } else if (strict && broken(donor)) {
      problem(donorLine, 'donor', 'not-found', 'error')
      donor = null
    }
  }

  const kept: { path: string; anchor: string }[] = (into?.items ?? []).map((item) => ({
    path: item.path,
    anchor: item.anchor,
  }))
  const fresh: { path: string; anchor: string }[] = []

  for (const item of items) {
    const anchor = item.anchor.trim()
    let path = item.acceptor.trim() === '' ? null : normalise(item.acceptor)

    if (item.acceptor.trim() === '' || anchor === '') {
      problem(item.line, item.acceptor.trim() === '' ? 'acceptor' : 'anchor', 'empty', 'error')
      continue
    }

    if (anchor.length > 255) {
      problem(item.line, 'anchor', 'anchor-long', 'error')
      continue
    }

    if (path === null) {
      problem(item.line, 'acceptor', 'foreign-host', 'error')
      continue
    }

    if (REDIRECTS[path] !== undefined) {
      problem(item.line, 'acceptor', 'redirected', 'warning', { from: path, to: REDIRECTS[path]! })
      path = REDIRECTS[path]!
    }

    if (strict && broken(path)) {
      problem(item.line, 'acceptor', 'not-found', 'error')
      continue
    }

    if (donor !== null && path === donor) {
      problem(item.line, 'acceptor', 'self', 'error')
      continue
    }

    if ([...kept, ...fresh].some((known) => known.path === path)) {
      problem(item.line, 'acceptor', 'duplicate', 'error')
      continue
    }

    fresh.push({ path, anchor })
  }

  return { donor, items: [...kept, ...fresh], problems }
}

function save(
  target: Block | null,
  donor: string,
  items: Plan['items'],
  values: Partial<Block>,
): Block {
  const block: Block = target ?? {
    id: nextBlock++,
    path: donor,
    heading: null,
    is_active: true,
    items: [],
    updated_at: stamp(),
  }

  block.path = donor
  block.items = items.map((item) => ({ id: nextItem++, path: item.path, anchor: item.anchor }))
  Object.assign(block, values)
  block.updated_at = stamp()

  if (target === null) blocks.push(block)

  return block
}

/** Segments, not characters: `/catalog/tech/` takes `/catalog/tech/x`, not `/catalog/tech-2`. */
function under(path: string, prefix: string): boolean {
  const head = normalise(prefix)

  if (head === null) return false
  if (head === '/') return true

  return path === head || path.startsWith(`${head}/`)
}

export function registerSeoLinks(on: On, fail: Fail, line: Line): void {
  const find = (id: string): Block => {
    const block = blocks.find((candidate) => candidate.id === Number(id))

    if (!block) throw fail(404, 'Not found.')

    return block
  }

  on('GET', '/seo/links', ({ query }) => {
    const term = (query.get('q') ?? '').trim().toLowerCase()
    const onlyBroken = ['1', 'true'].includes(query.get('broken') ?? '')
    const perPage = Number(query.get('per_page') ?? 25)
    const page = Math.max(1, Number(query.get('page') ?? 1))

    const found = blocks
      .filter(
        (block) =>
          term === '' ||
          block.path.toLowerCase().includes(term) ||
          block.items.some((item) => item.anchor.toLowerCase().includes(term)),
      )
      .filter((block) => !onlyBroken || block.items.some((item) => broken(item.path)))

    const rows = found.slice((page - 1) * perPage, page * perPage)
    const from = rows.length > 0 ? (page - 1) * perPage + 1 : null

    return {
      data: rows.map((block) => describe(block, false)),
      meta: {
        current_page: page,
        last_page: Math.max(1, Math.ceil(found.length / perPage)),
        per_page: perPage,
        total: found.length,
        from,
        to: from === null ? null : from + rows.length - 1,
      },
    }
  })

  on('GET', '/seo/links/addresses', ({ query }) => {
    const term = (query.get('q') ?? '')
      .trim()
      .replace(/^\/+|\/+$/g, '')
      .toLowerCase()
    const locale = query.get('locale')

    return {
      data: Object.entries(ADDRESSES)
        .filter(([path]) => locale === null || localeOf(path) === locale)
        .filter(([path]) => path.toLowerCase().includes(term))
        .sort(([a], [b]) => a.length - b.length)
        .slice(0, 20)
        .map(([path, type]) => ({
          url: path,
          locale: localeOf(path),
          entity_type: type,
          entity_id: null,
        })),
    }
  })

  on('GET', '/seo/links/(\\d+)', ({ params }) => ({ data: describe(find(params[0]!)) }))

  const write = (body: Record<string, unknown>, locale: string, target: Block | null) => {
    const items = (Array.isArray(body.items) ? body.items : []).map((item, index) => ({
      acceptor: String((item as { acceptor?: unknown }).acceptor ?? ''),
      anchor: String((item as { anchor?: unknown }).anchor ?? ''),
      line: index,
    }))
    const planned = plan(line, locale, String(body.donor ?? ''), items, false, 0)
    const errors: Record<string, string[]> = {}

    for (const problem of planned.problems) {
      if (problem.level !== 'error') continue

      const key = problem.field === 'donor' ? 'donor' : `items.${problem.line}.${problem.field}`
      ;(errors[key] ??= []).push(problem.message)
    }

    if (
      planned.donor !== null &&
      blocks.some((block) => block.path === planned.donor && block.id !== target?.id)
    ) {
      ;(errors.donor ??= []).push(say(line, locale, 'donor-taken'))
    }

    if (Object.keys(errors).length > 0) {
      throw fail(422, 'The given data was invalid.', errors)
    }

    const heading =
      typeof body.heading === 'string' && body.heading.trim() !== '' ? body.heading.trim() : null
    const saved = save(target, planned.donor!, planned.items, {
      heading,
      is_active: body.is_active !== false,
    })

    return {
      data: {
        ...describe(saved),
        warnings: planned.problems.filter((problem) => problem.level === 'warning'),
      },
    }
  }

  on('POST', '/seo/links', ({ body, locale }) => write(body, locale, null))
  on('PUT', '/seo/links/(\\d+)', ({ params, body, locale }) =>
    write(body, locale, find(params[0]!)),
  )
  on('DELETE', '/seo/links/(\\d+)', ({ params }) => {
    const block = find(params[0]!)

    blocks.splice(blocks.indexOf(block), 1)

    return {}
  })

  on('POST', '/seo/links/heading', ({ body }) => {
    const ids = Array.isArray(body.ids) ? body.ids.map(Number) : null
    const prefix = typeof body.prefix === 'string' ? body.prefix : ''

    if ((ids === null || ids.length === 0) && prefix.trim() === '') {
      throw fail(422, 'The given data was invalid.', { prefix: ['Type the start of an address.'] })
    }

    const chosen =
      ids !== null && ids.length > 0
        ? blocks.filter((block) => ids.includes(block.id))
        : blocks.filter((block) => under(block.path, prefix))
    const heading =
      typeof body.heading === 'string' && body.heading.trim() !== ''
        ? body.heading.trim().slice(0, 255)
        : null
    const dryRun = body.dry_run !== false && body.dry_run !== 0 && body.dry_run !== '0'

    if (!dryRun) {
      for (const block of chosen) {
        block.heading = heading
        block.updated_at = stamp()
      }
    }

    return {
      data: {
        ok: true,
        applied: !dryRun,
        count: chosen.length,
        heading,
        donors: chosen.map((block) => block.path),
      },
    }
  })
}

/* ------------------------------------------------------------------ files ------------------ */

const HEADERS: Record<string, 'donor' | 'acceptor' | 'anchor' | 'heading'> = {
  donor: 'donor',
  донор: 'donor',
  acceptor: 'acceptor',
  акцептор: 'acceptor',
  anchor: 'anchor',
  анкор: 'anchor',
  heading: 'heading',
  заголовок: 'heading',
}

/** CSV the way `LinkSpreadsheet` reads it: a BOM dropped, the separator of the first line, quotes. */
function parseCsv(text: string): string[][] {
  const source = text.replace(/^\uFEFF/, '')
  const first = source.split(/\r?\n/, 1)[0] ?? ''
  const separator = [';', '\t', ','].reduce((best, candidate) =>
    first.split(candidate).length > first.split(best).length ? candidate : best,
  )
  const rows: string[][] = []
  let row: string[] = []
  let cell = ''
  let quoted = false

  for (let index = 0; index < source.length; index++) {
    const char = source[index]!

    if (quoted) {
      if (char === '"' && source[index + 1] === '"') {
        cell += '"'
        index++
      } else if (char === '"') {
        quoted = false
      } else {
        cell += char
      }
    } else if (char === '"') {
      quoted = true
    } else if (char === separator) {
      row.push(cell)
      cell = ''
    } else if (char === '\n' || char === '\r') {
      if (char === '\r' && source[index + 1] === '\n') index++
      row.push(cell)
      rows.push(row)
      row = []
      cell = ''
    } else {
      cell += char
    }
  }

  if (cell !== '' || row.length > 0) {
    row.push(cell)
    rows.push(row)
  }

  return rows
}

function runImport(line: Line, locale: string, text: string, mode: string, dryRun: boolean) {
  const rows = parseCsv(text)
  const head = (rows[0] ?? []).map((cell) => HEADERS[cell.trim().toLowerCase()])
  const named = head.some((column) => column !== undefined)
  const columns = named ? head : (['donor', 'acceptor', 'anchor', 'heading'] as const)
  const append = mode === 'append'

  const groups = new Map<
    string,
    {
      donor: string
      line: number
      heading: string | null
      items: { acceptor: string; anchor: string; line: number }[]
    }
  >()
  const problems: Problem[] = []

  rows.forEach((cells, index) => {
    if (named && index === 0) return
    if (cells.every((cell) => cell.trim() === '')) return

    const lineNumber = index + 1
    const value = (name: string) => cells[columns.indexOf(name as never)]?.trim() ?? ''
    const donor = value('donor')
    const key = normalise(donor) ?? donor

    if (!groups.has(key)) groups.set(key, { donor, line: lineNumber, heading: null, items: [] })

    const group = groups.get(key)!

    if (group.heading === null && value('heading') !== '') group.heading = value('heading')

    group.items.push({ acceptor: value('acceptor'), anchor: value('anchor'), line: lineNumber })
  })

  const counts = { donors: 0, links: 0, created: 0, replaced: 0, appended: 0 }
  const report: { donor: string; links: number; action: string; heading: string | null }[] = []

  for (const group of groups.values()) {
    let planned = plan(line, locale, group.donor, group.items, true, group.line)
    const existing =
      planned.donor === null ? null : (blocks.find((block) => block.path === planned.donor) ?? null)

    if (append && existing !== null) {
      planned = plan(line, locale, group.donor, group.items, true, group.line, existing)
    }

    problems.push(...planned.problems)

    if (planned.donor === null || planned.items.length === 0) continue

    const action = existing === null ? 'create' : append ? 'append' : 'replace'

    counts.donors++
    counts.links += planned.items.length
    counts[action === 'create' ? 'created' : action === 'append' ? 'appended' : 'replaced']++
    report.push({
      donor: planned.donor,
      links: planned.items.length,
      action,
      heading: group.heading,
    })

    if (!dryRun) {
      save(
        existing,
        planned.donor,
        planned.items,
        group.heading === null ? {} : { heading: group.heading },
      )
    }
  }

  problems.sort((a, b) => a.line - b.line)

  return {
    ok: true,
    applied: !dryRun,
    mode: append ? 'append' : 'replace',
    ...counts,
    errors: problems.filter((problem) => problem.level === 'error').length,
    problems,
    blocks: report,
  }
}

function json(response: ServerResponse, status: number, body: unknown): true {
  response.statusCode = status
  response.setHeader('Content-Type', 'application/json; charset=utf-8')
  response.end(JSON.stringify(body))

  return true
}

async function raw(request: IncomingMessage): Promise<Buffer> {
  const chunks: Buffer[] = []

  for await (const chunk of request) chunks.push(chunk as Buffer)

  return Buffer.concat(chunks)
}

/** The parts of a multipart body by name: text for fields, the file's name and bytes for a file. */
function multipart(
  body: Buffer,
  boundary: string,
): Map<string, { filename: string | null; data: Buffer }> {
  const parts = new Map<string, { filename: string | null; data: Buffer }>()
  const marker = Buffer.from(`--${boundary}`)
  let start = body.indexOf(marker)

  while (start !== -1) {
    const next = body.indexOf(marker, start + marker.length)

    if (next === -1) break

    const part = body.subarray(start + marker.length + 2, next - 2)
    const split = part.indexOf('\r\n\r\n')

    if (split !== -1) {
      const headers = part.subarray(0, split).toString('utf8')
      const name = /name="([^"]*)"/.exec(headers)?.[1]
      const filename = /filename="([^"]*)"/.exec(headers)?.[1] ?? null

      if (name !== undefined) parts.set(name, { filename, data: part.subarray(split + 4) })
    }

    start = next
  }

  return parts
}

/** The two requests that are not JSON: the file going in and the file coming out. */
export function handleSeoLinkFiles(
  request: IncomingMessage,
  response: ServerResponse,
  url: URL,
  line: Line,
): boolean {
  const method = (request.method ?? 'GET').toUpperCase()

  if (url.pathname === '/api/cms/seo/links/export' && method === 'GET') {
    if (url.searchParams.get('format') === 'xlsx') {
      return json(response, 422, {
        message: 'The playground writes CSV only; XLSX is checked on a real site.',
      })
    }

    const quote = (cell: string) =>
      /[",\r\n]/.test(cell) ? `"${cell.replaceAll('"', '""')}"` : cell
    const lines = ['donor,acceptor,anchor,heading']

    for (const block of blocks) {
      block.items.forEach((item, index) =>
        lines.push(
          [block.path, item.path, item.anchor, index === 0 ? (block.heading ?? '') : '']
            .map(quote)
            .join(','),
        ),
      )
    }

    response.statusCode = 200
    response.setHeader('Content-Type', 'text/csv; charset=UTF-8')
    response.setHeader('Content-Disposition', 'attachment; filename="interlinking.csv"')
    response.end(`\uFEFF${lines.join('\r\n')}\r\n`)

    return true
  }

  if (url.pathname === '/api/cms/seo/links/import' && method === 'POST') {
    void (async () => {
      const header = request.headers['x-webx-locale']
      const locale = typeof header === 'string' && header !== '' ? header : 'en'
      const boundary = /boundary=(?:"([^"]+)"|([^;]+))/.exec(request.headers['content-type'] ?? '')

      if (boundary === null) {
        json(response, 422, {
          message: 'Expected a multipart body.',
          errors: { file: ['Expected a file.'] },
        })

        return
      }

      const parts = multipart(await raw(request), boundary[1] ?? boundary[2]!)
      const file = parts.get('file')

      if (!file || !/\.(csv|txt)$/i.test(file.filename ?? '')) {
        json(response, 422, {
          message: 'The given data was invalid.',
          errors: { file: [line(locale, 'webx-seo', 'links.unreadable')] },
        })

        return
      }

      const mode = parts.get('mode')?.data.toString('utf8') ?? 'replace'
      const dry = parts.get('dry_run')?.data.toString('utf8') ?? '1'

      json(response, 200, {
        data: runImport(
          line,
          locale,
          file.data.toString('utf8'),
          mode,
          !['0', 'false'].includes(dry),
        ),
      })
    })()

    return true
  }

  return false
}
