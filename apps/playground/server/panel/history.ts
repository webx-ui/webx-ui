import type {
  HistoryChange,
  HistoryEntry,
  HistoryPage,
  HistoryRun,
} from '../../../../packages/module-admin/src/history'

/**
 * The journal of a record that does not exist anywhere else: `demo.product`, a kettle.
 *
 * The catalogue that will write real rows has no code yet, and the journal's node is the frame's
 * — so it is shown here on a fake entity with a history made up to have every kind of line in
 * it: saves in the panel, an agent's, a nightly price import and a bulk action, a published
 * record, a long text, a translated name, somebody whose account is gone.
 *
 * The shapes are the server's (`HistoryPresenter`), so what the node draws here is what a site
 * gets.
 */

const TYPE = 'demo.product'
const PER_PAGE = 20

const LABELS: Record<string, string> = {
  price: 'Цена',
  stock: 'Остаток',
  is_visible: 'Показывать на сайте',
  body: 'Описание',
  sku: 'Артикул',
}

function label(field: string): string {
  const [base, locale] = field.split('.')
  const word = LABELS[base!] ?? (base === 'name' ? 'Название' : base!)

  return locale === undefined ? word : `${word} (${locale.toUpperCase()})`
}

function change(field: string, from: unknown, to: unknown): HistoryChange {
  return { field, label: label(field), from, to }
}

const ANNA = { id: 1, name: 'Анна Ковальчук' }
const VASYA = { id: 4, name: 'Василий Орлов' }
/* An account deleted since: the journal keeps the name it was signed with. */
const GONE = { id: 9, name: 'Пётр Смирнов' }

const RUNS: HistoryEntry[] = [
  {
    id: 900,
    event: 'run',
    source: 'import',
    subject: { type: TYPE, id: null },
    admin: ANNA,
    grant_id: null,
    changes: [],
    run: null,
    created_at: '2026-09-28T03:05:00+03:00',
    summary: { what: 'prices-2026-09.csv', profile: 'Цены поставщика', rows: 34 },
    rows: 34,
  },
  {
    id: 901,
    event: 'run',
    source: 'bulk',
    subject: { type: TYPE, id: null },
    admin: VASYA,
    grant_id: null,
    changes: [],
    run: null,
    created_at: '2026-09-21T16:40:00+03:00',
    summary: { what: 'Скрыть распроданное', rows: 12 },
    rows: 12,
  },
]

function runRef(id: number): HistoryEntry['run'] {
  const run = RUNS.find((one) => one.id === id)

  return { id, summary: run?.summary ?? null }
}

/* The kettle's own lines, newest first. */
const KETTLE: HistoryEntry[] = [
  line(60, '2026-09-29T09:12:00+03:00', 'updated', 'panel', ANNA, [
    change('price', '2490.00', '2290.00'),
  ]),
  line(59, '2026-09-28T22:48:00+03:00', 'updated', 'mcp', VASYA, [change('stock', 3, 12)], {
    grant_id: 17,
  }),
  line(
    58,
    '2026-09-28T03:05:40+03:00',
    'updated',
    'import',
    ANNA,
    [change('price', '2390.00', '2490.00'), change('stock', 0, 3)],
    { run: runRef(900) },
  ),
  line(57, '2026-09-26T11:02:00+03:00', 'updated', 'panel', ANNA, [
    {
      field: 'body',
      label: label('body'),
      from: null,
      to: null,
      long: true,
      from_length: 612,
      to_length: 1840,
    },
  ]),
  line(56, '2026-09-24T14:30:00+03:00', 'published', 'panel', VASYA, []),
  line(55, '2026-09-24T14:29:00+03:00', 'updated', 'panel', VASYA, [
    change('name.ru', 'Чайник', 'Чайник электрический 1,7 л'),
    change('name.en', null, 'Electric kettle 1.7 l'),
  ]),
  line(
    54,
    '2026-09-21T16:40:12+03:00',
    'updated',
    'bulk',
    VASYA,
    [change('is_visible', true, false)],
    { run: runRef(901) },
  ),
  ...Array.from({ length: 16 }, (_, index) =>
    line(
      53 - index,
      new Date(Date.UTC(2026, 8, 20 - index, 7, 30)).toISOString(),
      'updated',
      'panel',
      index % 3 === 0 ? GONE : ANNA,
      [change('price', `${2000 + index * 10}.00`, `${2010 + index * 10}.00`)],
    ),
  ),
  line(30, '2026-09-01T08:00:00+03:00', 'updated', 'console', null, [
    change('sku', null, 'KT-1700'),
  ]),
  line(29, '2026-08-31T18:00:00+03:00', 'created', 'panel', GONE, []),
]

function line(
  id: number,
  at: string,
  event: HistoryEntry['event'],
  source: HistoryEntry['source'],
  admin: HistoryEntry['admin'],
  changes: HistoryChange[],
  extra: Partial<HistoryEntry> = {},
): HistoryEntry {
  return {
    id,
    event,
    source,
    subject: { type: TYPE, id: 7 },
    admin,
    grant_id: null,
    changes,
    run: null,
    created_at: at,
    ...extra,
  }
}

/* What else each run touched: other products, one line each. */
function runRows(run: HistoryEntry): HistoryEntry[] {
  const count = run.rows ?? 0

  return Array.from({ length: count }, (_, index) => {
    const product = index === 0 ? 7 : 100 + index

    return line(
      run.id * 100 + index,
      run.created_at ?? '',
      'updated',
      run.source,
      run.admin,
      run.source === 'bulk'
        ? [change('is_visible', true, false)]
        : [change('price', `${1000 + index * 50}.00`, `${1100 + index * 50}.00`)],
      { subject: { type: TYPE, id: product }, run: { id: run.id, summary: run.summary ?? null } },
    )
  })
}

function paginate(rows: HistoryEntry[], page: number): HistoryPage {
  const last = Math.max(1, Math.ceil(rows.length / PER_PAGE))
  const current = Math.min(Math.max(1, page), last)

  return {
    data: rows.slice((current - 1) * PER_PAGE, current * PER_PAGE),
    current_page: current,
    last_page: last,
    total: rows.length,
  }
}

/** `GET /history/{type}/{id}` — only the kettle has a past; any other record has none yet. */
export function historyOf(type: string, id: number, page: number): HistoryPage | null {
  if (type !== TYPE) return null

  return paginate(id === 7 ? KETTLE : [], page)
}

/** `GET /history/runs/{id}` with the search by record. */
export function historyRun(id: number, page: number, search: number | null): HistoryRun | null {
  const run = RUNS.find((one) => one.id === id)

  if (run === undefined) return null

  const rows = runRows(run).filter((row) => search === null || row.subject.id === search)

  return { run, rows: paginate(rows, page) }
}
