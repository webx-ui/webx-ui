/**
 * «Orders» (`module-orders`, spec §11, §15): twelve orders of the demo set in the shapes the PHP
 * controllers answer — the list with its views and filters, the card, the dry runs of the
 * dialogs and what they do. The rules are the service's, retold small: a mark is set once, a
 * cancelled order takes no payment, a refund is held to what is left.
 *
 * Fake buyers only: «Buyer A»…, phones `+800 1234 5678` and on, addresses on `example.com`.
 *
 * The statuses and the settings of the section (§5.6, §11.4) are here too: both levels in the
 * order of the arrays, written, dragged, deleted softly and brought back under the service's
 * rules — the initial status stays, a group with orders keeps an active stage, a public group goes
 * only empty or with its stages moved.
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
type Fail = (
  status: number,
  message: string,
  data?: unknown,
  errors?: Record<string, string[]>,
) => Error
type Line = (locale: string, namespace: string, path: string) => string

type Milestone = 'placed' | 'awaiting_payment' | 'paid' | 'received' | 'cancelled' | 'refunded'
type Color = 'neutral' | 'info' | 'success' | 'warning' | 'danger' | 'accent'

interface PublicRecord {
  id: number
  code: string
  channels: string[]
  /** The ids of the channels of the notifications fixtures it tells (`options.notify_channels`). */
  notify?: number[]
  /** Words typed in the panel; until then the dictionary's. */
  names?: Record<string, string>
  descriptions?: Record<string, string>
  deleted?: boolean
}

interface StatusRecord {
  id: number
  key: string
  public: number
  color: Color
  milestone: Milestone | null
  active: boolean
  deleted: boolean
  names?: Record<string, string>
}

interface ItemRecord {
  id: number
  parent: number | null
  name: string
  sku: string
  price: number
  qty: number
  refunded_qty: number
  discount: number
  label: string | null
  /** The variant chosen, as the line's label shows it: «Size: M». */
  options?: string | null
  /** A gift of a rule: an edit may only take it out (§37.4). */
  gift?: boolean
  /** Taken out by an edit (§37.4): the line stays for the units returned of it. */
  removed?: boolean
  /** The edit the line came with; 0 — placed with the order. */
  revision?: number
}

interface HistoryRecord {
  key: string
  kind: string
  at: string
  source: string
  admin: string | null
  note: string | null
  from?: number | null
  to?: number | null
  milestone?: Milestone
  reference?: string | null
  amount?: number
  units?: number
  /** A refund's record (decision 98): its id, what it gave back, and its reversal. */
  refund_id?: number
  tenders?: [number, number][]
  items?: [number, number][]
  reversed?: { at: string; reason: string | null } | null
  /** A message sent again by hand (decision 99): its event. */
  event?: string
  /** An edit of the lines (§38.2), as the timeline shows it. */
  edit?: Record<string, unknown>
}

interface OrderRecord {
  id: number
  number: string
  placed_at: string
  status: number
  guest: boolean
  first_name: string
  last_name: string | null
  phone: string | null
  email: string | null
  recipient: { first_name: string; last_name: string | null; phone: string | null } | null
  comment: string | null
  manager_note: string | null
  items: ItemRecord[]
  adjustments: { id: number; label: string; amount: number }[]
  tenders: { id: number; label: string; amount: number; refunded: number }[]
  benefits: { key: string; label: string }[]
  refunded: number
  /** Σ money that came in (§36.1): the payment and the top-ups after it. */
  paid_total: number
  /** The edits of the lines (§38): how many, and when the last one was. */
  revision: number
  changed_at: string | null
  awaiting: boolean
  failed_at: string | null
  marks: Partial<Record<Milestone, { at: string; source: string; reference: string | null }>>
  history: HistoryRecord[]
  delivery: { method: string; summary: string } | null
  payment: { method: string; summary: string } | null
}

const day = (offset: number, hour = 10) =>
  new Date(Date.UTC(2026, 9, 28 + offset, hour, 20)).toISOString().replace('.000Z', '+00:00')

const money = (minor: number) =>
  new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(minor / 100)

const publics: PublicRecord[] = [
  { id: 1, code: 'accepted', channels: [] },
  { id: 2, code: 'in_progress', channels: [] },
  { id: 3, code: 'shipped', channels: ['Buyers: shipped'] },
  { id: 4, code: 'completed', channels: [] },
  { id: 5, code: 'cancelled', channels: ['Buyers: cancelled'] },
]

const statuses: StatusRecord[] = [
  { id: 1, key: 'new', public: 1, color: 'info', milestone: null, active: true, deleted: false },
  {
    id: 2,
    key: 'awaiting_payment',
    public: 1,
    color: 'warning',
    milestone: null,
    active: true,
    deleted: false,
  },
  {
    id: 3,
    key: 'taken',
    public: 2,
    color: 'accent',
    milestone: null,
    active: true,
    deleted: false,
  },
  {
    id: 4,
    key: 'awaiting_call',
    public: 2,
    color: 'warning',
    milestone: null,
    active: true,
    deleted: false,
  },
  {
    id: 5,
    key: 'packing',
    public: 2,
    color: 'neutral',
    milestone: null,
    active: true,
    deleted: false,
  },
  {
    id: 6,
    key: 'handed_to_delivery',
    public: 3,
    color: 'info',
    milestone: null,
    active: true,
    deleted: false,
  },
  {
    id: 7,
    key: 'completed',
    public: 4,
    color: 'success',
    milestone: 'received',
    active: true,
    deleted: false,
  },
  {
    id: 8,
    key: 'cancelled',
    public: 5,
    color: 'danger',
    milestone: 'cancelled',
    active: true,
    deleted: false,
  },
]

/** `webx-orders::checkout.errors.phone` — what the server says of a phone it cannot read. */
const PHONE_WORDS = 'A phone is a + and digits, for example +800 1234 5678.'

const ITEMS = {
  a: { name: 'Item A', sku: 'ITEM-A', price: 4999 },
  b: { name: 'Item B', sku: 'ITEM-B', price: 1250 },
  c: { name: 'Item C', sku: 'ITEM-C', price: 700 },
  d: { name: 'Set D', sku: 'SET-D', price: 15000 },
}

let nextId = 1000

function item(kind: keyof typeof ITEMS, qty = 1, extra: Partial<ItemRecord> = {}): ItemRecord {
  return {
    id: ++nextId,
    parent: null,
    ...ITEMS[kind],
    qty,
    refunded_qty: 0,
    discount: 0,
    label: null,
    ...extra,
  }
}

/** A set with its two parts under it, as the cart had it. */
function set(): ItemRecord[] {
  const root = item('d')

  return [
    root,
    item('b', 1, { parent: root.id, price: 0 }),
    item('c', 2, { parent: root.id, price: 0 }),
  ]
}

const letters = 'ABCDEFGHIJKL'

function order(index: number, fields: Partial<OrderRecord>): OrderRecord {
  const id = index + 1
  const placed = day(-12 + index, 9 + (index % 8))

  return {
    id,
    number: `WX-${1000 + id}`,
    placed_at: placed,
    status: 1,
    guest: index % 3 !== 0,
    first_name: 'Buyer',
    last_name: letters[index] ?? null,
    phone: `+800 1234 ${String(5678 + index).padStart(4, '0')}`,
    email: `buyer-${(letters[index] ?? 'x').toLowerCase()}@example.com`,
    recipient: null,
    comment: null,
    manager_note: null,
    items: [item('a')],
    adjustments: [],
    tenders: [],
    benefits: [],
    refunded: 0,
    paid_total: 0,
    revision: 0,
    changed_at: null,
    awaiting: false,
    failed_at: null,
    marks: { placed: { at: placed, source: 'checkout', reference: null } },
    history: [
      {
        key: `h${id}-0`,
        kind: 'status',
        at: placed,
        source: 'checkout',
        admin: null,
        note: null,
        from: null,
        to: fields.status ?? 1,
      },
    ],
    delivery: null,
    payment: null,
    ...fields,
  }
}

/* The spread of §15: every public status, two awaiting payment (one failed), paid and waiting,
   cancelled unpaid and paid with a full refund, received with a partial one, a rule's discount
   with a gift, a tender of the wallet. */
const orders: OrderRecord[] = [
  order(0, {
    items: [item('a'), item('c', 2)],
    manager_note: 'Regular buyer: call only in the morning.',
  }),
  order(1, { status: 2, awaiting: true, marks: {} }),
  order(2, { status: 2, awaiting: true, failed_at: day(-9, 12) }),
  order(3, {
    status: 1,
    marks: {},
    items: [item('b', 3)],
    comment: 'Please call before delivery.',
  }),
  order(4, {
    status: 3,
    items: [...set(), item('c', 1, { label: 'Gift', price: 0, gift: true })],
  }),
  order(5, { status: 4, recipient: { first_name: 'Sam', last_name: 'Doe', phone: null } }),
  order(6, { status: 6, delivery: { method: 'courier', summary: 'Courier, tomorrow 10–14' } }),
  order(7, {
    status: 8,
    items: [item('a', 2), item('c', 1, { label: 'Gift', price: 0, gift: true })],
    adjustments: [{ id: 1, label: 'Autumn sale', amount: -1000 }],
  }),
  order(8, { status: 7, delivery: { method: 'pickup', summary: 'Pickup point' } }),
  order(9, { status: 5, tenders: [{ id: 1, label: 'Wallet', amount: 1500, refunded: 0 }] }),
  order(10, { status: 8 }),
  order(11, { status: 7, items: [item('a'), item('b', 2)] }),
]

/* The marks of the spread, set after the orders are made so their times follow the placing. */
function seedMarks(): void {
  const mark = (
    record: OrderRecord,
    key: Milestone,
    offset: number,
    reference: string | null = null,
  ) => {
    // A day per step after the placing, two hours into it.
    const at = new Date(Date.parse(record.placed_at) + offset * 86_400_000 + 7_200_000)

    record.marks[key] = {
      at: at.toISOString().replace('.000Z', '+00:00'),
      source: 'panel',
      reference,
    }
  }
  const at = (index: number) => orders[index] as OrderRecord

  for (const record of orders)
    record.marks.placed ??= { at: record.placed_at, source: 'checkout', reference: null }
  at(1).marks.awaiting_payment = { at: at(1).placed_at, source: 'checkout', reference: null }
  at(2).marks.awaiting_payment = { at: at(2).placed_at, source: 'checkout', reference: null }
  // Notes of the feed «Notes»: a manager's, an agent's `orders_note`, on the first order.
  at(0).history.push(
    {
      key: 'h1-note1',
      kind: 'note',
      at: day(-1, 11),
      source: 'panel',
      admin: 'Demo admin',
      note: 'Called the buyer: they asked to deliver after 18:00.\nThe courier knows.',
    },
    {
      key: 'h1-note2',
      kind: 'note',
      at: day(-1, 12),
      source: 'mcp',
      admin: 'Demo admin',
      note: 'The second item is reserved at the warehouse until Friday.',
    },
  )
  mark(at(3), 'paid', 0, 'TX-4410')
  mark(at(6), 'paid', 0)
  // Cancelled, paid and given back whole outside the site.
  mark(at(7), 'paid', 0)
  mark(at(7), 'cancelled', 1)
  mark(at(7), 'refunded', 2, 'R-77')
  at(7).refunded = total(at(7))
  at(7).history.push({
    key: 'h8-r',
    kind: 'refund',
    at: at(7).marks.refunded?.at ?? at(7).placed_at,
    source: 'panel',
    admin: 'Demo admin',
    note: 'Given back to the card by the bank: the buyer found the drill cheaper elsewhere.',
    reference: 'R-77',
    amount: total(at(7)),
    refund_id: 1,
    tenders: [],
    items: [],
  })
  mark(at(8), 'paid', 0)
  mark(at(8), 'received', 2)
  mark(at(10), 'cancelled', 0)
  at(10).history.push({
    key: 'h11-c',
    kind: 'milestone',
    at: day(-2, 16),
    source: 'panel',
    admin: 'Demo admin',
    note: 'The buyer asked to cancel',
    milestone: 'cancelled',
  })
  mark(at(11), 'paid', 0)
  mark(at(11), 'received', 1)
  // Received, one line given back.
  const returned = at(11).items[1] as ItemRecord
  returned.refunded_qty = 1
  at(11).refunded = returned.price
  const wrongAt = day(0, 11)
  at(11).history.push(
    {
      key: 'h12-r1',
      kind: 'refund',
      at: wrongAt,
      source: 'panel',
      admin: 'Demo admin',
      note: 'The second line came back broken.',
      reference: 'R-112',
      amount: total(at(11)),
      units: 1,
      refund_id: 2,
      tenders: [],
      items: [[returned.id, 1]],
      reversed: { at: day(0, 12), reason: 'Typed the whole order instead of one line.' },
    },
    {
      key: 'h12-rr',
      kind: 'refund_reversed',
      at: day(0, 12),
      source: 'panel',
      admin: 'Demo admin',
      note: 'Typed the whole order instead of one line.',
      amount: total(at(11)),
      refund_id: 2,
    },
    {
      key: 'h12-r2',
      kind: 'refund',
      at: day(0, 13),
      source: 'panel',
      admin: 'Demo admin',
      note: 'The second line came back broken.',
      reference: 'R-113',
      amount: returned.price,
      units: 1,
      refund_id: 3,
      tenders: [],
      items: [[returned.id, 1]],
    },
  )

  for (const record of orders) if (record.marks.paid) record.paid_total = payable(record)
  // Paid short by the gateway: «Top-up» in the list and the filter «Waiting for a top-up».
  at(6).paid_total -= 1500
  // Topped up over at the desk: «Give back».
  at(3).paid_total += 500
  at(3).history.push({
    key: 'h4-p',
    kind: 'payment',
    at: day(-7, 15),
    source: 'panel',
    admin: 'Demo admin',
    note: 'At the desk',
    reference: 'BANK-7',
    amount: 500,
  })
}

