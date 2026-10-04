/*
 * «System → Audit» (WEBX_UI_MODULE_AUDIT.md) without a site to audit: one finished run with the
 * findings a freshly deployed project usually has — the case the module exists for first, a
 * development stand left in the content, published and in a draft — and a «Run the audit» that
 * walks through the stages on the clock and finishes with one of them fixed. The last run is a
 * full one: a dozen crawled pages with their links, for the pages screen and the page's card.
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
  /** The crawled page it was found on. */
  page?: number
  summary: [string, Record<string, string | number>]
  table?: { columns: Cell[]; rows: Record<string, unknown>[] }
}

const BASE = 'https://shop.example.com'

/* The fixes the modules register on the server, by the check they close (§7). */
const FIXES: Record<string, string[]> = {
  'hosts.dev_content': ['audit.replace-host'],
  'host.mirror': ['seo.normalise-host'],
  'host.https': ['seo.normalise-https'],
  'host.index_files': ['seo.normalise-index'],
  'redirects.chain': ['seo.collapse-chain'],
}

/* What was pressed, by finding: the finding stays until a run says it is gone, as on the server. */
const pressed = new Map<string, string>()

/* The hiding rules (decision 9) and the section's settings, kept as long as the dev server runs. */
interface Rule {
  id: number
  check: string
  pattern: string
  reason: string
  created_by: string | null
  created_at: string
}

const rules: Rule[] = []
const settings: Record<string, unknown> = { 'audit.other-hosts': 'dev.shop.example.com' }

/* `*` a stretch without a slash, `**` one with them; the path unless the mask names a scheme. */
function masked(pattern: string, url: string | null): boolean {
  if (pattern.trim() === '' || pattern === '**') return true
  if (!url) return false

  let subject = url

  if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(pattern)) {
    try {
      subject = new URL(url).pathname
    } catch {
      return false
    }
  }

  const quote = (text: string) => text.replace(/[.+?^${}()|[\]\\]/g, '\\$&')
  const body = pattern
    .split('**')
    .map((part) => part.split('*').map(quote).join('[^/]*'))
    .join('.*')

  return new RegExp(`^${body}$`).test(subject)
}

const ruleOf = (finding: Finding) =>
  rules.find((rule) => rule.check === finding.check && masked(rule.pattern, finding.url))

const shown = (run: Run) => run.findings.filter((finding) => !ruleOf(finding))
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
    check: 'hosts.dev_page',
    group: 'hosts',
    severity: 'error',
    url: `${BASE}/`,
    page: 1,
    summary: ['dev-page', { count: 1 }],
    table: {
      columns: [
        { key: 'url', type: 'url' },
        { key: 'kind', type: 'text' },
        { key: 'anchor', type: 'text' },
      ],
      rows: [{ url: 'https://dev.shop.example.com/storage/hero.jpg', kind: 'img', anchor: null }],
    },
  },
  {
    check: 'images.alt',
    group: 'images',
    severity: 'warning',
    url: `${BASE}/`,
    page: 1,
    summary: ['images-alt', { count: 2 }],
    table: {
      columns: [{ key: 'markup', type: 'code' }],
      rows: [
        { markup: '<img src="/storage/banners/spring.jpg" class="hero__picture" loading="lazy">' },
        { markup: '<img src="/storage/brands/acme.svg" width="120" height="40">' },
      ],
    },
  },
  {
    check: 'links.broken',
    group: 'links',
    severity: 'error',
    url: `${BASE}/`,
    page: 1,
    summary: ['links-broken', { count: 1 }],
    table: {
      columns: [
        { key: 'url', type: 'url' },
        { key: 'status', type: 'status' },
        { key: 'anchor', type: 'text' },
      ],
      rows: [{ url: `${BASE}/spring-sale`, status: 404, anchor: 'Spring sale' }],
    },
  },
  {
    check: 'title.missing',
    group: 'page',
    severity: 'error',
    url: `${BASE}/delivery/regions`,
    page: 9,
    summary: ['title-missing', {}],
  },
  {
    check: 'structure.orphan',
    group: 'structure',
    severity: 'warning',
    url: `${BASE}/delivery/regions`,
    page: 9,
    summary: ['orphan', {}],
  },
  {
    check: 'description.duplicate',
    group: 'page',
    severity: 'warning',
    url: `${BASE}/catalog/spades`,
    page: 5,
    summary: ['duplicate-description', { count: 1, value: 'Garden tools and plants, delivered.' }],
    table: {
      columns: [
        { key: 'url', type: 'url' },
        { key: 'title', type: 'text' },
      ],
      rows: [{ url: `${BASE}/catalog/rakes`, title: 'Rakes' }],
    },
  },
  {
    check: 'config.queue',
    group: 'config',
    severity: 'warning',
    url: null,
    summary: ['queue-sync', { connection: 'sync' }],
  },
  {
    check: 'images.broken',
    group: 'images',
    severity: 'error',
    url: `${BASE}/`,
    page: 1,
    summary: ['images-broken', { count: 1 }],
    table: {
      columns: [
        { key: 'url', type: 'url' },
        { key: 'status', type: 'status' },
        { key: 'error', type: 'text' },
      ],
      rows: [{ url: `${BASE}/storage/banners/spring.jpg`, status: 404, error: null }],
    },
  },
  {
    check: 'redirects.chain',
    group: 'redirects',
    severity: 'warning',
    url: `${BASE}/blog`,
    summary: ['redirect-chain', { steps: 2 }],
    table: {
      columns: [
        { key: 'url', type: 'url' },
        { key: 'status', type: 'status' },
      ],
      rows: [
        { url: `${BASE}/blog`, status: 301 },
        { url: `${BASE}/journal`, status: 302 },
        { url: `${BASE}/journal/`, status: 200 },
      ],
    },
  },
  {
    check: 'hreflang.not_reciprocal',
    group: 'page',
    severity: 'warning',
    url: `${BASE}/`,
    page: 1,
    summary: ['hreflang-not-reciprocal', { count: 1 }],
    table: {
      columns: [
        { key: 'lang', type: 'text' },
        { key: 'url', type: 'url' },
        { key: 'status', type: 'status' },
        { key: 'back', type: 'bool' },
        { key: 'indexable', type: 'bool' },
      ],
      rows: [
        { lang: 'en', url: `${BASE}/`, status: 200, back: true, indexable: true },
        { lang: 'de', url: `${BASE}/de/`, status: 200, back: false, indexable: true },
        { lang: 'fr', url: `${BASE}/fr/`, status: null, back: null, indexable: null },
      ],
    },
  },
  {
    check: 'jsonld.required',
    group: 'page',
    severity: 'warning',
    url: `${BASE}/`,
    page: 1,
    summary: ['jsonld-required', { count: 1 }],
    table: {
      columns: [
        { key: 'block', type: 'text' },
        { key: 'type', type: 'text' },
        { key: 'fields', type: 'text' },
      ],
      rows: [{ block: 2, type: 'Product', fields: 'offers | review | aggregateRating' }],
    },
  },
]

