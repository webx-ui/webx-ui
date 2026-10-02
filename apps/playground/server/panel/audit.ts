/*
 * «System → Audit» (WEBX_UI_MODULE_AUDIT.md) without a site to audit: one finished run with the
 * findings a freshly deployed project usually has — the case the module exists for first, a
 * development stand left in the content, published and in a draft — and a «Run the audit» that
 * walks through the stages on the clock and finishes with one of them fixed.
 *
 * The words come out of the PHP package's dictionary, the way the real API translates them.
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
type Fail = (status: number, message: string) => Error
type Line = (locale: string, namespace: string, path: string) => string

type Severity = 'error' | 'warning' | 'notice'

interface Cell {
  key: string
  type: string
}

interface Finding {
  check: string
  group: string
  severity: Severity
  url: string | null
  summary: [string, Record<string, string | number>]
  table?: { columns: Cell[]; rows: Record<string, unknown>[] }
}

const BASE = 'https://shop.example.com'
const STAGE_MS = 1500
const STAGES = ['probes', 'database', 'analyse'] as const

const ago = (minutes: number) => new Date(Date.now() - minutes * 60_000).toISOString()

const devTable = (rows: Record<string, unknown>[]) => ({
  columns: [
    { key: 'record', type: 'text' },
    { key: 'field', type: 'text' },
    { key: 'locale', type: 'text' },
    { key: 'url', type: 'url' },
    { key: 'published', type: 'bool' },
    { key: 'edit', type: 'edit' },
  ],
  rows,
})

const FINDINGS: Finding[] = [
  {
    check: 'hosts.dev_content',
    group: 'hosts',
    severity: 'error',
    url: 'https://dev.shop.example.com/storage/hero.jpg',
    summary: [
      'dev-content',
      { hosts: 'dev.shop.example.com', record: 'Delivery', field: 'blocks', count: 2 },
    ],
    table: devTable([
      {
        record: 'Delivery',
        field: 'blocks',
        locale: null,
        url: 'https://dev.shop.example.com/storage/hero.jpg',
        published: true,
        edit: '/pages/2',
      },
      {
        record: 'Delivery',
        field: 'blocks',
        locale: null,
        url: 'https://dev.shop.example.com/sale',
        published: true,
        edit: '/pages/2',
      },
    ]),
  },
  {
    check: 'hosts.dev_content',
    group: 'hosts',
    severity: 'error',
    url: 'http://localhost:8000/storage/logo.svg',
    summary: [
      'dev-content',
      { hosts: 'localhost', record: 'Header', field: 'draft.blocks', count: 1 },
    ],
    table: devTable([
      {
        record: 'Header',
        field: 'draft.blocks',
        locale: null,
        url: 'http://localhost:8000/storage/logo.svg',
        published: false,
        edit: '/regions/header',
      },
    ]),
  },
  {
    check: 'host.mirror',
    group: 'host',
    severity: 'error',
    url: 'https://www.shop.example.com/',
    summary: ['mirror-answers', { host: 'www.shop.example.com' }],
  },
  {
    check: 'host.https',
    group: 'host',
    severity: 'warning',
    url: 'http://shop.example.com/',
    summary: ['http-chain', { steps: 2 }],
    table: {
      columns: [
        { key: 'url', type: 'url' },
        { key: 'status', type: 'status' },
        { key: 'location', type: 'url' },
      ],
      rows: [
        { url: 'http://shop.example.com/', status: 302, location: 'http://www.shop.example.com/' },
        { url: 'http://www.shop.example.com/', status: 301, location: `${BASE}/` },
      ],
    },
  },
  {
    check: 'host.index_files',
    group: 'host',
    severity: 'error',
    url: `${BASE}/index.php`,
    summary: ['index-file', { path: '/index.php' }],
  },
  {
    check: 'host.security_headers',
    group: 'host',
    severity: 'notice',
    url: `${BASE}/`,
    summary: ['security-headers', { headers: 'Referrer-Policy, X-Frame-Options' }],
    table: {
      columns: [
        { key: 'header', type: 'text' },
        { key: 'value', type: 'missing' },
      ],
      rows: [
        { header: 'Referrer-Policy', value: null },
        { header: 'X-Frame-Options', value: null },
      ],
    },
  },
  {
    check: 'config.queue',
    group: 'config',
    severity: 'warning',
    url: null,
    summary: ['queue-sync', { connection: 'sync' }],
  },
]

interface Run {
  id: number
  status: 'queued' | 'running' | 'done' | 'failed' | 'cancelled'
  scope: 'full' | 'quick'
  startedAt: number
  findings: Finding[]
  createdAt: string
  finishedAt: string | null
}

const runs: Run[] = [
  {
    id: 1,
    status: 'done',
    scope: 'quick',
    startedAt: Date.now() - 3 * 86_400_000,
    findings: [
      ...FINDINGS,
      {
        ...FINDINGS[4]!,
        url: `${BASE}/index.html`,
        summary: ['index-file', { path: '/index.html' }],
      },
    ],
    createdAt: ago(3 * 1440),
    finishedAt: ago(3 * 1440 - 1),
  },
  {
    id: 2,
    status: 'done',
    scope: 'quick',
    startedAt: Date.now() - 3_600_000,
    findings: FINDINGS,
    createdAt: ago(61),
    finishedAt: ago(60),
  },
]

const fingerprint = (finding: Finding) =>
  `${finding.check}|${finding.url ?? ''}|${JSON.stringify(finding.summary[1])}`

const WEIGHTS: Record<Severity, number> = { error: 10, warning: 3, notice: 1 }

/* The checks of the real module and their worst severity — what the health weighs. */
const CHECKS: Record<string, Severity> = {
  'config.debug': 'error',
  'config.env': 'warning',
  'config.app_url': 'error',
  'config.queue': 'warning',
  'config.mail': 'error',
  'config.schedule': 'warning',
  'config.storage_link': 'error',
  'config.site_gate': 'notice',
  'host.mirror': 'error',
  'host.https': 'error',
  'host.tls': 'error',
  'host.hsts': 'notice',
  'host.index_files': 'error',
  'host.slashes': 'warning',
  'host.trailing_slash': 'warning',
  'host.case': 'warning',
  'host.soft_404': 'error',
  'host.404_page': 'notice',
  'host.compression': 'warning',
  'host.security_headers': 'notice',
  'host.server_leak': 'notice',
  'host.static_cache': 'warning',
  'hosts.dev_content': 'error',
}