let refunds = 3
const nextRefund = () => ++refunds

const subtotal = (record: OrderRecord) =>
  record.items.reduce((sum, line) => sum + line.price * line.qty - line.discount, 0)
const adjustments = (record: OrderRecord) =>
  record.adjustments.reduce((sum, one) => sum + one.amount, 0)
const total = (record: OrderRecord) => subtotal(record) + adjustments(record)
const tendered = (record: OrderRecord) => record.tenders.reduce((sum, one) => sum + one.amount, 0)
const payable = (record: OrderRecord) => total(record) - tendered(record)

/* The balance of §36.1 as `Balance` counts it (decision 102): a refund never makes it owe. */
function balanceOf(record: OrderRecord) {
  const cancelled = Boolean(record.marks.cancelled)
  const due = cancelled ? 0 : Math.max(0, payable(record) - record.paid_total)
  const over = Math.max(0, record.paid_total - record.refunded - (cancelled ? 0 : payable(record)))
  const kind =
    over > 0 ? 'over' : due > 0 && record.paid_total > 0 ? 'topup' : due > 0 ? 'unpaid' : null

  return { due, over, kind }
}

/** The badge and the line of the card: only what says more than «to pay». */
function balanceLine(record: OrderRecord) {
  const { due, over, kind } = balanceOf(record)

  if (kind !== 'topup' && kind !== 'over') return null

  const minor = kind === 'over' ? over : due

  return { kind, amount: money(minor), amount_minor: minor }
}

/** `Consequences::balance()` — what the dialog of a top-up shows before and after it. */
function balanceAnswer(record: OrderRecord) {
  const { due, over, kind } = balanceOf(record)
  const tendersOver = 0
  const amounts = Object.entries({
    paid_total: record.paid_total,
    refunded_total: record.refunded,
    due,
    over,
    tenders_over: tendersOver,
  }).flatMap(([key, minor]) => [
    [key, minor],
    [`${key}_formatted`, money(minor)],
  ])

  return { kind, ...Object.fromEntries(amounts), balance: due - over }
}

/** What came in (§42.4): the whole, and with top-ups the first payment and the top-ups apart. */
function paidTotals(record: OrderRecord) {
  const topups = record.history.reduce(
    (sum, one) => sum + (one.kind === 'payment' ? (one.amount ?? 0) : 0),
    0,
  )

  return {
    paid: record.paid_total > 0 ? money(record.paid_total) : null,
    paid_first: topups > 0 && record.paid_total > topups ? money(record.paid_total - topups) : null,
    paid_topups: topups > 0 ? money(topups) : null,
    balance: balanceLine(record),
  }
}

/** The limit of a refund (§36.2): what came in over the order's cost is the buyer's too. */
const refundableOf = (record: OrderRecord) =>
  Math.max(0, Math.max(payable(record), record.paid_total) - record.refunded)

seedMarks()

/* -------------------------------------------------------------------------------- words ----- */

/** A status's name in the panel's language: what was typed, else the dictionary's. */
function stageName(status: StatusRecord, locale: string, line: Line): string {
  return (
    status.names?.[locale] ??
    status.names?.en ??
    (status.key ? line(locale, 'webx-orders', `statuses.internal.${status.key}`) : '')
  )
}

/** Channel ids as the server keeps them: whole, positive, each once. */
function ids(value: unknown): number[] {
  if (!Array.isArray(value)) return []

  return [...new Set(value.map(Number).filter((id) => Number.isInteger(id) && id > 0))]
}

function groupName(group: PublicRecord, locale: string, line: Line): string {
  return (
    group.names?.[locale] ??
    group.names?.en ??
    line(locale, 'webx-orders', `statuses.public.${group.code}.name`)
  )
}

function statusOf(id: number, locale: string, line: Line) {
  const status = statuses.find((one) => one.id === id)

  if (!status) return null

  const group = publics.find((one) => one.id === status.public) as PublicRecord

  return {
    id: status.id,
    name: stageName(status, locale, line),
    color: status.color,
    milestone: status.milestone,
    active: status.active,
    deleted: status.deleted,
    public: {
      id: group.id,
      code: group.code,
      name: groupName(group, locale, line),
    },
  }
}

function grouped(locale: string, line: Line) {
  return publics.map((group) => ({
    id: group.id,
    code: group.code,
    name: groupName(group, locale, line),
    deleted: group.deleted ?? false,
    statuses: statuses
      .filter((one) => one.public === group.id)
      // A status inside its group: the group is the parent, not a field of it.
      .map((one) => ({ ...statusOf(one.id, locale, line), public: undefined })),
  }))
}

/* --------------------------------------------------------------------------------- list ----- */

const name = (record: OrderRecord) =>
  [record.first_name, record.last_name].filter(Boolean).join(' ')

/** Where the order came from: the source of its first step (§29). */
const sourceOf = (record: OrderRecord) => record.marks.placed?.source ?? record.history[0]?.source

/** The lines the order shows: one an edit took out whole is history only (§37.4). */
const shown = (record: OrderRecord) =>
  record.items.filter((one) => one.parent === null && (!one.removed || one.qty > 0))

function row(record: OrderRecord, locale: string, line: Line) {
  const roots = shown(record)

  return {
    id: record.id,
    number: record.number,
    placed_at: record.placed_at,
    customer: { name: name(record), phone: record.phone, email: record.email },
    guest: record.guest,
    items: { first: roots[0]?.name ?? null, more: Math.max(0, roots.length - 1) },
    total: money(total(record)),
    payable: money(payable(record)),
    status: statusOf(record.status, locale, line),
    awaiting_payment: record.awaiting && !record.marks.cancelled,
    payment_failed: record.failed_at !== null && !record.marks.paid,
    paid_at: record.marks.paid?.at ?? null,
    received_at: record.marks.received?.at ?? null,
    cancelled_at: record.marks.cancelled?.at ?? null,
    refunded: record.refunded > 0,
    balance: balanceLine(record),
    edited: record.revision > 0,
    delivery: record.delivery?.summary ?? null,
    payment: record.payment?.summary ?? null,
    source: sourceOf(record),
  }
}

function inView(record: OrderRecord, view: string): boolean {
  const awaiting = record.awaiting && !record.marks.cancelled

  if (view === 'new') return record.status === 1
  if (view === 'awaiting') return awaiting
  if (view === 'failed') return awaiting && record.failed_at !== null

  return true
}

function matches(record: OrderRecord, query: URLSearchParams): boolean {
  const term = (query.get('search') ?? '').trim().toLowerCase()

  if (term !== '') {
    const digits = term.replace(/\D+/g, '')
    const words = term.split(/\s+/)
    const found =
      record.number.toLowerCase().includes(term) ||
      (record.email ?? '').includes(term) ||
      (digits.length >= 3 && (record.phone ?? '').replace(/\D+/g, '').includes(digits)) ||
      words.every((word) => name(record).toLowerCase().includes(word))

    if (!found) return false
  }

  const flag = (key: string) => (query.has(key) ? query.get(key) === '1' : null)
  const status = query.get('status')
  const group = query.get('public')

  if (status && String(record.status) !== status) return false
  if (group && String(statuses.find((one) => one.id === record.status)?.public) !== group)
    return false

  for (const [key, mark] of [
    ['paid', 'paid'],
    ['received', 'received'],
    ['cancelled', 'cancelled'],
  ] as const) {
    const wanted = flag(key)

    if (wanted !== null && Boolean(record.marks[mark]) !== wanted) return false
  }

  const refunded = flag('refunded')

  if (refunded !== null && record.refunded > 0 !== refunded) return false

  // «Payment differs» (§42.4): an order not paid yet owes its sum — that is not a top-up.
  const balance = query.get('balance')
  const kind = balanceOf(record).kind

  if (balance === 'due' && kind !== 'topup') return false
  if (balance === 'over' && kind !== 'over') return false
  if (query.get('delivery') && record.delivery?.method !== query.get('delivery')) return false
  if (query.get('source') && sourceOf(record) !== query.get('source')) return false

  const from = query.get('from')
  const to = query.get('to')

  if (from && record.placed_at.slice(0, 10) < from) return false
  if (to && record.placed_at.slice(0, 10) > to) return false

  return true
}

function list(query: URLSearchParams, locale: string, line: Line) {
  const filtered = orders.filter((record) => matches(record, query))
  const asked = query.get('view') ?? 'all'
  // `auto` — the list opened without a tab (decision 92): «New» while it holds any order.
  const view =
    asked === 'auto' ? (filtered.some((record) => inView(record, 'new')) ? 'new' : 'all') : asked
  const rows = filtered.filter((record) => inView(record, view))
  const sort = query.get('sort') ?? '-placed_at'
  const by = sort.replace(/^-/, '')
  const sign = sort.startsWith('-') ? -1 : 1

  rows.sort((a, b) => {
    const left = by === 'total' ? total(a) : a.placed_at
    const right = by === 'total' ? total(b) : b.placed_at

    return (left < right ? -1 : left > right ? 1 : 0) * sign || (a.id - b.id) * sign
  })

  const perPage = Number(query.get('per_page') ?? 25) || 25
  const page = Math.max(1, Number(query.get('page') ?? 1) || 1)
  const slice = rows.slice((page - 1) * perPage, page * perPage)

  return {
    data: slice.map((record) => row(record, locale, line)),
    current_page: page,
    last_page: Math.max(1, Math.ceil(rows.length / perPage)),
    per_page: perPage,
    total: rows.length,
    from: slice.length > 0 ? (page - 1) * perPage + 1 : null,
    to: slice.length > 0 ? (page - 1) * perPage + slice.length : null,
    counts: {
      all: filtered.length,
      new: filtered.filter((record) => inView(record, 'new')).length,
      awaiting: filtered.filter((record) => inView(record, 'awaiting')).length,
      failed: filtered.filter((record) => inView(record, 'failed')).length,
    },
    view,
  }
}

/* ------------------------------------------------------------------------------ summary ----- */