interface Run {
  id: number
  status: 'queued' | 'running' | 'done' | 'failed' | 'cancelled'
  scope: 'full' | 'quick' | 'urls'
  /** The addresses of a recheck. */
  urls?: string[]
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
    scope: 'full',
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
  'hosts.dev_page': 'error',
  'links.broken': 'error',
  'title.missing': 'error',
  'images.alt': 'warning',
  'structure.orphan': 'warning',
  'description.duplicate': 'warning',
  'images.broken': 'error',
  'redirects.chain': 'warning',
  'hreflang.not_reciprocal': 'warning',
  'jsonld.required': 'warning',
}

/* A recheck is measured against the last full run; the others against the run before them. */
function previous(run: Run): Run | undefined {
  return runs
    .filter(
      (other) =>
        other.status === 'done' &&
        other.id < run.id &&
        (run.scope === 'urls' ? other.scope === 'full' : other.scope !== 'urls'),
    )
    .at(-1)
}

function state(run: Run, finding: Finding): 'new' | 'persisting' {
  const before = previous(run)

  return before?.findings.some((other) => fingerprint(other) === fingerprint(finding))
    ? 'persisting'
    : 'new'
}

/*
 * The health as `Runs\Health` counts it (§12): the share of crawled pages without errors, minus
 * 10 for each error of the whole site and 2 for each check with warnings, 20 at most.
 */
function health(run: Run, findings: Finding[]) {
  const pages =
    run.scope === 'quick' ? 0 : run.scope === 'urls' ? (run.urls ?? []).length : PAGES.length
  const broken = new Set<number>()
  const site = new Set<string>()
  const warnings = new Set<string>()

  for (const finding of findings) {
    if (finding.severity === 'error') {
      if (finding.page === undefined) site.add(finding.check)
      else broken.add(finding.page)
    } else if (finding.severity === 'warning') {
      warnings.add(finding.check)
    }
  }

  const clean = Math.max(0, pages - broken.size)
  const share = pages === 0 ? 100 : (100 * clean) / pages
  const penalty = 10 * site.size + Math.min(20, 2 * warnings.size)

  return {
    score: Math.max(0, Math.round(share - penalty)),
    parts: { pages, clean, site_errors: site.size, warnings: warnings.size },
  }
}