function previous(run: Run): Run | undefined {
  return runs.filter((other) => other.status === 'done' && other.id < run.id).at(-1)
}

function state(run: Run, finding: Finding): 'new' | 'persisting' {
  const before = previous(run)

  return before?.findings.some((other) => fingerprint(other) === fingerprint(finding))
    ? 'persisting'
    : 'new'
}

function counts(run: Run) {
  const severity: Record<Severity, number> = { error: 0, warning: 0, notice: 0 }
  const groups: Record<string, Record<Severity, number>> = {}
  const failed: Record<string, Severity> = {}

  for (const finding of run.findings) {
    severity[finding.severity]++
    groups[finding.group] ??= { error: 0, warning: 0, notice: 0 }
    groups[finding.group]![finding.severity]++

    if (
      WEIGHTS[finding.severity] > WEIGHTS[failed[finding.check] ?? 'notice'] ||
      !failed[finding.check]
    ) {
      failed[finding.check] = finding.severity
    }
  }

  const total = Object.values(CHECKS).reduce((sum, worst) => sum + WEIGHTS[worst], 0)
  const lost = Object.values(failed).reduce((sum, worst) => sum + WEIGHTS[worst], 0)
  const before = previous(run)
  const now = new Set(run.findings.map(fingerprint))

  return {
    severity,
    groups,
    checks: Object.keys(CHECKS),
    failed,
    health: Math.round((100 * (total - lost)) / total),
    new: run.findings.filter((finding) => state(run, finding) === 'new').length,
    fixed: before ? before.findings.filter((finding) => !now.has(fingerprint(finding))).length : 0,
    previous_id: before?.id ?? null,
    sources: { searched: ['pages', 'regions'], missing: ['articles'] },
  }
}

/* A run started from the page walks through the stages on the clock. */
function advance(run: Run): void {
  if (run.status !== 'queued' && run.status !== 'running') return

  const elapsed = Date.now() - run.startedAt

  if (elapsed < STAGE_MS) {
    run.status = 'queued'
  } else if (elapsed < STAGE_MS * (STAGES.length + 1)) {
    run.status = 'running'
  } else {
    run.status = 'done'
    run.finishedAt = new Date().toISOString()
  }
}

function stage(run: Run): string | null {
  const index = Math.floor((Date.now() - run.startedAt) / STAGE_MS) - 1

  return run.status === 'running'
    ? (STAGES[Math.max(0, Math.min(index, STAGES.length - 1))] ?? null)
    : 'probes'
}

function resource(run: Run) {
  advance(run)

  return {
    id: run.id,
    status: run.status,
    scope: run.scope,
    base_url: BASE,
    resolve_to: null,
    progress: { stage: run.status === 'done' ? 'analyse' : stage(run), done: [], checks: 23 },
    counts: run.status === 'done' ? counts(run) : null,
    started_by: '1',
    created_at: run.createdAt,
    started_at: run.createdAt,
    finished_at: run.finishedAt,
    error: null,
  }
}