/** The figures over the list (decision 93), counted as `OrdersSummary` counts them. */
function summary() {
  // Calendar days with today counted in (decision 95): 1 is today from midnight.
  const midnight = new Date()
  midnight.setHours(0, 0, 0, 0)
  const placedSince = (days: number) => {
    const from = new Date(midnight)
    from.setDate(from.getDate() - (days - 1))

    return orders.filter((record) => Date.parse(record.placed_at) >= from.getTime())
  }
  // What came in less what went back (§36.2): a top-up and an overpayment show in it.
  const revenueOf = (record: OrderRecord) => record.paid_total - record.refunded
  const periods = [1, 7, 30].map((days) => {
    const placed = placedSince(days)
    const paid = placed.filter((record) => record.marks.paid)
    const revenue = paid.reduce((sum, record) => sum + revenueOf(record), 0)
    const average = paid.length > 0 ? Math.trunc(revenue / paid.length) : 0

    return {
      days,
      by_currency:
        placed.length === 0
          ? []
          : [
              {
                currency: 'EUR',
                placed: placed.length,
                paid: paid.length,
                revenue,
                revenue_formatted: money(revenue),
                average,
                average_formatted: money(average),
              },
            ],
    }
  })
  const waiting = orders.filter((record) => record.awaiting && !record.marks.cancelled)
  const topups = orders.filter((record) => balanceOf(record).kind === 'topup')
  const today = new Date()
  const daily = Array.from({ length: 30 }, (_, index) => {
    const at = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 29 + index)
    const date = `${at.getFullYear()}-${String(at.getMonth() + 1).padStart(2, '0')}-${String(at.getDate()).padStart(2, '0')}`

    return {
      date,
      orders: orders.filter((record) => record.placed_at.slice(0, 10) === date).length,
    }
  })

  return {
    awaiting_payment: waiting.length,
    payment_failed: waiting.filter((record) => record.failed_at !== null).length,
    paid_status_waits: orders.filter((record) => record.marks.paid && record.status === 1).length,
    awaiting_by_currency:
      waiting.length === 0
        ? []
        : [
            {
              currency: 'EUR',
              orders: waiting.length,
              amount: waiting.reduce((sum, record) => sum + payable(record), 0),
              amount_formatted: money(waiting.reduce((sum, record) => sum + payable(record), 0)),
            },
          ],
    awaiting_topup: topups.length,
    topup_by_currency:
      topups.length === 0
        ? []
        : [
            {
              currency: 'EUR',
              orders: topups.length,
              amount: topups.reduce((sum, record) => sum + balanceOf(record).due, 0),
              amount_formatted: money(
                topups.reduce((sum, record) => sum + balanceOf(record).due, 0),
              ),
            },
          ],
    periods,
    daily,
  }
}

/** An opened row of the list (decision 94), as `OrderCard::glance()` answers it. */
function glance(record: OrderRecord) {
  const roots = shown(record)
  const notes = record.history.filter((one) => one.kind === 'note')
  const last = notes[notes.length - 1]

  return {
    id: record.id,
    items: roots.map((one) => ({
      id: one.id,
      name: one.name,
      options: one.options ?? null,
      qty: one.qty,
      total: money(one.price * one.qty - one.discount),
    })),
    count: {
      lines: roots.length,
      units: roots.reduce((sum, one) => sum + one.qty, 0),
      refunded_units: roots.reduce((sum, one) => sum + one.refunded_qty, 0),
    },
    totals: {
      subtotal: money(subtotal(record)),
      adjustments: record.adjustments.length > 0 ? money(adjustments(record)) : null,
      total: money(total(record)),
      tenders: record.tenders.length > 0 ? money(tendered(record)) : null,
      payable: money(payable(record)),
      refunded: record.refunded > 0 ? money(record.refunded) : null,
      ...paidTotals(record),
    },
    paid: Boolean(record.marks.paid),
    items_revision: record.revision,
    awaiting_payment: record.awaiting && !record.marks.cancelled,
    source: record.marks.placed?.source ?? null,
    customer: { name: name(record), phone: record.phone, email: record.email },
    recipient: record.recipient
      ? {
          name: [record.recipient.first_name, record.recipient.last_name].filter(Boolean).join(' '),
          phone: record.recipient.phone,
        }
      : null,
    comment: record.comment,
    manager_note: record.manager_note,
    last_note: last
      ? {
          id: notes.length,
          body: last.note ?? '',
          source: last.source,
          author: last.admin ? { id: 1, name: last.admin, avatar: null } : null,
          created_at: last.at,
        }
      : null,
    blocks: [
      ...(record.delivery ? [{ key: 'delivery', summary: record.delivery.summary }] : []),
      ...(record.payment ? [{ key: 'payment', summary: record.payment.summary }] : []),
    ],
    milestones: Object.fromEntries(
      Object.entries(record.marks).map(([key, mark]) => [key, mark?.at ?? null]),
    ),
  }
}

/* --------------------------------------------------------------------------------- card ----- */

function lines(record: OrderRecord, parent: number | null): unknown[] {
  return record.items
    .filter((one) => one.parent === parent && (!one.removed || one.qty > 0))
    .map((one) => ({
      id: one.id,
      name: one.name,
      sku: one.sku,
      options: one.options ?? null,
      image: null,
      label: one.label,
      price: money(one.price),
      qty: one.qty,
      refunded_qty: one.refunded_qty,
      total: money(one.price * one.qty - one.discount),
      discounts: [],
      children: lines(record, one.id),
      generated: Boolean(one.gift),
      part: one.parent !== null && !one.gift,
      removed: Boolean(one.removed),
      revision: one.revision ?? 0,
    }))
}

function timeline(record: OrderRecord, locale: string, line: Line) {
  const statusName = (id: number | null | undefined) =>
    id ? (statusOf(id, locale, line)?.name ?? null) : null
  const fromHistory = record.history
    .filter((one) => one.kind !== 'milestone')
    .map((one) => {
      const to = one.to ? statusOf(one.to, locale, line) : null
      const from = one.from ? statusOf(one.from, locale, line) : null

      return {
        ...one,
        from: statusName(one.from),
        to: to?.name ?? null,
        to_color: to?.color ?? null,
        public:
          one.kind === 'status' && from?.public?.id !== to?.public?.id
            ? (to?.public?.name ?? null)
            : null,
        amount: one.amount !== undefined ? money(one.amount) : undefined,
        amount_minor: one.kind === 'payment' ? one.amount : undefined,
        reversed: one.kind === 'refund' ? Boolean(one.reversed) : undefined,
        revision: one.kind === 'items' ? one.edit : undefined,
        edit: undefined,
        event_label:
          one.kind === 'notification_sent' && one.event
            ? line(
                locale,
                'webx-orders',
                `notifications.events.${one.event.replace('orders.', '')}`,
              )
            : undefined,
        milestone:
          one.kind === 'milestone_removed' && one.milestone
            ? {
                key: one.milestone,
                label: line(locale, 'webx-orders', `module.milestones.${one.milestone}`),
              }
            : undefined,
      }
    })
  const marks = Object.entries(record.marks).map(([key, mark]) => ({
    key: `m${record.id}-${key}`,
    kind: 'milestone',
    at: mark.at,
    source: mark.source,
    admin: mark.source === 'panel' ? 'Demo admin' : null,
    note:
      record.history.find((one) => one.kind === 'milestone' && one.milestone === key)?.note ?? null,
    milestone: { key, label: line(locale, 'webx-orders', `module.milestones.${key}`) },
    reference: mark.reference,
  }))

  return [...marks, ...fromHistory].sort((a, b) =>
    a.at === b.at
      ? (a.kind === 'milestone' ? -1 : 0) - (b.kind === 'milestone' ? -1 : 0)
      : a.at < b.at
        ? -1
        : 1,
  )
}

function card(record: OrderRecord, locale: string, line: Line) {
  const paid = Boolean(record.marks.paid)
  const cancelled = Boolean(record.marks.cancelled)
  const refundable = refundableOf(record)
  const tendersLeft = record.tenders.reduce((sum, one) => sum + one.amount - one.refunded, 0)
  const unitsLeft = record.items.reduce((sum, one) => sum + one.qty - one.refunded_qty, 0)

  return {
    order: {
      id: record.id,
      number: record.number,
      placed_at: record.placed_at,
      locale: 'en',
      currency: 'EUR',
      exponent: 2,
      guest: record.guest,
      user_id: record.guest ? null : 1,
      // The playground has no visitors module: the linking of §13.3 is not offered.
      visitor: record.guest
        ? null
        : {
            id: 1,
            name: `${record.first_name} ${record.last_name ?? ''}`.trim(),
            email: record.email,
          },
      status: statusOf(record.status, locale, line),
      statuses: grouped(locale, line),
      awaiting_payment: record.awaiting && !cancelled,
      payment_failed_at: paid ? null : record.failed_at,
      // The note of a mark lives in its history line here; the card has it on the mark.
      milestones: Object.fromEntries(
        Object.entries(record.marks).map(([key, mark]) => [
          key,
          {
            ...mark,
            note:
              record.history.find((one) => one.kind === 'milestone' && one.milestone === key)
                ?.note ?? null,
          },
        ]),
      ),
      items: lines(record, null),
      items_revision: record.revision,
      items_changed_at: record.changed_at,
      adjustments: record.adjustments.map((one) => ({
        ...one,
        source: 'rule#1',
        amount: money(one.amount),
      })),
      tenders: record.tenders.map((one) => ({
        id: one.id,
        label: one.label,
        source: 'wallet',
        amount: money(one.amount),
        amount_minor: one.amount,
        refunded: one.refunded > 0 ? money(one.refunded) : null,
        left_minor: one.amount - one.refunded,
      })),
      benefits: record.benefits,
      totals: {
        subtotal: money(subtotal(record)),
        adjustments: record.adjustments.length > 0 ? money(adjustments(record)) : null,
        total: money(total(record)),
        tenders: record.tenders.length > 0 ? money(tendered(record)) : null,
        payable: money(payable(record)),
        refunded: record.refunded > 0 ? money(record.refunded) : null,
        refundable: money(refundable),
        refundable_minor: refundable,
        ...paidTotals(record),
      },
      count: {
        lines: shown(record).length,
        units: shown(record).reduce((sum, one) => sum + one.qty, 0),
        refunded_units: shown(record).reduce((sum, one) => sum + one.refunded_qty, 0),
      },
      refunds: record.history
        .filter((one) => one.kind === 'refund')
        .map((one, index) => ({
          id: one.refund_id ?? index + 1,
          at: one.at,
          amount: money(one.amount ?? 0),
          reason: one.note,
          reference: one.reference ?? null,
          reversed: one.reversed ?? null,
        })),
      blocks: [record.delivery, record.payment].flatMap((block, index) =>
        block
          ? [
              {
                key: index === 0 ? 'delivery' : 'payment',
                summary: block.summary,
                value: { method: block.method },
              },
            ]
          : [],
      ),
      timeline: timeline(record, locale, line),
      // The note pinned over the feed (decision 91).
      manager_note: record.manager_note,
      // The notes are the history's lines of the kind `note`, as the server reads them.
      notes: record.history
        .filter((one) => one.kind === 'note')
        .map((one, index) => ({
          id: index + 1,
          body: one.note ?? '',
          source: one.source,
          author: one.admin ? { id: 1, name: one.admin, avatar: null } : null,
          created_at: one.at,
        })),
      // Nothing really goes out of the playground; the log is what the module would have written.
      notifications: logOf(record).map((one) => ({
        id: one.id,
        event: one.event,
        event_label: line(
          locale,
          'webx-orders',
          `notifications.events.${one.event.replace('orders.', '')}`,
        ),
        channel_id: one.channel_id,
        channel: one.channel,
        provider: 'email',
        recipient: one.recipient,
        recipient_label: null,
        status: one.status,
        trap: null,
        status_label: line(locale, 'webx-notifications', `module.statuses.${one.status}`),
        reason: null,
        reason_label: null,
        error: one.status === 'failed' ? 'Connection refused by the mail server.' : null,
        created_at: one.created_at,
        sent_at: one.status === 'sent' ? one.created_at : null,
        resendable: true,
        manual: one.manual,
        manual_by: one.manual_by,
      })),
      actions: {
        status: true,
        paid: !paid && !cancelled,
        received: !record.marks.received && !cancelled,
        cancel: !cancelled && !record.marks.refunded,
        details: true,
        note: true,
        refund: refundable > 0 || tendersLeft > 0 || unitsLeft > 0,
        refund_reverse: true,
        payment: paid && !cancelled,
        edit_items: true,
        edit_items_reason:
          record.marks.received || cancelled || record.marks.refunded
            ? line(locale, 'webx-orders', 'panel.items-refusals.state')
            : null,
        visitor: false,
        visitor_search: false,
        resend: true,
        notify: true,
        notify_reason: record.email
          ? null
          : line(locale, 'webx-orders', 'panel.notify-refusals.no_email'),
      },
    },
    values: {
      'customer.first_name': record.first_name,
      'customer.last_name': record.last_name,
      'customer.phone': record.phone,
      'customer.email': record.email,
      'recipient.first_name': record.recipient?.first_name ?? null,
      'recipient.last_name': record.recipient?.last_name ?? null,
      'recipient.phone': record.recipient?.phone ?? null,
      comment: record.comment,
    },
  }
}