function counts(run: Run) {
  const severity: Record<Severity, number> = { error: 0, warning: 0, notice: 0 }
  const groups: Record<string, Record<Severity, number>> = {}
  const failed: Record<string, Severity> = {}

  for (const finding of shown(run)) {
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

  const { score, parts } = health(run, shown(run))
  const before = previous(run)
  const now = new Set(run.findings.map(fingerprint))

  return {
    severity,
    groups,
    checks: Object.keys(CHECKS),
    failed,
    health: score,
    health_parts: parts,
    new: shown(run).filter((finding) => state(run, finding) === 'new').length,
    fixed: before
      ? shown(before).filter(
          (finding) =>
            !now.has(fingerprint(finding)) &&
            (run.scope !== 'urls' || (run.urls ?? []).includes(finding.url ?? '')),
        ).length
      : 0,
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
    urls: run.urls ?? [],
    progress: {
      stage: run.status === 'done' ? 'analyse' : stage(run),
      done: [],
      checks: 23,
      pages: { crawled: run.scope === 'full' ? PAGES.length : 0, limit: 1000 },
    },
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

/* One crawled page of the snapshot, the way the API lists it. */
interface PageRow {
  id: number
  url: string
  status: number | null
  final_status: number | null
  redirect_to: string | null
  source: 'home' | 'sitemap' | 'registry' | 'link'
  depth: number | null
  indexable: boolean
  title: string | null
  description: string | null
  h1: string | null
  canonical: string | null
  robots_meta: string | null
  x_robots_tag: string | null
  lang: string | null
  content_type: string | null
  bytes: number | null
  ttfb_ms: number | null
  total_ms: number | null
  compression: string | null
  word_count: number | null
  links_in: number
  links_out_internal: number
  links_out_external: number
  images: number
  images_without_alt: number
  in_sitemap: boolean
  in_registry: boolean
  blocked_by_robots: boolean
}

const html = 'text/html; charset=utf-8'
const description = 'Garden tools and plants, delivered to the door within a day.'

function crawled(
  id: number,
  path: string,
  fields: Partial<PageRow> & Pick<PageRow, 'depth' | 'title'>,
): PageRow {
  return {
    id,
    url: `${BASE}${path}`,
    status: 200,
    final_status: 200,
    redirect_to: null,
    source: 'link',
    indexable: true,
    description,
    h1: fields.title,
    canonical: `${BASE}${path}`,
    robots_meta: null,
    x_robots_tag: null,
    lang: 'en',
    content_type: html,
    bytes: 38_000 + id * 1_731,
    ttfb_ms: 90 + id * 23,
    total_ms: 120 + id * 31,
    compression: 'br',
    word_count: 300 + id * 47,
    links_in: 3,
    links_out_internal: 24,
    links_out_external: 2,
    images: 6,
    images_without_alt: 0,
    in_sitemap: true,
    in_registry: true,
    blocked_by_robots: false,
    ...fields,
  }
}

const PAGES: PageRow[] = [
  crawled(1, '/', {
    depth: 0,
    source: 'home',
    title: 'Garden shop — tools and plants',
    images_without_alt: 2,
    links_in: 11,
  }),
  crawled(2, '/about', { depth: 1, title: 'About the shop and the people who run it' }),
  crawled(3, '/delivery', { depth: 1, title: 'Delivery and payment' }),
  crawled(4, '/catalog', { depth: 1, title: 'Catalog', word_count: 140 }),
  crawled(5, '/catalog/spades', {
    depth: 2,
    title: 'Spades',
    description: 'Garden tools and plants, delivered.',
  }),
  crawled(6, '/catalog/rakes', {
    depth: 2,
    title: 'Rakes',
    description: 'Garden tools and plants, delivered.',
  }),
  crawled(7, '/blog', {
    depth: 1,
    status: 301,
    final_status: 200,
    redirect_to: `${BASE}/blog/`,
    indexable: false,
    title: null,
    description: null,
    h1: null,
    canonical: null,
    lang: null,
    content_type: null,
    bytes: 0,
    word_count: null,
    links_out_internal: 0,
    links_out_external: 0,
    images: 0,
    in_sitemap: false,
  }),
  crawled(8, '/blog/', {
    depth: 1,
    title: 'The garden blog',
    robots_meta: 'noindex, follow',
    indexable: false,
  }),
  crawled(9, '/delivery/regions', {
    depth: null,
    source: 'sitemap',
    title: null,
    h1: 'Regions',
    links_in: 0,
    in_registry: false,
  }),
  crawled(10, '/spring-sale', {
    depth: 1,
    status: 404,
    final_status: 404,
    indexable: false,
    title: 'Not found',
    description: null,
    canonical: null,
    word_count: 12,
    in_sitemap: false,
    in_registry: false,
  }),
  crawled(11, '/catalog/seeds?sort=price', {
    depth: 2,
    title: 'Seeds',
    canonical: `${BASE}/catalog/seeds`,
    indexable: false,
    in_sitemap: false,
    in_registry: false,
  }),
  crawled(12, '/private/drafts', {
    depth: 2,
    title: 'Drafts',
    blocked_by_robots: true,
    in_sitemap: false,
    in_registry: false,
  }),
]

/* The home page's structured data: one block that does not parse, one product without a price. */
const HOME_JSON_LD = [
  {
    types: ['Organization', 'WebSite'],
    error: null,
    items: [],
    source:
      '{"@context":"https://schema.org","@graph":[{"@type":"Organization","name":"Garden shop"},{"@type":"WebSite","url":"https://shop.example.com/"}]}',
  },
  {
    types: ['Product'],
    error: null,
    items: [
      {
        type: 'Product',
        missing: ['offers | review | aggregateRating'],
        recommended: ['brand', 'sku'],
      },
    ],
    source:
      '{\n  "@context": "https://schema.org",\n  "@type": "Product",\n  "name": "Spade",\n  "image": "https://shop.example.com/storage/spade.jpg",\n  "description": "A spade for every garden."\n}',
  },
  { types: [], error: 'Syntax error', items: [], source: '{"@type": "BreadcrumbList",}' },
]

/* What a page loads, as stage 5 answered: the home page has a little of everything. */
function resourcesOf(page: PageRow, tab: 'images' | 'css' | 'js') {
  const row = (url: string, extra: Record<string, unknown>) => ({
    url,
    kind: 'img',
    alt: null,
    host_class: 'own',
    checked: true,
    status: 200,
    error: null,
    location: null,
    content_type: null,
    bytes: null,
    cache_control: 'public, max-age=31536000, immutable',
    compression: null,
    width: null,
    height: null,
    ...extra,
  })

  if (tab === 'css') {
    return [
      row(`${BASE}/build/app.css`, {
        kind: 'link',
        content_type: 'text/css',
        bytes: 48_210,
        compression: 'br',
      }),
    ]
  }

  if (tab === 'js') {
    return [
      row(`${BASE}/build/app.js`, {
        kind: 'script',
        content_type: 'text/javascript',
        bytes: 91_400,
        compression: 'br',
      }),
      row('https://analytics.example.org/tag.js', {
        kind: 'script',
        host_class: 'external',
        content_type: 'text/javascript',
        bytes: 31_000,
        cache_control: 'max-age=300',
      }),
    ]
  }

  if (page.id !== 1) {
    return [
      row(`${BASE}/storage/logo.svg`, {
        alt: 'Garden shop',
        content_type: 'image/svg+xml',
        bytes: 3_100,
      }),
    ]
  }

  return [
    row(`${BASE}/storage/logo.svg`, {
      alt: 'Garden shop',
      content_type: 'image/svg+xml',
      bytes: 3_100,
    }),
    row(`${BASE}/storage/spade.jpg`, { content_type: 'image/jpeg', bytes: 421_000 }),
    row(`${BASE}/storage/banners/spring.jpg`, {
      alt: 'Spring sale',
      status: 404,
      cache_control: null,
    }),
    row(`${BASE}/storage/og.png`, {
      kind: 'meta',
      content_type: 'image/png',
      bytes: 88_000,
      width: 600,
      height: 315,
    }),
    row('https://dev.shop.example.com/storage/hero.jpg', {
      alt: 'Hero',
      host_class: 'dev',
      checked: false,
      status: null,
      cache_control: null,
    }),
  ]
}

/* Outgoing links of the home page; the other pages link home and to the catalog. */
function linksOf(page: PageRow) {
  const own = (id: number, anchor: string, kind = 'a') => {
    const target = PAGES.find((other) => other.id === id)!

    return {
      url: target.url,
      page_id: id,
      status: target.status,
      kind,
      anchor,
      rel: null,
      target: null,
      host: 'shop.example.com',
      host_class: 'own',
      absolute: false,
    }
  }

  if (page.id === 1) {
    return [
      own(2, 'About us'),
      own(3, 'Delivery'),
      own(4, 'Catalog'),
      own(7, 'Blog'),
      own(10, 'Spring sale'),
      {
        url: 'https://dev.shop.example.com/storage/hero.jpg',
        page_id: null,
        status: null,
        kind: 'img',
        anchor: null,
        rel: null,
        target: null,
        host: 'dev.shop.example.com',
        host_class: 'dev',
        absolute: true,
      },
      {
        url: 'https://www.youtube.com/@gardenshop',
        page_id: null,
        status: null,
        kind: 'a',
        anchor: 'Our channel',
        rel: 'noopener',
        target: '_blank',
        host: 'www.youtube.com',
        host_class: 'external',
        absolute: true,
      },
    ]
  }

  return [own(1, 'Home'), own(4, 'Catalog')]
}

function pageRow(run: Run, page: PageRow) {
  return { ...page, issues: run.findings.filter((finding) => finding.page === page.id).length }
}

const FIELD_TYPES: Record<string, string> = {
  status: 'number',
  final_status: 'number',
  depth: 'number',
  bytes: 'number',
  ttfb_ms: 'number',
  total_ms: 'number',
  word_count: 'number',
  links_in: 'number',
  links_out_internal: 'number',
  links_out_external: 'number',
  images: 'number',
  images_without_alt: 'number',
  issues: 'number',
}

/* `f[field]=op:value`, the way PageQuery reads it on the server. */
function matches(row: Record<string, unknown>, field: string, condition: string): boolean {
  const [op, value = ''] = condition.split(/:(.*)/s)
  const cell = row[field]
  const number = FIELD_TYPES[field] === 'number'

  switch (op) {
    case 'empty':
      return cell === null || cell === '' || cell === undefined
    case 'filled':
      return cell !== null && cell !== '' && cell !== undefined
    case 'yes':
      return cell === true
    case 'no':
      return cell === false
    case 'contains':
      return String(cell ?? '')
        .toLowerCase()
        .includes(value.toLowerCase())
    case 'eq':
      return String(cell ?? '') === value
    case 'gt':
      return number ? Number(cell ?? 0) > Number(value) : String(cell ?? '') > value
    case 'lt':
      return number ? cell === null || Number(cell) < Number(value) : String(cell ?? '') < value
    default:
      return true
  }
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
    const done = runs.filter((run) => run.status === 'done' && run.scope !== 'urls').at(-1)
    const crawled = runs.filter((run) => run.status === 'done' && run.scope === 'full').at(-1)

    return {
      data: {
        active: active ? resource(active) : null,
        done: done ? resource(done) : null,
        crawled: crawled ? resource(crawled) : null,
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

    const urls = Array.isArray(body.urls) ? body.urls.map(String) : []

    if (body.scope === 'urls' && !urls.some((url) => url.startsWith(`${BASE}/`))) {
      throw fail(422, line('en', 'webx-audit', 'page.urls-foreign'))
    }

    const run: Run =
      body.scope === 'urls'
        ? {
            id: runs.length + 1,
            status: 'queued',
            scope: 'urls',
            urls,
            startedAt: Date.now(),
            // The edit worked: what the page had in the last full run, but its title is there now.
            findings: FINDINGS.filter(
              (finding) => urls.includes(finding.url ?? '') && finding.check !== 'title.missing',
            ),
            createdAt: new Date().toISOString(),
            finishedAt: null,
          }
        : {
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

  /* Hidden findings are a state of their own: out of every list, until asked for. */
  const filtered = (run: Run, query: URLSearchParams) =>
    run.findings.filter(
      (finding) =>
        (query.get('state') === 'hidden' ? Boolean(ruleOf(finding)) : !ruleOf(finding)) &&
        (!query.get('severity') || finding.severity === query.get('severity')) &&
        (!query.get('group') || finding.group === query.get('group')) &&
        (!query.get('state') ||
          query.get('state') === 'hidden' ||
          state(run, finding) === query.get('state')) &&
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
          fixes: FIXES[row.id] ?? [],
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
      data: rows.map((finding) => ({
        // The place in the run, not in the filtered list: the fix dialog asks by it.
        id: run.findings.indexOf(finding) + 1,
        check: finding.check,
        severity: finding.severity,
        url: finding.url,
        state: state(run, finding),
        ignored: Boolean(ruleOf(finding)),
        ignore: ruleOf(finding) ?? null,
        fixed_with: pressed.get(fingerprint(finding)) ?? null,
        fixed_at: null,
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

  const issueOf = (run: Run, id: string): Finding => {
    const finding = run.findings[Number(id) - 1]

    if (!finding) throw fail(404, 'No such finding.')

    return finding
  }

  const hostOf = (url: unknown) => {
    try {
      return new URL(String(url)).host
    } catch {
      return ''
    }
  }

  /* What a fix would change, the way the server's previews say it. */
  const offer = (finding: Finding, id: string, locale: string) => {
    const seo = (key: string) => line(locale, 'webx-seo', key)
    const setting = (name: string, after: string) => [
      {
        label: seo(`screen.${name}`),
        before: seo('screen.normalise-off'),
        after,
        edit_url: '/settings',
      },
    ]

    const changes =
      id === 'audit.replace-host'
        ? [
            {
              label: String(finding.table?.rows[0]?.record ?? ''),
              field: String(finding.table?.rows[0]?.field ?? ''),
              count: finding.table?.rows.length ?? 0,
              before: [...new Set(finding.table?.rows.map((row) => hostOf(row.url)))].join(', '),
              after: hostOf(BASE),
              edit_url: String(finding.table?.rows[0]?.edit ?? ''),
            },
          ]
        : id === 'seo.normalise-host'
          ? setting('normalise-host', seo('screen.normalise-host-bare'))
          : id === 'seo.normalise-https'
            ? setting('normalise-https', seo('audit.on'))
            : id === 'seo.normalise-index'
              ? setting('normalise-index', seo('audit.on'))
              : (finding.table?.rows ?? []).slice(0, -2).map((row) => ({
                  label: new URL(String(row.url)).pathname,
                  before: new URL(String(finding.table?.rows.at(-2)?.url)).pathname,
                  after: new URL(String(finding.table?.rows.at(-1)?.url)).pathname,
                  edit_url: '/seo/redirects',
                }))
    const namespace = id.startsWith('seo.') ? 'webx-seo' : 'webx-audit'

    return {
      id,
      title: line(locale, namespace, `fixes.${id}.title`),
      description: line(locale, namespace, `fixes.${id}.description`),
      changes,
      total: changes.reduce((sum, change) => sum + ('count' in change ? change.count : 1), 0),
      note: id.startsWith('seo.normalise') ? seo('audit.normalise-note') : null,
    }
  }

  on('GET', '/audit/runs/(\\d+)/issues/(\\d+)/fixes', ({ params, locale }) => {
    const finding = issueOf(find(params[0]!), params[1]!)

    return {
      data: pressed.has(fingerprint(finding))
        ? []
        : (FIXES[finding.check] ?? []).map((id) => offer(finding, id, locale)),
    }
  })

  on('POST', '/audit/runs/(\\d+)/issues/(\\d+)/fixes/([^/]+)', ({ params, body, locale }) => {
    const finding = issueOf(find(params[0]!), params[1]!)
    const id = decodeURIComponent(params[2]!)

    if (!(FIXES[finding.check] ?? []).includes(id) || pressed.has(fingerprint(finding))) {
      throw fail(422, 'This fix cannot close this finding any more. Run the audit again.')
    }

    const dryRun = Boolean((body as { dry_run?: boolean } | null)?.dry_run)

    if (!dryRun) pressed.set(fingerprint(finding), id)

    return { data: { ...offer(finding, id, locale), applied: !dryRun } }
  })

  const pagesOf = (run: Run) =>
    run.scope === 'full'
      ? PAGES
      : run.scope === 'urls'
        ? PAGES.filter((page) => run.urls?.includes(page.url))
        : []

  const paginate = <T>(rows: T[], query: URLSearchParams) => {
    const perPage = Number(query.get('per_page') ?? 50)
    const current = Math.max(1, Number(query.get('page') ?? 1))
    const last = Math.max(1, Math.ceil(rows.length / perPage))
    const slice = rows.slice((current - 1) * perPage, current * perPage)

    return {
      data: slice,
      meta: {
        current_page: current,
        last_page: last,
        per_page: perPage,
        total: rows.length,
        from: slice.length ? (current - 1) * perPage + 1 : null,
        to: slice.length ? (current - 1) * perPage + slice.length : null,
      },
    }
  }

  on('GET', '/audit/runs/(\\d+)/pages', ({ params, query }) => {
    const run = find(params[0]!)
    const status = query.get('status')
    const search = (query.get('search') ?? '').toLowerCase()
    const check = query.get('check')
    let rows: Record<string, unknown>[] = pagesOf(run).map((page) => pageRow(run, page))

    rows = rows.filter((row) => {
      const code = row.status as number | null

      return (
        (!search || String(row.url).toLowerCase().includes(search)) &&
        (!status ||
          (status === 'none' ? code === null : String(code ?? '').startsWith(status[0]!))) &&
        (!query.get('indexable') || row.indexable === (query.get('indexable') === '1')) &&
        (!check ||
          run.findings.some((finding) => finding.check === check && finding.page === row.id)) &&
        [...query.entries()].every(([key, condition]) => {
          const field = /^f\[(.+)\]$/.exec(key)?.[1]

          return !field || matches(row, field, condition)
        })
      )
    })

    const sort = query.get('sort')

    if (sort) {
      const key = sort.replace(/^-/, '')
      const sign = sort.startsWith('-') ? -1 : 1

      rows = [...rows].sort((a, b) => {
        const x = a[key] ?? ''
        const y = b[key] ?? ''

        return (x > y ? 1 : x < y ? -1 : 0) * sign
      })
    }

    return paginate(rows, query)
  })

  on('GET', '/audit/runs/(\\d+)/pages/(\\d+)', ({ params, locale }) => {
    const run = find(params[0]!)
    const page = pagesOf(run).find((candidate) => candidate.id === Number(params[1]))

    if (!page) throw fail(404, 'Not found.')

    const issues = run.findings
      .map((finding, index) => ({ finding, index }))
      .filter(({ finding }) => finding.page === page.id)
      .map(({ finding, index }) => ({
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
      }))
    const incoming = PAGES.filter((other) =>
      linksOf(other).some((link) => link.page_id === page.id),
    )

    return {
      data: {
        page: {
          ...pageRow(run, page),
          headers: page.content_type
            ? {
                'content-type': page.content_type,
                'cache-control': 'no-cache, private',
                'content-encoding': 'br',
                server: 'nginx',
              }
            : { location: page.redirect_to ?? '' },
          h1: page.h1 ? [page.h1] : [],
          headings: { h1: page.h1 ? 1 : 0, h2: 4, h3: 2, h4: 0, h5: 0, h6: 0 },
          hreflang: [],
          og: page.title ? { title: page.title, type: 'website' } : {},
          twitter: {},
          json_ld: page.id === 1 ? HOME_JSON_LD : [],
          error: null,
          facts: {},
          fetched_at: ago(60),
        },
        issues,
        counts: {
          issues: issues.length,
          incoming: incoming.length,
          outgoing: linksOf(page).length,
          images: resourcesOf(page, 'images').length,
          css: resourcesOf(page, 'css').length,
          js: resourcesOf(page, 'js').length,
          microdata: page.id === 1 ? HOME_JSON_LD.length : 0,
        },
      },
    }
  })

  on('GET', '/audit/runs/(\\d+)/pages/(\\d+)/resources', ({ params, query }) => {
    const run = find(params[0]!)
    const page = pagesOf(run).find((candidate) => candidate.id === Number(params[1]))
    const tab = query.get('tab')

    if (!page) throw fail(404, 'Not found.')
    if (tab !== 'images' && tab !== 'css' && tab !== 'js') throw fail(422, 'Unknown tab.')

    return paginate(
      resourcesOf(page, tab).map((row, index) => ({ id: index + 1, ...row })),
      query,
    )
  })

  on('GET', '/audit/runs/(\\d+)/pages/(\\d+)/links', ({ params, query }) => {
    const run = find(params[0]!)
    const page = pagesOf(run).find((candidate) => candidate.id === Number(params[1]))

    if (!page) throw fail(404, 'Not found.')

    const rows =
      query.get('direction') === 'in'
        ? PAGES.flatMap((other) =>
            linksOf(other)
              .filter((link) => link.page_id === page.id)
              .map((link) => ({
                ...link,
                url: other.url,
                page_id: other.id,
                status: other.status,
              })),
          )
        : linksOf(page)

    return paginate(
      rows.map((link, index) => ({ id: index + 1, ...link })),
      query,
    )
  })

  /* One page as a file: the browser downloads what the card shows, plus its links. */
  on('GET', '/audit/runs/(\\d+)/pages/(\\d+)/export', ({ params }) => {
    const run = find(params[0]!)
    const page = pagesOf(run).find((candidate) => candidate.id === Number(params[1]))

    if (!page) throw fail(404, 'Not found.')

    return {
      run: { id: run.id, scope: run.scope, base_url: BASE, finished_at: run.finishedAt },
      page: pageRow(run, page),
      issues: run.findings.filter((finding) => finding.page === page.id),
      links: linksOf(page),
    }
  })

  const issueJson = (run: Run, finding: Finding, locale: string) => ({
    id: run.findings.indexOf(finding) + 1,
    check: finding.check,
    severity: finding.severity,
    url: finding.url,
    state: state(run, finding),
    ignored: Boolean(ruleOf(finding)),
    ignore: ruleOf(finding) ?? null,
    fixed_with: null,
    fixed_at: null,
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
  })

  on('GET', '/audit/runs', ({ query }) => {
    runs.forEach(advance)

    return paginate([...runs].reverse().map(resource), query)
  })

  /* Two runs by fingerprint: new in `to`, in both, gone from `from` (decision 8). */
  const comparison = (query: URLSearchParams) => {
    const to = find(query.get('to') ?? '')
    const from = query.get('from') ? find(query.get('from')!) : previous(to)

    if (!from) throw fail(404, 'Not found.')

    const before = new Set(shown(from).map(fingerprint))
    const after = new Set(to.findings.map(fingerprint))
    const kinds = {
      new: shown(to).filter((finding) => !before.has(fingerprint(finding))),
      persisting: shown(to).filter((finding) => before.has(fingerprint(finding))),
      // A recheck answers for its own addresses, not for every other page of the run.
      fixed: shown(from).filter(
        (finding) =>
          !after.has(fingerprint(finding)) &&
          (to.scope !== 'urls' || (to.urls ?? []).includes(finding.url ?? '')),
      ),
    }

    return { from, to, kinds }
  }

  on('GET', '/audit/runs/compare', ({ query, locale }) => {
    const { from, to, kinds } = comparison(query)
    const rows = new Map<
      string,
      {
        check: string
        title: string
        severity: Severity
        new: number
        persisting: number
        fixed: number
      }
    >()

    for (const [kind, findings] of Object.entries(kinds) as [keyof typeof kinds, Finding[]][]) {
      for (const finding of findings) {
        const row = rows.get(finding.check) ?? {
          check: finding.check,
          title: line(locale, 'webx-audit', `checks.${finding.check}.title`),
          severity: finding.severity,
          new: 0,
          persisting: 0,
          fixed: 0,
        }
        row[kind]++
        rows.set(finding.check, row)
      }
    }

    return {
      data: {
        from: resource(from),
        to: resource(to),
        checks: [...rows.values()].sort(
          (a, b) => WEIGHTS[b.severity] - WEIGHTS[a.severity] || b.new + b.fixed - a.new - a.fixed,
        ),
      },
    }
  })

  on('GET', '/audit/runs/compare/issues', ({ query, locale }) => {
    const { from, to, kinds } = comparison(query)
    const kind = query.get('kind') as keyof typeof kinds

    if (!(kind in kinds)) throw fail(422, 'The kind is new, persisting or fixed.')

    const run = kind === 'fixed' ? from : to

    return paginate(
      kinds[kind]
        .filter((finding) => !query.get('check') || finding.check === query.get('check'))
        .map((finding) => issueJson(run, finding, locale)),
      query,
    )
  })

  /* «Outgoing»: the hosts of the full run's links and of the stand addresses in the content. */
  on('GET', '/audit/hosts', ({ query }) => {
    const full = runs.filter((run) => run.status === 'done' && run.scope === 'full').at(-1)
    const links = (full ? PAGES : []).flatMap((page) =>
      linksOf(page)
        .filter((link) => link.host_class !== 'own')
        .map((link) => ({ ...link, page })),
    )
    const fields = FINDINGS.filter((finding) => finding.check === 'hosts.dev_content').flatMap(
      (finding) => finding.table?.rows ?? [],
    )
    const hosts = new Map<string, Record<string, unknown> & { host: string; class: string }>()
    const row = (host: string, kind: string) =>
      hosts.get(host) ??
      hosts
        .set(host, {
          host,
          class: kind,
          links: 0,
          pages: 0,
          broken: 0,
          nofollow: 0,
          fields: 0,
          first_seen: ago(3 * 1440),
        })
        .get(host)!

    for (const link of links) {
      const entry = row(link.host, link.host_class)
      entry.links = Number(entry.links) + 1
      entry.pages = Number(entry.pages) + 1
    }

    for (const field of fields) {
      const entry = row(hostOf(field.url), 'dev')
      entry.fields = Number(entry.fields) + 1
    }

    const own = row('shop.example.com', 'own')
    own.links = PAGES.reduce((sum, page) => sum + linksOf(page).length, 0) - links.length
    own.pages = PAGES.length

    const order: Record<string, number> = { dev: 0, own_mirror: 1, external: 2, own: 3 }
    const search = (query.get('search') ?? query.get('host') ?? '').toLowerCase()
    const list = [...hosts.values()]
      .filter((entry) => !query.get('class') || entry.class === query.get('class'))
      .filter((entry) => !search || entry.host.includes(search))
      .sort((a, b) => (order[a.class] ?? 9) - (order[b.class] ?? 9))
    const host = query.get('host')

    return {
      data: {
        crawled_run: full?.id ?? null,
        database_run: runs.at(-1)?.id ?? null,
        classes: list.reduce<Record<string, number>>(
          (sum, entry) => ({ ...sum, [entry.class]: (sum[entry.class] ?? 0) + 1 }),
          {},
        ),
        hosts: list,
        ...(host
          ? {
              pages: links
                .filter((link) => link.host === host)
                .map((link) => ({
                  page_id: link.page.id,
                  page: link.page.url,
                  url: link.url,
                  kind: link.kind,
                  anchor: link.anchor,
                  rel: link.rel,
                  status: link.status,
                })),
              fields: fields
                .filter((field) => hostOf(field.url) === host)
                .map((field) => ({
                  source: String(field.field).includes('draft') ? 'regions' : 'pages',
                  record_id: '2',
                  record_label: String(field.record),
                  field: String(field.field),
                  locale: null,
                  url: String(field.url),
                  published: Boolean(field.published),
                  edit_url: String(field.edit),
                })),
            }
          : {}),
      },
    }
  })

  on('GET', '/audit/ignores', ({ locale }) => ({
    data: [...rules].reverse().map((rule) => ({
      ...rule,
      title: line(locale, 'webx-audit', `checks.${rule.check}.title`),
      hidden: runs.flatMap((run) => run.findings).filter((finding) => ruleOf(finding) === rule)
        .length,
    })),
  }))

  on('POST', '/audit/ignores', ({ body, locale }) => {
    const check = String(body.check ?? '')
    const pattern = String(body.pattern ?? '').trim()
    const last = runs.filter((run) => run.status === 'done' && run.scope !== 'urls').at(-1)

    if (body.dry_run) {
      return {
        data: {
          hidden: (last ? shown(last) : []).filter(
            (finding) => finding.check === check && masked(pattern, finding.url),
          ).length,
        },
      }
    }

    if (!String(body.reason ?? '').trim()) {
      throw fail(422, line(locale, 'webx-audit', 'page.hide-reason'))
    }

    const rule: Rule = {
      id: rules.length + 1,
      check,
      pattern,
      reason: String(body.reason).trim(),
      created_by: 'Admin',
      created_at: new Date().toISOString(),
    }
    rules.push(rule)

    return { data: rule }
  })

  on('DELETE', '/audit/ignores/(\\d+)', ({ params }) => {
    const index = rules.findIndex((rule) => rule.id === Number(params[0]))

    if (index === -1) throw fail(404, 'Not found.')

    rules.splice(index, 1)

    return { data: { id: Number(params[0]) } }
  })

  on('GET', '/audit/settings', () => ({ data: { values: { ...settings } } }))

  on('PUT', '/audit/settings', ({ body }) => {
    Object.assign(settings, (body.values ?? {}) as Record<string, unknown>)

    return { data: { values: { ...settings } } }
  })
}
