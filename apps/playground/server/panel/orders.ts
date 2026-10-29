/**
 * «Orders» (`module-orders`, spec §11, §15): twelve orders of the demo set in the shapes the PHP
 * controllers answer — the list with its views and filters, the card, the dry runs of the
 * dialogs and what they do. The rules are the service's, retold small: a mark is set once, a
 * cancelled order takes no payment, a refund is held to what is left.
 *
 * Fake buyers only: «Buyer A»…, phones `+800 1234 5678` and on, addresses on `example.com`.
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
}

interface StatusRecord {
  id: number
  key: string
  public: number
  color: Color
  milestone: Milestone | null
  active: boolean
  deleted: boolean
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
  order(0, { items: [item('a'), item('c', 2)] }),
  order(1, { status: 2, awaiting: true, marks: {} }),
  order(2, { status: 2, awaiting: true, failed_at: day(-9, 12) }),
  order(3, {
    status: 1,
    marks: {},
    items: [item('b', 3)],
    comment: 'Please call before delivery.',
  }),
  order(4, { status: 3, items: set() }),
  order(5, { status: 4, recipient: { first_name: 'Sam', last_name: 'Doe', phone: null } }),
  order(6, { status: 6, delivery: { method: 'courier', summary: 'Courier, tomorrow 10–14' } }),
  order(7, {
    status: 8,
    items: [item('a', 2), item('c', 1, { label: 'Gift', price: 0 })],
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
  mark(at(3), 'paid', 0, 'TX-4410')
  mark(at(6), 'paid', 0)
  // Cancelled, paid and given back whole outside the site.
  mark(at(7), 'paid', 0)
  mark(at(7), 'cancelled', 1)
  mark(at(7), 'refunded', 2, 'R-77')
  at(7).refunded = total(at(7))
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
}

const subtotal = (record: OrderRecord) =>
  record.items.reduce((sum, line) => sum + line.price * line.qty - line.discount, 0)
const adjustments = (record: OrderRecord) =>
  record.adjustments.reduce((sum, one) => sum + one.amount, 0)
const total = (record: OrderRecord) => subtotal(record) + adjustments(record)
const tendered = (record: OrderRecord) => record.tenders.reduce((sum, one) => sum + one.amount, 0)
const payable = (record: OrderRecord) => total(record) - tendered(record)

seedMarks()

/* -------------------------------------------------------------------------------- words ----- */

function statusOf(id: number, locale: string, line: Line) {
  const status = statuses.find((one) => one.id === id)

  if (!status) return null

  const group = publics.find((one) => one.id === status.public) as PublicRecord

  return {
    id: status.id,
    name: line(locale, 'webx-orders', `statuses.internal.${status.key}`),
    color: status.color,
    milestone: status.milestone,
    active: status.active,
    deleted: status.deleted,
    public: {
      id: group.id,
      code: group.code,
      name: line(locale, 'webx-orders', `statuses.public.${group.code}.name`),
    },
  }
}

function grouped(locale: string, line: Line) {
  return publics.map((group) => ({
    id: group.id,
    code: group.code,
    name: line(locale, 'webx-orders', `statuses.public.${group.code}.name`),
    deleted: false,
    statuses: statuses
      .filter((one) => one.public === group.id)
      // A status inside its group: the group is the parent, not a field of it.
      .map((one) => ({ ...statusOf(one.id, locale, line), public: undefined })),
  }))
}

/* --------------------------------------------------------------------------------- list ----- */

const name = (record: OrderRecord) =>
  [record.first_name, record.last_name].filter(Boolean).join(' ')

function row(record: OrderRecord, locale: string, line: Line) {
  const roots = record.items.filter((one) => one.parent === null)

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
    delivery: record.delivery?.summary ?? null,
    payment: record.payment?.summary ?? null,
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
  if (query.get('delivery') && record.delivery?.method !== query.get('delivery')) return false

  const from = query.get('from')
  const to = query.get('to')

  if (from && record.placed_at.slice(0, 10) < from) return false
  if (to && record.placed_at.slice(0, 10) > to) return false

  return true
}