/* -------------------------------------------------------------------------------- moves ----- */

/** Where a cancellation takes the order (decision 88): the first active status that cancels. */
function cancelling(record: OrderRecord): StatusRecord | null {
  const current = statuses.find((one) => one.id === record.status)

  if (current?.milestone === 'cancelled') return null

  return statuses.find((one) => one.milestone === 'cancelled' && one.active && !one.deleted) ?? null
}

/** What the cancellation says of its move, as the dialog of a move says one. */
function cancelStatus(record: OrderRecord, locale: string, line: Line) {
  const to = cancelling(record)

  if (!to || record.marks.cancelled) return null

  return {
    id: to.id,
    name: statusOf(to.id, locale, line)?.name ?? '',
    ...plan(record, to, locale, line),
    milestone: null,
  }
}

/** The cancellation: the mark with its reason, then the move into the status that cancels. */
function cancelOrder(record: OrderRecord, reason: string | null): void {
  const to = cancelling(record)

  mark(record, 'cancelled', null, reason)

  if (to) {
    record.history.push({
      key: `h${record.id}-${record.history.length}`,
      kind: 'status',
      at: new Date().toISOString(),
      source: 'panel',
      admin: 'Demo admin',
      note: reason,
      from: record.status,
      to: to.id,
    })
    record.status = to.id
  }
}

function plan(record: OrderRecord, to: StatusRecord, locale: string, line: Line) {
  const from = statuses.find((one) => one.id === record.status) as StatusRecord
  const moved = from.id !== to.id
  const publicChanged = moved && from.public !== to.public
  const group = publics.find((one) => one.id === to.public) as PublicRecord
  let milestone: Milestone | null = null
  let refusal: string | null = !to.active ? 'inactive' : to.deleted ? 'deleted' : null

  if (refusal === null && moved && to.milestone && !record.marks[to.milestone]) {
    if (record.marks.cancelled && to.milestone !== 'cancelled') refusal = 'cancelled'
    milestone = to.milestone
  }

  return {
    moved,
    public_changed: publicChanged,
    public: groupName(group, locale, line),
    milestone: milestone
      ? { key: milestone, label: line(locale, 'webx-orders', `module.milestones.${milestone}`) }
      : null,
    channels: publicChanged ? group.channels : [],
    refusal,
    message: refusal ? line(locale, 'webx-orders', `panel.refusals.${refusal}`) : null,
  }
}

function move(
  record: OrderRecord,
  to: StatusRecord,
  note: string | null,
  outcome: ReturnType<typeof plan>,
): void {
  if (!outcome.moved) return

  record.history.push({
    key: `h${record.id}-${record.history.length}`,
    kind: 'status',
    at: new Date().toISOString(),
    source: 'panel',
    admin: 'Demo admin',
    note,
    from: record.status,
    to: to.id,
  })
  record.status = to.id

  if (outcome.milestone) mark(record, outcome.milestone.key, null, note)
}

function mark(
  record: OrderRecord,
  key: Milestone,
  reference: string | null,
  note: string | null,
): void {
  record.marks[key] = { at: new Date().toISOString(), source: 'panel', reference }

  if (note) {
    record.history.push({
      key: `h${record.id}-n${record.history.length}`,
      kind: 'milestone',
      at: record.marks[key].at,
      source: 'panel',
      admin: 'Demo admin',
      note,
      milestone: key,
    })
  }

  if (key === 'paid') {
    record.awaiting = false
    record.paid_total += balanceOf(record).due
  }
  if (key === 'cancelled') for (const tender of record.tenders) tender.refunded = tender.amount
}

/* ---------------------------------------------------------------------------- new order ----- */

/** What a manager finds for a line (§29.2): the shirt has a size to pick, the bench no price. */
const CATALOGUE: {
  ref: number
  name: string
  sku: string
  hint: string
  price: number
  sizes?: string[]
}[] = [
  { ref: 101, name: 'Cordless drill', sku: 'DRILL-1', hint: 'Tools', price: 12900 },
  { ref: 102, name: 'Drill bits, set of 10', sku: 'BITS-10', hint: 'Tools', price: 1490 },
  {
    ref: 103,
    name: 'Work shirt',
    sku: 'SHIRT-W',
    hint: 'Clothing',
    price: 2500,
    sizes: ['S', 'M', 'L', 'XL'],
  },
  { ref: 104, name: 'Safety gloves', sku: 'GLOVES', hint: 'Clothing', price: 650 },
  { ref: 105, name: 'Workbench', sku: 'BENCH-1', hint: 'Furniture', price: 0 },
]

/** The orders a form's token made: a second press is the first order. */
const placedByKey = new Map<string, OrderRecord>()

/** The lines counted as the order would count them; a refused one is 422 under its index. */
function counted(value: unknown, fail: Fail) {
  const refuse = (field: string, message: string) =>
    fail(422, message, undefined, { [field]: [message] })

  const sent = Array.isArray(value) ? (value as Record<string, unknown>[]) : []

  if (sent.length === 0) throw refuse('items', 'Add at least one product.')

  const picked = sent.map((one, index) => {
    const product = CATALOGUE.find((entry) => entry.ref === Number(one.ref))

    if (!product || product.price === 0)
      throw refuse(`items.${index}.ref`, 'This product cannot be bought now.')

    const options = (one.options ?? {}) as Record<string, unknown>
    const size = product.sizes?.find((entry) => entry.toLowerCase() === options.size)

    if (product.sizes && !size) throw refuse(`items.${index}.options`, 'Choose a size.')

    return { product, size: size ?? null, qty: Math.max(1, Number(one.qty ?? 1) || 1) }
  })

  const lines = picked.map(({ product, size, qty }, index) => ({
    id: index + 1,
    name: product.name,
    qty,
    options_label: size ? `Size: ${size}` : null,
    price_formatted: money(product.price),
    line_total_formatted: money(product.price * qty),
    available: true,
    label: null,
    children: [],
  }))
  const sum = picked.reduce((all, one) => all + one.product.price * one.qty, 0)

  return {
    picked,
    preview: {
      orderable: true,
      message: null,
      totals: {
        lines,
        subtotal_formatted: money(sum),
        adjustments: [],
        total_formatted: money(sum),
        tenders: [],
        payable: sum,
        payable_formatted: money(sum),
        warnings: [],
      },
    },
  }
}

/* ------------------------------------------------------------------------- edit of lines ----- */

/** `:name` of a dictionary line filled in — the fixture has no translator of its own. */
const fill = (words: string, values: Record<string, string | number>) =>
  Object.entries(values).reduce(
    (all, [key, value]) => all.replaceAll(`:${key}`, String(value)),
    words,
  )

/** The totals of §38.1 of an order as it stands, minor units with the string beside. */
function editTotals(record: OrderRecord) {
  const { due, over } = balanceOf(record)

  return Object.fromEntries(
    Object.entries({
      subtotal: subtotal(record),
      adjustments_total: adjustments(record),
      total: total(record),
      tenders_total: tendered(record),
      payable: payable(record),
      balance: due - over,
    }).flatMap(([key, minor]) => [
      [key, minor],
      [`${key}_formatted`, money(minor)],
    ]),
  ) as Record<string, number | string>
}

/**
 * `Orders::editItems()` of the fixture (§37): the lines placed keep their price and a discount in
 * proportion, a product added comes at the catalogue's price, a set changes whole, a gift only
 * goes, a line keeps its units returned. The answer is `Consequences::itemsEdit()`; `apply` writes it.
 */
function editPlan(
  record: OrderRecord,
  body: Record<string, unknown>,
  locale: string,
  line: Line,
  fail: Fail,
) {
  const words = (path: string, values: Record<string, string | number> = {}) =>
    fill(line(locale, 'webx-orders', path), values)
  const before = editTotals(record)
  const qty = (body.qty ?? {}) as Record<string, number>
  const remove = Array.isArray(body.remove) ? body.remove.map(Number) : []
  const add = Array.isArray(body.add) ? (body.add as Record<string, unknown>[]) : []
  const refused = (refusal: string, field: string | null = null) => ({
    answer: {
      changes: [],
      adjustments: [],
      before,
      after: before,
      balance: balanceAnswer(record),
      warnings: [],
      refusal,
      field,
      message: words(`panel.items-refusals.${refusal}`),
      revision: null,
      dry_run: true,
    },
    apply: null,
  })

  if (Number(body.revision ?? -1) !== record.revision) return refused('stale', 'revision')
  if (record.marks.received || record.marks.cancelled || record.marks.refunded)
    return refused('state')
  if (add.length === 0 && remove.length === 0 && Object.keys(qty).length === 0)
    return refused('nothing')

  const targets = new Map<number, { qty: number; out: boolean; field: string }>()

  for (const [id, to] of Object.entries(qty))
    targets.set(Number(id), { qty: Number(to), out: Number(to) === 0, field: `qty.${id}` })
  remove.forEach((id, index) => targets.set(id, { qty: 0, out: true, field: `remove.${index}` }))

  for (const [id, target] of targets) {
    const one = record.items.find((entry) => entry.id === id)

    if (!one || one.removed) return refused('foreign', target.field)
    if (one.parent !== null) return refused('child', target.field)
    if (target.out) target.qty = one.refunded_qty
    else if (one.gift && target.qty !== one.qty) return refused('generated', target.field)
    else if (target.qty < one.refunded_qty) return refused('below_refunded', target.field)
  }

  // The products added, counted as «New order» counts them; a refusal points at `add.N`.
  let picked: ReturnType<typeof counted>['picked'] = []

  if (add.length > 0) {
    try {
      picked = counted(add, fail).picked
    } catch (error) {
      const found = (error as { errors?: Record<string, string[]> }).errors ?? {}

      throw fail(
        422,
        Object.values(found)[0]?.[0] ?? 'Not accepted.',
        undefined,
        Object.fromEntries(
          Object.entries(found).map(([key, value]) => [key.replace(/^items/, 'add'), value]),
        ),
      )
    }
  }

  const after: OrderRecord = { ...record, items: record.items.map((one) => ({ ...one })) }
  const changes: Record<string, unknown>[] = []
  const nextRevision = record.revision + 1

  for (const one of after.items.filter((entry) => entry.parent === null)) {
    const target = targets.get(one.id)

    if (!target || (target.qty === one.qty && !target.out)) continue

    const from = { qty: one.qty, total: one.price * one.qty - one.discount }
    const ratio = one.qty > 0 ? target.qty / one.qty : 0

    one.discount = Math.round(one.discount * ratio)
    one.qty = target.qty
    if (target.out) one.removed = true

    for (const part of after.items.filter((entry) => entry.parent === one.id)) {
      part.qty = target.out ? part.refunded_qty : Math.round(part.qty * ratio)
    }

    const to = one.price * one.qty - one.discount

    changes.push({
      op: target.out ? 'remove' : 'qty',
      item_id: one.id,
      name: one.name,
      options_label: one.options ?? null,
      price: one.price,
      price_formatted: money(one.price),
      qty_from: from.qty,
      qty_to: one.qty,
      total_from: from.total,
      total_from_formatted: money(from.total),
      total_to: to,
      total_to_formatted: money(to),
    })
  }

  for (const { product, size, qty: units } of picked) {
    const options = size ? `Size: ${size}` : null
    const same = after.items.find(
      (one) =>
        one.parent === null &&
        !one.removed &&
        !one.gift &&
        one.sku === product.sku &&
        one.price === product.price &&
        (one.options ?? null) === options,
    )

    if (same) {
      const from = same.qty

      same.qty += units
      changes.push({
        op: 'qty',
        item_id: same.id,
        name: same.name,
        options_label: options,
        price: same.price,
        price_formatted: money(same.price),
        qty_from: from,
        qty_to: same.qty,
        total_from: same.price * from,
        total_from_formatted: money(same.price * from),
        total_to: same.price * same.qty,
        total_to_formatted: money(same.price * same.qty),
      })

      continue
    }

    after.items.push({
      id: ++nextId,
      parent: null,
      name: product.name,
      sku: product.sku,
      price: product.price,
      qty: units,
      refunded_qty: 0,
      discount: 0,
      label: null,
      options,
      revision: nextRevision,
    })
    changes.push({
      op: 'add',
      item_id: null,
      name: product.name,
      options_label: options,
      price: product.price,
      price_formatted: money(product.price),
      qty_from: 0,
      qty_to: units,
      total_from: 0,
      total_from_formatted: money(0),
      total_to: product.price * units,
      total_to_formatted: money(product.price * units),
    })
  }

  if (!after.items.some((one) => one.parent === null && !one.removed && one.qty > one.refunded_qty))
    return refused('empty')

  const balance = balanceAnswer(after)
  const totals = editTotals(after)
  const warnings: { code: string; message: string }[] = []

  if (record.marks.paid) {
    const kind = Number(balance.over) > 0 ? 'over' : Number(balance.due) > 0 ? 'due' : 'even'

    warnings.push({
      code: 'paid',
      message: words(`panel.items-warnings.paid-${kind}`, {
        paid: money(record.paid_total),
        payable: String(totals.payable_formatted),
        due: String(balance.due_formatted),
        over: String(balance.over_formatted),
      }),
    })
  }

  if (subtotal(after) !== subtotal(record)) {
    for (const one of record.adjustments.filter((entry) => entry.amount < 0)) {
      warnings.push({
        code: 'adjustment_kept',
        message: words('panel.items-warnings.adjustment_kept', {
          label: one.label,
          subtotal: money(subtotal(record)),
        }),
      })
    }
  }

  const answer = {
    changes,
    adjustments: [],
    before,
    after: totals,
    balance,
    warnings,
    refusal: null,
    field: null,
    message: null,
    revision: null,
    dry_run: true,
  }

  return {
    answer,
    apply: () => {
      const at = new Date().toISOString().replace(/\.\d+Z$/, '+00:00')

      changes
        .filter((change) => change.op === 'add')
        .forEach((change, index) => {
          change.item_id =
            after.items.filter((one) => one.revision === nextRevision)[index]?.id ?? null
        })
      record.items = after.items
      record.revision = nextRevision
      record.changed_at = at
      record.history.push({
        key: `h${record.id}-e${nextRevision}`,
        kind: 'items',
        at,
        source: 'panel',
        admin: 'Demo admin',
        note:
          typeof body.reason === 'string' && body.reason.trim() !== '' ? body.reason.trim() : null,
        edit: {
          number: nextRevision,
          changes: changes.map((change) => ({
            op: change.op,
            item_id: change.item_id,
            name: change.name,
            options_label: change.options_label,
            price: change.price_formatted,
            qty_from: change.qty_from,
            qty_to: change.qty_to,
            total_from: change.total_from_formatted,
            total_to: change.total_to_formatted,
          })),
          total_from: String(before.total_formatted),
          total_to: String(totals.total_formatted),
          balance:
            Number(totals.balance) === 0
              ? null
              : {
                  kind: Number(totals.balance) > 0 ? 'due' : 'over',
                  amount: money(Math.abs(Number(totals.balance))),
                },
          message:
            typeof body.message === 'string' && body.message.trim() !== ''
              ? body.message.trim()
              : null,
        },
      })

      return { ...answer, revision: nextRevision, dry_run: false }
    },
  }
}