function fill(text: string, params: Record<string, string | number>): string {
  return Object.entries(params).reduce(
    (line, [key, value]) => line.replaceAll(`:${key}`, String(value)),
    text,
  )
}

export function registerAudit(on: On, fail: Fail, line: Line): void {
  const find = (id: string) => {
    const run = runs.find((candidate) => candidate.id === Number(id))

    if (!run) throw fail(404, 'Not found.')

    return run
  }

  on('GET', '/audit/runs/latest', () => {
    runs.forEach(advance)
    const active = runs.filter((run) => run.status === 'queued' || run.status === 'running').at(-1)
    const done = runs.filter((run) => run.status === 'done').at(-1)

    return {
      data: {
        active: active ? resource(active) : null,
        done: done ? resource(done) : null,
        last: null,
        queue: { sync: false },
      },
    }
  })

  on('GET', '/audit/runs/(\\d+)', ({ params }) => ({ data: resource(find(params[0]!)) }))

  on('POST', '/audit/runs', ({ body }) => {
    if (runs.some((run) => run.status === 'queued' || run.status === 'running')) {
      throw fail(409, 'An audit is already running.')
    }

    const run: Run = {
      id: runs.length + 1,
      status: 'queued',
      scope: body.scope === 'full' ? 'full' : 'quick',
      startedAt: Date.now(),
      // Somebody replaced the stand address in the header's draft.
      findings: FINDINGS.filter(
        (finding) => finding.url !== 'http://localhost:8000/storage/logo.svg',
      ),
      createdAt: new Date().toISOString(),
      finishedAt: null,
    }

    runs.push(run)

    return { data: resource(run) }
  })

  on('POST', '/audit/runs/(\\d+)/cancel', ({ params }) => {
    const run = find(params[0]!)

    if (run.status === 'queued' || run.status === 'running') {
      run.status = 'cancelled'
      run.finishedAt = new Date().toISOString()
    }

    return { data: resource(run) }
  })

  const filtered = (run: Run, query: URLSearchParams) =>
    run.findings.filter(
      (finding) =>
        (!query.get('severity') || finding.severity === query.get('severity')) &&
        (!query.get('group') || finding.group === query.get('group')) &&
        (!query.get('state') || state(run, finding) === query.get('state')) &&
        (!query.get('check') || finding.check === query.get('check')),
    )

  on('GET', '/audit/runs/(\\d+)/checks', ({ params, query, locale }) => {
    const run = find(params[0]!)
    const rows = new Map<
      string,
      { id: string; group: string; severity: Severity; count: number; new: number }
    >()

    for (const finding of filtered(run, query)) {
      const row = rows.get(finding.check) ?? {
        id: finding.check,
        group: finding.group,
        severity: 'notice' as Severity,
        count: 0,
        new: 0,
      }
      row.count++
      row.new += state(run, finding) === 'new' ? 1 : 0
      row.severity =
        WEIGHTS[finding.severity] > WEIGHTS[row.severity] ? finding.severity : row.severity
      rows.set(finding.check, row)
    }

    return {
      data: [...rows.values()]
        .map((row) => ({
          ...row,
          title: line(locale, 'webx-audit', `checks.${row.id}.title`),
          found: line(locale, 'webx-audit', `checks.${row.id}.found`),
          why: line(locale, 'webx-audit', `checks.${row.id}.why`),
          fix: line(locale, 'webx-audit', `checks.${row.id}.fix`),
        }))
        .sort((a, b) => WEIGHTS[b.severity] - WEIGHTS[a.severity] || b.count - a.count),
    }
  })

  on('GET', '/audit/runs/(\\d+)/issues', ({ params, query, locale }) => {
    const run = find(params[0]!)
    const rows = filtered(run, query)

    return {
      data: rows.map((finding, index) => ({
        id: index + 1,
        check: finding.check,
        severity: finding.severity,
        url: finding.url,
        state: state(run, finding),
        ignored: false,
        details: {
          summary: fill(
            line(locale, 'webx-audit', `details.${finding.summary[0]}`),
            finding.summary[1],
          ),
          table: finding.table
            ? {
                columns: finding.table.columns.map((column) => ({
                  ...column,
                  label: line(locale, 'webx-audit', `details.column-${column.key}`),
                })),
                rows: finding.table.rows,
              }
            : null,
        },
      })),
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 50,
        total: rows.length,
        from: rows.length ? 1 : null,
        to: rows.length,
      },
    }
  })
}