function list(query: URLSearchParams, locale: string, line: Line) {
  const filtered = orders.filter((record) => matches(record, query))
  const view = query.get('view') ?? 'all'
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
  }
}

/* --------------------------------------------------------------------------------- card ----- */

function lines(record: OrderRecord, parent: number | null): unknown[] {
  return record.items
    .filter((one) => one.parent === parent)
    .map((one) => ({
      id: one.id,
      name: one.name,
      sku: one.sku,
      options: null,
      image: null,
      label: one.label,
      price: money(one.price),
      qty: one.qty,
      refunded_qty: one.refunded_qty,
      total: money(one.price * one.qty - one.discount),
      discounts: [],
      children: lines(record, one.id),
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
  const refundable = Math.max(0, payable(record) - record.refunded)
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
      status: statusOf(record.status, locale, line),
      statuses: grouped(locale, line),
      awaiting_payment: record.awaiting && !cancelled,
      payment_failed_at: paid ? null : record.failed_at,
      milestones: record.marks,
      items: lines(record, null),
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
      },
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
      actions: {
        status: true,
        paid: !paid && !cancelled,
        received: !record.marks.received && !cancelled,
        cancel: !cancelled && !record.marks.refunded,
        details: true,
        refund: refundable > 0 || tendersLeft > 0 || unitsLeft > 0,
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
      manager_note: record.manager_note,
    },
  }
}

/* -------------------------------------------------------------------------------- moves ----- */

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
    public: line(locale, 'webx-orders', `statuses.public.${group.code}.name`),
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

  if (key === 'paid') record.awaiting = false
  if (key === 'cancelled') for (const tender of record.tenders) tender.refunded = tender.amount
}

/* ------------------------------------------------------------------------------- routes ----- */

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
      const left = Math.max(0, payable(record) - record.refunded)

      return {
        data: {
          was_paid: Boolean(record.marks.paid),
          money_to_refund: record.marks.paid ? left : 0,
          money_to_refund_text: record.marks.paid && left > 0 ? money(left) : null,
          tenders: record.tenders
            .filter((one) => one.amount > one.refunded)
            .map((one) => ({ label: one.label, amount: money(one.amount - one.refunded) })),
          outcome: record.marks.cancelled ? 'already_marked' : 'marked',
        },
      }
    }

    if (!reason)
      throw fail(422, 'The reason field is required.', undefined, {
        reason: ['The reason field is required.'],
      })
    if (!record.marks.cancelled) mark(record, 'cancelled', null, reason)

    return { data: card(record, locale, line) }
  })

  on('PUT', '/orders/(\\d+)/details', ({ params, body, locale }) => {
    const record = find(params[0])
    const values = (body.values ?? {}) as Record<string, unknown>
    const has = (key: string) => key in values
    const phone = text(values['customer.phone'])

    if (has('customer.phone') && phone && !/^\+?[0-9][0-9 ().-]*$/.test(phone)) {
      throw fail(422, 'The phone format is invalid.', undefined, {
        'customer.phone': ['The phone format is invalid.'],
      })
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
    const moneyLeft = Math.max(0, payable(record) - record.refunded)
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
    })
    if (full && !record.marks.refunded) mark(record, 'refunded', text(body.reference), null)

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
      refused: [] as unknown[],
      dry_run: Boolean(body.dry_run),
    }

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

      if (!body.dry_run) move(record, to, text(body.note), outcome)
    }

    return { data: { ...tally, status: statusOf(to.id, locale, line) } }
  })

  on('POST', '/orders/cancel', ({ body }) => {
    const reason = text(body.reason)

    if (!reason)
      throw fail(422, 'The reason field is required.', undefined, {
        reason: ['The reason field is required.'],
      })

    const ids = (Array.isArray(body.ids) ? body.ids : []).map(Number)
    const tally = {
      orders: 0,
      cancelled: 0,
      unchanged: 0,
      paid: 0,
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

      if (!body.dry_run) mark(record, 'cancelled', null, reason)
    }

    return { data: tally }
  })
}