/* ------------------------------------------------------------------------------- routes ----- */

/**
 * The log of «Notifications» of an order (decision 99), as the notifications module would have
 * it: the letter «Order accepted» to the buyer when there is an e-mail, the managers' «New order».
 * Nothing really goes out of the playground; a row sent again by hand is a row of its own.
 */
interface DeliveryRecord {
  id: number
  event: string
  channel_id: number
  channel: string
  recipient: string
  status: 'sent' | 'failed'
  created_at: string
  manual: boolean
  manual_by: string | null
}

/** The channels of the fixture that write to the buyer, by event; the others have none. */
const buyerChannels: Record<string, { id: number; name: string; subject: string }> = {
  'orders.placed': {
    id: 101,
    name: 'Order accepted — to the buyer',
    subject: 'Order :number accepted',
  },
  'orders.paid': {
    id: 102,
    name: 'Payment received — to the buyer',
    subject: 'Order :number is paid',
  },
}

const byHand = [
  'orders.placed',
  'orders.public_status',
  'orders.paid',
  'orders.payment_failed',
  'orders.received',
  'orders.cancelled',
  'orders.refunded',
]

const logs = new Map<number, DeliveryRecord[]>()
let nextDelivery = 500

function logOf(record: OrderRecord): DeliveryRecord[] {
  let log = logs.get(record.id)

  if (!log) {
    log = [
      {
        id: nextDelivery++,
        event: 'orders.placed',
        channel_id: 103,
        channel: 'New order — to the shop',
        recipient: 'shop@example.com',
        status: 'sent',
        created_at: record.placed_at,
        manual: false,
        manual_by: null,
      },
    ]

    if (record.email) {
      log.push({
        id: nextDelivery++,
        event: 'orders.placed',
        channel_id: 101,
        channel: buyerChannels['orders.placed']!.name,
        recipient: record.email,
        // One letter the mail server would not take, to have a failed row to send again.
        status: record.id % 2 === 0 ? 'failed' : 'sent',
        created_at: record.placed_at,
        manual: false,
        manual_by: null,
      })
    }

    logs.set(record.id, log)
  }

  return log
}

function basisOf(record: OrderRecord, event: string): boolean {
  switch (event) {
    case 'orders.placed':
    case 'orders.public_status':
      return true
    case 'orders.paid':
      return Boolean(record.marks.paid)
    case 'orders.payment_failed':
      return Boolean(record.failed_at) && !record.marks.paid
    case 'orders.received':
      return Boolean(record.marks.received)
    case 'orders.cancelled':
      return Boolean(record.marks.cancelled)
    case 'orders.refunded':
      return record.refunded > 0
    default:
      return false
  }
}

/** Sent by hand a moment ago: a second press of the button is refused, as the server does. */
function recently(log: DeliveryRecord[], test: (one: DeliveryRecord) => boolean): boolean {
  return log.some(
    (one) => one.manual && test(one) && Date.now() - Date.parse(one.created_at) < 60_000,
  )
}

export function registerOrders(on: On, fail: Fail, line: Line): void {
  const find = (id: string) => {
    const record = orders.find((one) => one.id === Number(id))

    if (!record) throw fail(404, `Order ${id} is not found.`)

    return record
  }
  const target = (id: unknown) => {
    const status = statuses.find((one) => one.id === Number(id))

    if (!status)
      throw fail(422, 'There is no such status.', undefined, {
        status_id: ['There is no such status.'],
      })

    return status
  }
  const text = (value: unknown) =>
    typeof value === 'string' && value.trim() !== '' ? value.trim() : null

  on('GET', '/orders', ({ query, locale }) => list(query, locale, line))

  on('GET', '/orders/summary', () => ({ data: summary() }))

  on('GET', '/orders/(\\d+)/glance', ({ params }) => ({
    data: glance(find(params[0])),
  }))

  on('GET', '/orders/meta', ({ locale }) => ({
    data: {
      statuses: grouped(locale, line),
      methods: {
        delivery: [
          ...new Set(orders.flatMap((one) => (one.delivery ? [one.delivery.method] : []))),
        ].sort(),
        payment: [],
      },
      initial_status: 1,
      visitors: true,
    },
  }))

  on('GET', '/orders/(\\d+)', ({ params, locale }) => ({
    data: card(find(params[0]), locale, line),
  }))

  on('POST', '/orders/(\\d+)/status', ({ params, body, locale }) => {
    const record = find(params[0])
    const to = target(body.status_id)
    const outcome = plan(record, to, locale, line)

    if (body.dry_run) return { data: outcome }
    if (outcome.message)
      throw fail(422, outcome.message, undefined, { status_id: [outcome.message] })
    // A status that cancels is a cancellation, and wants its reason (§9.1).
    if (outcome.milestone?.key === 'cancelled' && !text(body.note))
      throw fail(422, 'Say why the order is cancelled.', undefined, {
        note: ['Say why the order is cancelled.'],
      })

    move(record, to, text(body.note), outcome)

    return { data: card(record, locale, line) }
  })

  on('POST', '/orders/(\\d+)/milestones', ({ params, body, locale }) => {
    const record = find(params[0])
    const key = body.milestone as Milestone

    if (record.marks.cancelled) {
      const said = line(locale, 'webx-orders', 'panel.refusals.cancelled')

      throw fail(422, said, undefined, { milestone: [said] })
    }

    if (!record.marks[key]) mark(record, key, text(body.reference), text(body.note))

    return { data: card(record, locale, line) }
  })

  on('POST', '/orders/(\\d+)/cancel', ({ params, body, locale }) => {
    const record = find(params[0])
    const reason = text(body.reason)

    if (body.dry_run) {
      const left = Math.max(0, record.paid_total - record.refunded)

      return {
        data: {
          was_paid: Boolean(record.marks.paid),
          money_to_refund: record.marks.paid ? left : 0,
          money_to_refund_text: record.marks.paid && left > 0 ? money(left) : null,
          tenders: record.tenders
            .filter((one) => one.amount > one.refunded)
            .map((one) => ({ label: one.label, amount: money(one.amount - one.refunded) })),
          outcome: record.marks.cancelled ? 'already_marked' : 'marked',
          status: cancelStatus(record, locale, line),
        },
      }
    }

    if (!reason)
      throw fail(422, 'Say why the order is cancelled.', undefined, {
        reason: ['Say why the order is cancelled.'],
      })
    if (!record.marks.cancelled) cancelOrder(record, reason)

    return { data: card(record, locale, line) }
  })

  on('POST', '/orders/(\\d+)/notes', ({ params, body, locale }) => {
    const record = find(params[0])
    const note = text(body.body)

    if (!note) throw fail(422, 'The body field is required.', undefined, { body: ['Required.'] })

    record.history.push({
      key: `h${record.id}-note${record.history.length}`,
      kind: 'note',
      at: new Date().toISOString(),
      source: 'panel',
      admin: 'Demo admin',
      note: note.slice(0, 1000),
    })

    return { data: card(record, locale, line) }
  })

  on('PUT', '/orders/(\\d+)/details', ({ params, body, locale }) => {
    const record = find(params[0])
    const values = (body.values ?? {}) as Record<string, unknown>
    const has = (key: string) => key in values
    const phone = text(values['customer.phone'])

    if (has('customer.phone') && phone && !/^\+?[0-9][0-9 ().-]*$/.test(phone)) {
      throw fail(422, PHONE_WORDS, undefined, {
        'customer.phone': [PHONE_WORDS],
      })
    }

    const email = has('customer.email') ? text(values['customer.email']) : record.email

    if (!(has('customer.phone') ? phone : record.phone) && !email) {
      const either = ['Enter a phone or an e-mail.']
      throw fail(422, either[0]!, undefined, { 'customer.phone': either, 'customer.email': either })
    }

    if (has('customer.first_name'))
      record.first_name = text(values['customer.first_name']) ?? record.first_name
    if (has('customer.last_name')) record.last_name = text(values['customer.last_name'])
    if (has('customer.phone')) record.phone = phone
    if (has('customer.email')) record.email = text(values['customer.email'])
    if (has('recipient.first_name')) {
      const first = text(values['recipient.first_name'])

      record.recipient = first
        ? {
            first_name: first,
            last_name: text(values['recipient.last_name']),
            phone: text(values['recipient.phone']),
          }
        : null
    }
    if (has('comment')) record.comment = text(values.comment)
    if (has('manager_note')) record.manager_note = text(values.manager_note)

    record.history.push({
      key: `h${record.id}-d${record.history.length}`,
      kind: 'details',
      at: new Date().toISOString(),
      source: 'panel',
      admin: 'Demo admin',
      note: null,
    })

    return { data: card(record, locale, line) }
  })

  on('POST', '/orders/(\\d+)/refunds', ({ params, body, locale }) => {
    const record = find(params[0])
    const minor = (value: unknown) => Math.round(Number(value ?? 0) * 100) || 0
    const amount = minor(body.amount)
    const tenders = Object.entries((body.tenders ?? {}) as Record<string, unknown>).map(
      ([id, value]) => [Number(id), minor(value)] as const,
    )
    const items = Object.entries((body.items ?? {}) as Record<string, unknown>).map(
      ([id, qty]) => [Number(id), Number(qty) || 0] as const,
    )
    const moneyLeft = refundableOf(record)
    let refusal: string | null = null
    let field: string | null = null

    if (amount > moneyLeft) [refusal, field] = ['amount', 'amount']

    for (const [id, value] of tenders) {
      const tender = record.tenders.find((one) => one.id === id)

      if (!refusal && (!tender || value > tender.amount - tender.refunded))
        [refusal, field] = ['tender', `tenders.${id}`]
    }

    for (const [id, qty] of items) {
      const one = record.items.find((each) => each.id === id)

      if (!refusal && (!one || qty > one.qty - one.refunded_qty))
        [refusal, field] = ['qty', `items.${id}`]
    }

    if (
      !refusal &&
      amount === 0 &&
      tenders.every(([, value]) => value === 0) &&
      items.every(([, qty]) => qty === 0)
    ) {
      ;[refusal, field] = ['empty', 'amount']
    }

    const tendersAfter =
      record.tenders.reduce((sum, one) => sum + one.amount - one.refunded, 0) -
      tenders.reduce((sum, [, value]) => sum + value, 0)
    const full =
      (amount > 0 || tenders.some(([, value]) => value > 0)) &&
      moneyLeft - amount === 0 &&
      tendersAfter === 0
    const message = refusal
      ? line(locale, 'webx-orders', `panel.refund-refusals.${refusal}`).replace(
          ':left',
          money(moneyLeft),
        )
      : null

    if (body.dry_run) {
      return {
        data: {
          money_left: moneyLeft,
          money_left_text: money(moneyLeft),
          tenders_left: Object.fromEntries(
            record.tenders.map((one) => [one.id, one.amount - one.refunded]),
          ),
          items_left: Object.fromEntries(
            record.items.map((one) => [one.id, one.qty - one.refunded_qty]),
          ),
          full,
          milestone:
            full && !record.marks.refunded
              ? {
                  key: 'refunded',
                  label: line(locale, 'webx-orders', 'module.milestones.refunded'),
                }
              : null,
          refusal,
          field,
          message,
        },
      }
    }

    if (refusal && field && message) throw fail(422, message, undefined, { [field]: [message] })

    record.refunded += amount
    for (const [id, value] of tenders) {
      const tender = record.tenders.find((one) => one.id === id)

      if (tender) tender.refunded += value
    }
    for (const [id, qty] of items) {
      const one = record.items.find((each) => each.id === id)

      if (one) one.refunded_qty += qty
    }
    record.history.push({
      key: `h${record.id}-r${record.history.length}`,
      kind: 'refund',
      at: new Date().toISOString(),
      source: 'panel',
      admin: 'Demo admin',
      note: text(body.reason),
      reference: text(body.reference),
      amount,
      units: items.reduce((sum, [, qty]) => sum + qty, 0),
      refund_id: nextRefund(),
      tenders: tenders
        .filter(([, value]) => value > 0)
        .map(([id, value]): [number, number] => [id, value]),
      items: items.filter(([, qty]) => qty > 0).map(([id, qty]): [number, number] => [id, qty]),
    })
    if (full && !record.marks.refunded) mark(record, 'refunded', text(body.reference), null)

    return { data: card(record, locale, line) }
  })

  /* A top-up of a paid order (§36.3): `dry_run` answers the balance after it. */
  on('POST', '/orders/(\\d+)/payments', ({ params, body, locale }) => {
    const record = find(params[0])
    const amount = Math.round(Number(body.amount ?? 0) * 100) || 0

    if (amount <= 0) {
      const words = line(locale, 'webx-orders', 'panel.payment-amount-required')
      throw fail(422, words, undefined, { amount: [words] })
    }

    const refusal = record.marks.cancelled ? 'cancelled' : !record.marks.paid ? 'not_paid' : null
    const message = refusal ? line(locale, 'webx-orders', `panel.refusals.${refusal}`) : null
    const at = body.at ? `${String(body.at)}T12:00:00+00:00` : new Date().toISOString()
    const before = balanceAnswer(record)

    if (body.dry_run) {
      record.paid_total += refusal ? 0 : amount
      const after = balanceAnswer(record)
      record.paid_total -= refusal ? 0 : amount

      return {
        data: {
          amount,
          amount_formatted: money(amount),
          reference: text(body.reference),
          note: text(body.note),
          at,
          before,
          after,
          refusal,
          message,
          dry_run: true,
        },
      }
    }

    if (refusal && message) throw fail(422, message, undefined, { amount: [message] })

    record.paid_total += amount
    record.history.push({
      key: `h${record.id}-p${record.history.length}`,
      kind: 'payment',
      at,
      source: 'panel',
      admin: 'Demo admin',
      note: text(body.note),
      reference: text(body.reference),
      amount,
    })

    return { data: card(record, locale, line) }
  })

  /* «Change the lines» (§42.1): the live totals, then the card with the edit beside it. */
  on('POST', '/orders/(\\d+)/items/preview', ({ params, body, locale }) => ({
    data: editPlan(find(params[0]), body, locale, line, fail).answer,
  }))

  on('PUT', '/orders/(\\d+)/items', ({ params, body, locale }) => {
    const record = find(params[0])

    if (typeof body.reason !== 'string' || body.reason.trim() === '') {
      const words = line(locale, 'webx-orders', 'panel.items-reason-required')
      throw fail(422, words, undefined, { reason: [words] })
    }

    const plan = editPlan(record, body, locale, line, fail)

    if (!plan.apply) {
      const field = plan.answer.field ?? 'items'
      throw fail(422, plan.answer.message, undefined, { [field]: [plan.answer.message] })
    }

    const edit = plan.apply()

    return { data: { ...card(record, locale, line), edit } }
  })

  const eventLabel = (locale: string, event: string) =>
    line(locale, 'webx-orders', `notifications.events.${event.replace('orders.', '')}`)
  const said = (locale: string, path: string) =>
    line(locale, path.startsWith('errors.') ? 'webx-notifications' : 'webx-orders', path)

  /** A message sent by hand is a line of the history with who did it (decision 99). */
  function sentLine(record: OrderRecord, event: string, recipients: string[]): void {
    record.history.push({
      key: `h${record.id}-${record.history.length + 1}`,
      kind: 'notification_sent',
      at: new Date().toISOString(),
      source: 'panel',
      admin: 'Demo admin',
      note: recipients.join(', '),
      event,
    })
  }

  on('POST', '/orders/(\\d+)/deliveries/(\\d+)/resend', ({ params, locale }) => {
    const record = find(params[0])
    const log = logOf(record)
    const row = log.find((one) => one.id === Number(params[1]))

    if (!row) {
      const words = said(locale, 'panel.notify-refusals.foreign')

      throw fail(422, words, undefined, { delivery: [words] })
    }

    if (recently(log, (one) => one.recipient === row.recipient && one.event === row.event)) {
      const words = said(locale, 'errors.resent-recently')

      throw fail(422, words, undefined, { delivery: [words] })
    }

    log.unshift({
      ...row,
      id: nextDelivery++,
      status: 'sent',
      created_at: new Date().toISOString(),
      manual: true,
      manual_by: 'Demo admin',
    })
    sentLine(record, row.event, [row.recipient])

    return { data: card(record, locale, line) }
  })

  on('GET', '/orders/(\\d+)/notify', ({ params, locale }) => {
    const record = find(params[0])
    const events = byHand.map((key) => {
      const basis = basisOf(record, key)
      const channel = buyerChannels[key]
      const reason = !basis
        ? 'no_basis'
        : !channel
          ? 'no_channel'
          : !record.email
            ? 'no_email'
            : null

      return {
        key,
        label: eventLabel(locale, key),
        basis,
        channels:
          basis && channel ? [{ id: channel.id, name: channel.name, provider: 'email' }] : [],
        available: reason === null,
        reason,
        reason_label: reason ? said(locale, `panel.notify-refusals.${reason}`) : null,
      }
    })
    const reason = record.email ? null : 'no_email'

    return {
      data: {
        email: record.email,
        reason,
        reason_label: reason ? said(locale, `panel.notify-refusals.${reason}`) : null,
        events,
      },
    }
  })

  on('POST', '/orders/(\\d+)/notify', ({ params, body, locale }) => {
    const record = find(params[0])
    const event = String(body.event ?? '')
    const channel = buyerChannels[event]
    const reason = !basisOf(record, event)
      ? 'no_basis'
      : !channel
        ? 'no_channel'
        : !record.email
          ? 'no_email'
          : null
    const log = logOf(record)

    if (reason || !channel || !record.email) {
      const words = said(locale, `panel.notify-refusals.${reason ?? 'no_channel'}`)

      throw fail(422, words, undefined, { event: [words] })
    }

    if (body.dry_run) {
      const items = record.items
        .filter((one) => one.parent === null)
        .map((one) => `${one.name} × ${one.qty} — ${money(one.price * one.qty - one.discount)}`)

      return {
        data: {
          event,
          dry_run: true,
          deliveries: [
            {
              id: null,
              channel: channel.name,
              provider: 'email',
              recipient: record.email,
              status: 'queued',
            },
          ],
          previews: [
            {
              channel: channel.name,
              provider: 'email',
              recipient: record.email,
              subject: channel.subject.replace(':number', record.number),
              body: '',
              plain: [
                `Hello, ${record.first_name}!`,
                `Your order ${record.number} is accepted.`,
                '',
                ...items,
                '',
                `Total: ${money(payable(record))}`,
              ].join('\n'),
              html: true,
            },
          ],
        },
      }
    }

    if (recently(log, (one) => one.event === event && one.channel_id === channel.id)) {
      const words = said(locale, 'errors.resent-recently')

      throw fail(422, words, undefined, { event: [words] })
    }

    log.unshift({
      id: nextDelivery++,
      event,
      channel_id: channel.id,
      channel: channel.name,
      recipient: record.email,
      status: 'sent',
      created_at: new Date().toISOString(),
      manual: true,
      manual_by: 'Demo admin',
    })
    sentLine(record, event, [record.email])

    return { data: card(record, locale, line) }
  })

  on('POST', '/orders/(\\d+)/refunds/(\\d+)/reverse', ({ params, body, locale }) => {
    const record = find(params[0])
    const refund = record.history.find(
      (one) => one.kind === 'refund' && one.refund_id === Number(params[1]),
    )
    const reason = body.dry_run ? (text(body.reason) ?? '…') : text(body.reason)

    if (!reason) {
      const said = line(locale, 'webx-orders', 'panel.reverse-reason-required')

      throw fail(422, said, undefined, { reason: [said] })
    }

    const refusal = !refund ? 'foreign' : refund.reversed ? 'reversed' : null
    const amount = refusal ? 0 : (refund?.amount ?? 0)
    const tenders = refusal ? [] : (refund?.tenders ?? [])
    const items = refusal ? [] : (refund?.items ?? [])
    const refundedTotal = Math.max(0, record.refunded - amount)
    const tendersLeft = Object.fromEntries(
      record.tenders.map((one) => [
        one.id,
        one.amount -
          one.refunded +
          tenders.filter(([id]) => id === one.id).reduce((sum, [, value]) => sum + value, 0),
      ]),
    )
    const whole =
      refundedTotal >= payable(record) && Object.values(tendersLeft).every((left) => left === 0)
    const removed = !refusal && Boolean(record.marks.refunded) && !whole
    const message = refusal
      ? line(locale, 'webx-orders', `panel.reverse-refusals.${refusal}`)
      : null

    if (body.dry_run) {
      return {
        data: {
          refund_id: Number(params[1]),
          amount,
          amount_formatted: money(amount),
          tenders: Object.fromEntries(tenders),
          units: items.reduce((sum, [, qty]) => sum + qty, 0),
          refunded_total: refusal ? 0 : refundedTotal,
          refunded_total_formatted: money(refusal ? 0 : refundedTotal),
          money_left: refusal ? 0 : Math.max(0, payable(record) - refundedTotal),
          money_left_formatted: money(refusal ? 0 : Math.max(0, payable(record) - refundedTotal)),
          tenders_left: refusal ? {} : tendersLeft,
          items_left: refusal
            ? {}
            : Object.fromEntries(
                record.items.map((one) => [
                  one.id,
                  one.qty -
                    one.refunded_qty +
                    items.filter(([id]) => id === one.id).reduce((sum, [, qty]) => sum + qty, 0),
                ]),
              ),
          milestone_removed: removed
            ? { key: 'refunded', label: line(locale, 'webx-orders', 'module.milestones.refunded') }
            : null,
          refusal,
          message,
          dry_run: true,
        },
      }
    }

    if (refusal || !refund) throw fail(422, message ?? '', undefined, { refund: [message ?? ''] })

    const now = new Date().toISOString()

    refund.reversed = { at: now, reason }
    record.refunded = refundedTotal
    for (const [id, value] of tenders) {
      const tender = record.tenders.find((one) => one.id === id)

      if (tender) tender.refunded -= value
    }
    for (const [id, qty] of items) {
      const one = record.items.find((each) => each.id === id)

      if (one) one.refunded_qty -= qty
    }
    record.history.push({
      key: `h${record.id}-rr${record.history.length}`,
      kind: 'refund_reversed',
      at: now,
      source: 'panel',
      admin: 'Demo admin',
      note: reason,
      amount,
      refund_id: refund.refund_id,
    })
    if (removed) {
      delete record.marks.refunded
      record.history.push({
        key: `h${record.id}-mr${record.history.length}`,
        kind: 'milestone_removed',
        at: now,
        source: 'panel',
        admin: 'Demo admin',
        note: null,
        milestone: 'refunded',
      })
    }

    return { data: card(record, locale, line) }
  })

  on('POST', '/orders/status', ({ body, locale }) => {
    const to = target(body.status_id)
    const ids = (Array.isArray(body.ids) ? body.ids : []).map(Number)
    const tally = {
      orders: 0,
      moved: 0,
      unchanged: 0,
      public_changed: 0,
      notifications: 0,
      milestones: 0,
      cancels: 0,
      refused: [] as unknown[],
      dry_run: Boolean(body.dry_run),
    }

    if (!body.dry_run && to.milestone === 'cancelled' && !text(body.note))
      throw fail(422, 'Say why the order is cancelled.', undefined, {
        note: ['Say why the order is cancelled.'],
      })

    for (const record of orders.filter((one) => ids.includes(one.id))) {
      const outcome = plan(record, to, locale, line)

      tally.orders++

      if (outcome.refusal) {
        tally.refused.push({
          id: record.id,
          number: record.number,
          reason: outcome.refusal,
          message: outcome.message,
        })
        continue
      }

      if (!outcome.moved) {
        tally.unchanged++
        continue
      }

      tally.moved++
      tally.public_changed += outcome.public_changed ? 1 : 0
      tally.notifications += outcome.channels.length
      tally.milestones += outcome.milestone ? 1 : 0
      tally.cancels += outcome.milestone?.key === 'cancelled' ? 1 : 0

      if (!body.dry_run) move(record, to, text(body.note), outcome)
    }

    return { data: { ...tally, status: statusOf(to.id, locale, line) } }
  })

  on('POST', '/orders/cancel', ({ body, locale }) => {
    const reason = text(body.reason)

    if (!reason)
      throw fail(422, 'Say why the order is cancelled.', undefined, {
        reason: ['Say why the order is cancelled.'],
      })

    const ids = (Array.isArray(body.ids) ? body.ids : []).map(Number)
    const tally = {
      orders: 0,
      cancelled: 0,
      unchanged: 0,
      paid: 0,
      moved: 0,
      public_changed: 0,
      notifications: 0,
      status_id: null as number | null,
      refused: [] as unknown[],
      dry_run: Boolean(body.dry_run),
    }

    for (const record of orders.filter((one) => ids.includes(one.id))) {
      tally.orders++

      if (record.marks.cancelled || record.marks.refunded) {
        tally.unchanged++
        continue
      }

      tally.cancelled++
      tally.paid += record.marks.paid ? 1 : 0

      const status = cancelStatus(record, locale, line)

      if (status) {
        tally.moved++
        tally.public_changed += status.public_changed ? 1 : 0
        tally.notifications += status.channels.length
        tally.status_id ??= status.id
      }

      if (!body.dry_run) cancelOrder(record, reason)
    }

    return {
      data: {
        ...tally,
        status: tally.status_id ? (statusOf(tally.status_id, locale, line) ?? null) : null,
      },
    }
  })

  /* «New order» (§29.2): the products of a made-up catalogue found by a word, a variant to pick
     on the shirt, the totals counted as the cart would, and the order placed with the source
     `panel` — the card and the list then say where it came from. */
  on('GET', '/orders/new', () => ({
    data: {
      locales: [
        { code: 'en', name: 'English' },
        { code: 'ru', name: 'Russian' },
      ],
      locale: 'en',
    },
  }))

  on('GET', '/orders/new/products', ({ query }) => {
    const term = (query.get('search') ?? '').trim().toLowerCase()

    return {
      data: CATALOGUE.filter(
        (one) =>
          term === '' ||
          one.name.toLowerCase().includes(term) ||
          one.sku.toLowerCase().includes(term),
      ).map((one) => ({
        type: 'catalog.product',
        ref: one.ref,
        name: one.name,
        sku: one.sku,
        hint: one.hint,
        image: null,
        price: one.price,
        price_formatted: one.price > 0 ? money(one.price) : null,
        available: one.price > 0,
        options: one.sizes
          ? [
              {
                key: 'size',
                label: 'Size',
                values: one.sizes.map((size) => ({ value: size.toLowerCase(), label: size })),
              },
            ]
          : [],
      })),
    }
  })

  on('POST', '/orders/new/preview', ({ body }) => ({ data: counted(body.lines, fail).preview }))

  on('POST', '/orders/new', ({ body }) => {
    const key = text(body.key)
    const again = key ? placedByKey.get(key) : undefined

    if (again) return { data: { id: again.id, number: again.number } }

    const count = counted(body.lines, fail)
    const customer = (body.customer ?? {}) as Record<string, unknown>
    const wanted = (body.recipient ?? null) as Record<string, unknown> | null
    const errors: Record<string, string[]> = {}
    const phoneOk = (value: string | null) => value === null || /^\+?[0-9][0-9 ().-]*$/.test(value)

    // The words of `ContactRules` on the real server: the pair refused under both its fields.
    if (!text(customer.first_name)) errors['customer.first_name'] = ['Fill in «First name».']
    if (!text(customer.phone) && !text(customer.email)) {
      errors['customer.phone'] = ['Enter a phone or an e-mail.']
      errors['customer.email'] = ['Enter a phone or an e-mail.']
    }
    if (!phoneOk(text(customer.phone))) errors['customer.phone'] = [PHONE_WORDS]
    if (wanted && !text(wanted.first_name))
      errors['recipient.first_name'] = ["Fill in «Recipient's first name»."]
    if (Object.keys(errors).length > 0)
      throw fail(422, Object.values(errors)[0]![0]!, undefined, errors)

    const at = new Date().toISOString().replace(/\.\d{3}Z$/, '+00:00')
    const id = Math.max(...orders.map((one) => one.id)) + 1
    const record: OrderRecord = {
      ...order(id - 1, {}),
      placed_at: at,
      status: 1,
      guest: true,
      first_name: text(customer.first_name)!,
      last_name: text(customer.last_name),
      phone: text(customer.phone),
      email: text(customer.email),
      recipient: wanted
        ? {
            first_name: text(wanted.first_name)!,
            last_name: text(wanted.last_name),
            phone: text(wanted.phone),
          }
        : null,
      comment: text(body.comment),
      items: count.picked.map(({ product, qty, size }) =>
        item('a', qty, {
          name: product.name,
          sku: product.sku,
          price: product.price,
          options: size ? `Size: ${size}` : null,
        }),
      ),
      marks: { placed: { at, source: 'panel', reference: null } },
      history: [
        {
          key: `h${id}-0`,
          kind: 'status',
          at,
          source: 'panel',
          admin: 'Demo manager',
          note: null,
          from: null,
          to: 1,
        },
      ],
    }

    orders.push(record)
    if (key) placedByKey.set(key, record)

    return { data: { id: record.id, number: record.number } }
  })

  registerStatuses(on, fail, line)
}

/* ----------------------------------------------------------------------------- statuses ----- */

const settings: Record<string, unknown> = {
  'orders.number.prefix': '',
  'orders.number.start': 1000,
  'orders.number.pad': 0,
  'orders.initial_status': null,
  'orders.initial_status_awaiting_payment': null,
  // The channels of the milestones (§10.1): the set a site starts with has none in the playground.
  'orders.notify.placed': [],
  'orders.notify.paid': [],
  'orders.notify.payment_failed': [],
  'orders.notify.received': [],
  'orders.notify.cancelled': [],
  'orders.notify.refunded': [],
  // The tab «Checkout» (§31.2) with the defaults a site starts with.
  'orders.checkout.fields.last_name': 'optional',
  'orders.checkout.fields.phone': 'required',
  'orders.checkout.fields.email': 'required',
  'orders.checkout.recipient': 'on',
  'orders.checkout.comment': 'on',
  'orders.checkout.terms': 'notice',
  'orders.checkout.terms_page': null,
  'orders.checkout.privacy_page': null,
}

const LANGUAGES = ['en', 'ru']

const ordersIn = (status: number) => orders.filter((one) => one.status === status).length
const alive = () => statuses.filter((one) => !one.deleted)
const liveGroups = () => publics.filter((one) => !one.deleted)
const groupAlive = (id: number) => liveGroups().some((one) => one.id === id)

/** The statuses new orders come into: the settings', else the first two of the first group. */
function initial(): number[] {
  const usable = alive().filter((one) => one.active && groupAlive(one.public))
  const chosen = (key: string) => usable.find((one) => one.id === Number(settings[key]))?.id ?? null
  const first = usable.filter((one) => one.public === usable[0]?.public)

  return [
    ...new Set(
      [
        chosen('orders.initial_status') ?? first[0]?.id,
        chosen('orders.initial_status_awaiting_payment') ?? first[1]?.id ?? first[0]?.id,
      ].filter((id): id is number => id !== undefined),
    ),
  ]
}

/** Why an active stage may not leave its group — deleted, switched off, or moved out. */
function leaving(status: StatusRecord, gone: boolean): string | null {
  if (gone && initial().includes(status.id)) return 'initial'

  const others = alive().filter((one) => one.active && one.id !== status.id)

  if (gone && others.length === 0) return 'last_active'
  if (others.some((one) => one.public === status.public)) return null

  const inGroup = statuses.filter((one) => one.public === status.public)

  return inGroup.some((one) => ordersIn(one.id) > 0) ? 'last_active' : null
}

function words(value: unknown): Record<string, string> {
  if (!value || typeof value !== 'object') return {}

  return Object.fromEntries(
    Object.entries(value as Record<string, unknown>).filter(
      (entry): entry is [string, string] => typeof entry[1] === 'string' && entry[1].trim() !== '',
    ),
  )
}

function localized(fill: (locale: string) => string): Record<string, string> {
  return Object.fromEntries(LANGUAGES.map((locale) => [locale, fill(locale)]))
}

function board(locale: string, line: Line) {
  const stage = (status: StatusRecord) => ({
    id: status.id,
    public_status_id: status.public,
    name: status.names ?? localized((one) => stageName(status, one, line)),
    label: stageName(status, locale, line),
    color: status.color,
    milestone: status.milestone,
    active: status.active,
    deleted: status.deleted,
    orders: ordersIn(status.id),
  })
  const group = (one: PublicRecord) => ({
    id: one.id,
    code: one.code,
    name: one.names ?? localized((code) => groupName(one, code, line)),
    description:
      one.descriptions ??
      localized((code) => line(code, 'webx-orders', `statuses.public.${one.code}.description`)),
    label: groupName(one, locale, line),
    deleted: one.deleted ?? false,
    channels: one.notify ?? [],
  })

  return {
    data: {
      groups: liveGroups().map((one) => {
        const stages = alive()
          .filter((status) => status.public === one.id)
          .map(stage)

        return {
          ...group(one),
          statuses: stages,
          orders: stages.reduce((sum, status) => sum + status.orders, 0),
        }
      }),
      deleted: {
        groups: publics
          .filter((one) => one.deleted)
          .map((one) => ({ ...group(one), statuses: [] })),
        statuses: statuses
          .filter((one) => one.deleted || !groupAlive(one.public))
          .map((one) => {
            const where = publics.find((candidate) => candidate.id === one.public) as PublicRecord

            return {
              ...stage(one),
              public: {
                id: where.id,
                name: groupName(where, locale, line),
                deleted: where.deleted ?? false,
              },
            }
          }),
      },
      initial: initial(),
      colors: (['neutral', 'info', 'success', 'warning', 'danger', 'accent'] as Color[]).map(
        (value) => ({ value, label: line(locale, 'webx-orders', `module.colors.${value}`) }),
      ),
      milestones: (['paid', 'received', 'cancelled'] as Milestone[]).map((value) => ({
        value,
        label: line(locale, 'webx-orders', `module.milestones.${value}`),
      })),
      notifications: true,
    },
  }
}

interface Move {
  id: number
  from: number
  to: number
  orders: number
}

function said(
  locale: string,
  line: Line,
  change: 'delete' | 'delete-public' | 'save' | 'arrange',
  plan: {
    refusal: string | null
    id?: number
    orders?: number
    rebound?: Move[]
    active?: number[]
  },
) {
  const word = (key: string, values: Record<string, string | number> = {}) =>
    Object.entries(values).reduce(
      (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
      line(locale, 'webx-orders', `panel.statuses.${key}`),
    )
  const status = (id: number) => statuses.find((one) => one.id === id) as StatusRecord
  const group = (id: number) => publics.find((one) => one.id === id) as PublicRecord
  const rebound = (plan.rebound ?? []).map((move) => ({
    ...move,
    name: stageName(status(move.id), locale, line),
    from_name: groupName(group(move.from), locale, line),
    to_name: groupName(group(move.to), locale, line),
  }))
  const lines: string[] = []
  const own = change !== 'delete-public' && plan.id ? status(plan.id) : null

  if (change === 'delete' && own) {
    lines.push(
      word('delete-orders', { status: stageName(own, locale, line), count: plan.orders ?? 0 }),
    )
    lines.push(word('delete-stay', { public: groupName(group(own.public), locale, line) }))
    lines.push(word('delete-restore'))
  }

  if (change === 'delete-public' && plan.id && !plan.refusal)
    lines.push(word('delete-public-stay', { public: groupName(group(plan.id), locale, line) }))

  for (const move of rebound)
    lines.push(
      word('rebind', {
        status: move.name,
        from: move.from_name,
        to: move.to_name,
        count: move.orders,
      }),
    )

  if (rebound.length > 0 || change === 'delete') lines.push(word('no-messages'))

  const publicName =
    change === 'delete-public' && plan.id
      ? groupName(group(plan.id), locale, line)
      : own
        ? groupName(group(own.public), locale, line)
        : ''

  return {
    refusal: plan.refusal,
    message: plan.refusal ? word(`refusals.${plan.refusal}`, { public: publicName }) : null,
    orders: plan.orders ?? 0,
    rebound,
    active: (plan.active ?? []).map((id) => ({ id, name: stageName(status(id), locale, line) })),
    lines,
  }
}

function registerStatuses(on: On, fail: Fail, line: Line): void {
  const refuse = (message: string | null) =>
    fail(422, message ?? 'Refused.', undefined, { status: [message ?? 'Refused.'] })
  const dry = (value: unknown) => value === true || value === 1 || value === '1' || value === 'true'
  const stageOf = (id: string) => {
    const found = statuses.find((one) => one.id === Number(id))

    if (!found) throw fail(404, 'No such status.')

    return found
  }
  const groupOf = (id: string | number) => {
    const found = publics.find((one) => one.id === Number(id))

    if (!found) throw fail(404, 'No such status.')

    return found
  }
  const named = (body: Record<string, unknown>) => {
    const name = words(body.name)

    if (!name.en)
      throw fail(422, 'The name field is required.', undefined, {
        'name.en': ['The name field is required.'],
      })

    return name
  }

  on('GET', '/orders/statuses', ({ locale }) => board(locale, line))

  on('POST', '/orders/statuses/public', ({ body, locale }) => {
    const code = String(body.code ?? '')
      .trim()
      .toLowerCase()

    if (!/^[a-z][a-z0-9_]*$/.test(code) || publics.some((one) => one.code === code))
      throw fail(422, 'The code is not right.', undefined, {
        code: [line(locale, 'webx-orders', 'panel.statuses.errors.code-taken')],
      })

    publics.push({
      id: Math.max(...publics.map((one) => one.id)) + 1,
      code,
      channels: [],
      notify: ids(body.notify_channels),
      names: named(body),
      descriptions: words(body.description),
    })

    return board(locale, line)
  })

  on('PUT', '/orders/statuses/public/(\\d+)', ({ params, body, locale }) => {
    const group = groupOf(params[0])

    group.names = named(body)
    group.descriptions = words(body.description)
    if (Array.isArray(body.notify_channels)) group.notify = ids(body.notify_channels)
    if (typeof body.code === 'string' && body.code.trim() !== '') group.code = body.code.trim()

    return board(locale, line)
  })

  on('DELETE', '/orders/statuses/public/(\\d+)', ({ params, query, locale }) => {
    const group = groupOf(params[0])
    const moveTo = query.get('move_to') ? groupOf(query.get('move_to') as string) : null
    const living = alive().filter((one) => one.public === group.id)
    const plan = {
      id: group.id,
      refusal: living.length > 0 && !moveTo ? 'has_active' : null,
      orders: living.reduce((sum, one) => sum + ordersIn(one.id), 0),
      active: living.map((one) => one.id),
      rebound: moveTo
        ? living.map((one) => ({
            id: one.id,
            from: group.id,
            to: moveTo.id,
            orders: ordersIn(one.id),
          }))
        : [],
    }

    if (dry(query.get('dry_run'))) return { data: said(locale, line, 'delete-public', plan) }
    if (plan.refusal) throw refuse(said(locale, line, 'delete-public', plan).message)

    for (const one of living) {
      one.public = moveTo?.id ?? one.public
      statuses.splice(statuses.indexOf(one), 1)
      statuses.push(one)
    }
    group.deleted = true

    return board(locale, line)
  })

  on('POST', '/orders/statuses/public/(\\d+)/restore', ({ params, locale }) => {
    groupOf(params[0]).deleted = false

    return board(locale, line)
  })

  on('POST', '/orders/statuses/internal', ({ body, locale }) => {
    statuses.push({
      id: Math.max(...statuses.map((one) => one.id)) + 1,
      key: '',
      public: groupOf(Number(body.public_status_id)).id,
      color: (body.color as Color) ?? 'neutral',
      milestone: (body.sets_milestone as Milestone | null) ?? null,
      active: body.is_active !== false,
      deleted: false,
      names: named(body),
    })

    return board(locale, line)
  })

  on('PUT', '/orders/statuses/internal/(\\d+)', ({ params, body, locale }) => {
    const status = stageOf(params[0])
    const to = groupOf(Number(body.public_status_id))
    const active = body.is_active !== false
    const rebound =
      to.id !== status.public
        ? [{ id: status.id, from: status.public, to: to.id, orders: ordersIn(status.id) }]
        : []
    const refusal =
      to.deleted && !status.deleted
        ? 'public_deleted'
        : !status.deleted && status.active && !active
          ? leaving(status, true)
          : rebound.length > 0 && !status.deleted && status.active
            ? leaving(status, false)
            : null
    const plan = { id: status.id, refusal, orders: ordersIn(status.id), rebound }

    if (dry(body.dry_run)) return { data: said(locale, line, 'save', plan) }
    if (refusal) throw refuse(said(locale, line, 'save', plan).message)

    status.names = named(body)
    status.color = (body.color as Color) ?? status.color
    status.milestone = (body.sets_milestone as Milestone | null) ?? null
    status.active = active

    if (rebound.length > 0) {
      status.public = to.id
      statuses.splice(statuses.indexOf(status), 1)
      statuses.push(status)
    }

    return board(locale, line)
  })

  on('DELETE', '/orders/statuses/internal/(\\d+)', ({ params, query, locale }) => {
    const status = stageOf(params[0])
    const plan = {
      id: status.id,
      refusal: status.active && !status.deleted ? leaving(status, true) : null,
      orders: ordersIn(status.id),
    }

    if (dry(query.get('dry_run'))) return { data: said(locale, line, 'delete', plan) }
    if (plan.refusal) throw refuse(said(locale, line, 'delete', plan).message)

    status.deleted = true

    return board(locale, line)
  })

  on('POST', '/orders/statuses/internal/(\\d+)/restore', ({ params, locale }) => {
    const status = stageOf(params[0])

    if (!groupAlive(status.public))
      throw refuse(said(locale, line, 'save', { id: status.id, refusal: 'public_deleted' }).message)

    status.deleted = false

    return board(locale, line)
  })

  on('PUT', '/orders/statuses/arrange', ({ body, locale }) => {
    const order = (Array.isArray(body.publics) ? body.publics : []).map(Number)
    const groups = (body.groups ?? {}) as Record<string, number[]>
    const rebound: Move[] = []

    for (const [group, ids] of Object.entries(groups))
      for (const id of ids) {
        const status = stageOf(String(id))

        if (status.public !== Number(group))
          rebound.push({
            id: status.id,
            from: status.public,
            to: Number(group),
            orders: ordersIn(status.id),
          })
      }

    // A group that loses its stages keeps an active one while orders stand in it.
    const after = (id: number) =>
      Object.entries(groups).find(([, ids]) => ids.map(Number).includes(id))?.[0]
    const emptied = [...new Set(rebound.map((move) => move.from))].some((group) => {
      const stages = statuses.filter((one) =>
        one.deleted ? one.public === group : Number(after(one.id)) === group,
      )

      return (
        !stages.some((one) => one.active && !one.deleted) &&
        stages.some((one) => ordersIn(one.id) > 0)
      )
    })
    const plan = {
      refusal: emptied ? 'last_active' : null,
      rebound,
      orders: rebound.reduce((sum, move) => sum + move.orders, 0),
    }

    if (dry(body.dry_run)) return { data: said(locale, line, 'arrange', plan) }
    if (plan.refusal) throw refuse(said(locale, line, 'arrange', plan).message)

    publics.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id))
    const placed = order.flatMap((group) =>
      (groups[group] ?? []).map((id) => {
        const status = stageOf(String(id))

        status.public = group

        return status
      }),
    )
    const rest = statuses.filter((one) => !placed.includes(one))
    statuses.splice(0, statuses.length, ...placed, ...rest)

    return board(locale, line)
  })

  const settingsAnswer = () => ({
    stored: true,
    values: { ...settings },
    next_id: Math.max(...orders.map((one) => one.id)) + 1,
    sample: '',
  })

  on('GET', '/orders/settings', () => ({ data: settingsAnswer() }))

  on('PUT', '/orders/settings', ({ body, locale }) => {
    const values = (body.values ?? {}) as Record<string, unknown>
    const id = settingsAnswer().next_id
    const start = values['orders.number.start'] ?? settings['orders.number.start']
    const pad = values['orders.number.pad'] ?? settings['orders.number.pad']
    const prefix = values['orders.number.prefix'] ?? settings['orders.number.prefix']
    const sample =
      String(prefix ?? '') + String(Number(start ?? 1000) + id).padStart(Number(pad ?? 0), '0')

    if (orders.some((one) => one.number === sample)) {
      const words = line(locale, 'webx-orders', 'screen.number-taken').replace(':number', sample)

      throw fail(422, words, undefined, { 'orders.number.prefix': [words] })
    }

    // An order needs a phone or an e-mail (§20.3): one of the two stays required.
    const presence = (key: string) => values[key] ?? settings[key]

    if (
      presence('orders.checkout.fields.phone') !== 'required' &&
      presence('orders.checkout.fields.email') !== 'required'
    ) {
      const words = line(locale, 'webx-orders', 'checkout.settings.contact-needed')

      throw fail(422, words, undefined, { 'orders.checkout.fields.phone': [words] })
    }

    Object.assign(settings, values)

    return { data: settingsAnswer() }
  })
}
