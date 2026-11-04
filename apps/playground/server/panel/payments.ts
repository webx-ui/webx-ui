import { Reply } from './reply'

/**
 * Payments (`module-payments`, spec §13): the section of the panel on fixtures — the methods with
 * «gateway A» among them, the journal of attempts, the exchange journal and the tab «Payment» of
 * an order.
 *
 * The answers have the shapes of the PHP controllers: the attempts are Laravel's `->paginate()`,
 * the exchange is keyset pages by id, a refusal is a 422 under the field's name. A gateway's key
 * is never in an answer — only whether it is set — and the bodies of the exchange carry the key
 * the way the journal stores it: `[secret:secret_key]`. Everything is fake and anonymous: «Shop A
 * Ltd», an account of zeros, buyers at `@example.com`, the phone `+800 1234 5678`.
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
  extra?: Record<string, unknown>,
) => Error
type Line = (locale: string, namespace: string, path: string) => string

type Localized = Record<string, string>

interface Method {
  id: number
  key: string
  type: string
  title: Localized
  description: Localized
  enabled: boolean
  position: number
  delivery_rule: { mode: string; methods?: string[] }
  min_amount: number | null
  max_amount: number | null
  settings: Record<string, unknown>
  /** Only which are set: the fixture, like the server, never holds a value to send back. */
  keys: string[]
  environment?: boolean
}

interface Attempt {
  id: number
  uuid: string
  order_id: number
  method_key: string
  type: string
  amount: number
  status: string
  problem: string | null
  resolved?: boolean
  gateway_ref: string | null
  paid_amount: number | null
  paid_at: string | null
  hold: boolean
  authorized_amount: number | null
  hold_expires_at: string | null
  failure_reason: string | null
  reference: string | null
  source: string
  created_at: string
  /** Who marked it paid by hand. */
  admin_id?: number
}

/* The playground's one administrator, as the journal's «admin #1» reads on screen. */
const ADMIN_NAME = 'Admin'

interface Event {
  id: number
  trace: string
  direction: 'out' | 'in' | 'browser' | 'internal'
  kind: string
  gateway: string | null
  method_key: string | null
  payment_id: number | null
  order_id: number | null
  source: string | null
  http_method: string | null
  url: string | null
  ip: string | null
  status: number | null
  transport_error: string | null
  request_headers: Record<string, string[]> | null
  request_body: string | null
  response_headers: Record<string, string[]> | null
  response_body: string | null
  at: string
  duration_ms: number | null
  signature: string | null
  reported: string | null
  status_before: string | null
  status_after: string | null
  outcome: string | null
  message: string | null
  masked: Record<string, number>
}

const GATEWAY = 'gateway-a'
const GATEWAY_TITLE = 'Gateway A'
const HOLD_ONLY_CARDS = 'A hold works for card payments only'
const CURRENCY = 'EUR'

const now = Date.now()
const at = (minutes: number) => new Date(now - minutes * 60_000).toISOString()

const money = (minor: number) => `${(minor / 100).toFixed(2).replace('.', ',')} €`

let methods: Method[] = [
  {
    id: 1,
    key: 'on-receipt',
    type: 'store.on_receipt',
    title: {},
    description: {},
    enabled: true,
    position: 0,
    delivery_rule: { mode: 'on_hand' },
    min_amount: null,
    max_amount: null,
    settings: {},
    keys: [],
  },
  {
    id: 2,
    key: 'bank-transfer',
    type: 'store.bank_transfer',
    title: {},
    description: {},
    enabled: true,
    position: 1,
    delivery_rule: { mode: 'any' },
    min_amount: null,
    max_amount: null,
    settings: {
      recipient: { en: 'Shop A Ltd' },
      account: { en: 'Account 0000 0000 0000 0000\nBank A, code 000000' },
    },
    keys: [],
  },
  {
    id: 3,
    key: 'gateway-a-card',
    type: 'gateway-a.card',
    title: { en: 'Card (gateway A)' },
    description: {},
    enabled: true,
    position: 2,
    delivery_rule: { mode: 'any' },
    min_amount: 100,
    max_amount: null,
    settings: { shop_id: 'shop-a', mode: 'test', hold: false },
    keys: ['secret_key'],
  },
  {
    id: 4,
    key: 'gateway-a-form',
    type: 'gateway-a.form',
    title: { en: 'Gateway A page' },
    description: {},
    enabled: false,
    position: 3,
    delivery_rule: { mode: 'any' },
    min_amount: null,
    max_amount: null,
    settings: { shop_id: 'shop-a', mode: 'test' },
    keys: [],
  },
  {
    id: 5,
    key: 'gateway-a-unsigned',
    type: 'gateway-a.unsigned',
    title: { en: 'Gateway A (unsigned)' },
    description: {},
    enabled: true,
    position: 4,
    delivery_rule: { mode: 'any' },
    min_amount: null,
    max_amount: null,
    settings: { shop_id: 'shop-a', mode: 'test' },
    keys: [],
    environment: true,
  },
]

const TYPES = [
  { key: 'store.on_receipt', kind: 'offline', gateway: null, title: 'Payment on receipt' },
  { key: 'store.bank_transfer', kind: 'offline', gateway: null, title: 'Bank transfer' },
  { key: 'gateway-a.card', kind: 'online', gateway: GATEWAY, title: 'Card (gateway A)' },
  { key: 'gateway-a.form', kind: 'online', gateway: GATEWAY, title: 'Gateway A page' },
  { key: 'gateway-a.unsigned', kind: 'online', gateway: GATEWAY, title: 'Gateway A (unsigned)' },
]

const ulid = (n: number) => `01JC0000000000000000000${String(n).padStart(3, '0')}`

const attempts: Attempt[] = [
  {
    id: 1,
    uuid: ulid(1),
    order_id: 3,
    method_key: 'gateway-a-card',
    type: 'gateway-a.card',
    amount: 4999,
    status: 'failed',
    problem: null,
    gateway_ref: 'ga_pay_0001',
    paid_amount: null,
    paid_at: null,
    hold: false,
    authorized_amount: null,
    hold_expires_at: null,
    failure_reason: 'The bank declined the payment',
    reference: null,
    source: 'checkout',
    created_at: at(60 * 24 * 9),
  },
  {
    id: 2,
    uuid: ulid(2),
    order_id: 4,
    method_key: 'gateway-a-card',
    type: 'gateway-a.card',
    amount: 3750,
    status: 'paid',
    problem: null,
    gateway_ref: 'ga_pay_0002',
    paid_amount: 3750,
    paid_at: at(60 * 30),
    hold: false,
    authorized_amount: null,
    hold_expires_at: null,
    failure_reason: null,
    reference: null,
    source: 'checkout',
    created_at: at(60 * 30 + 3),
  },
  {
    id: 3,
    uuid: ulid(3),
    order_id: 4,
    method_key: 'gateway-a-card',
    type: 'gateway-a.card',
    amount: 3750,
    status: 'paid',
    problem: 'duplicate',
    gateway_ref: 'ga_pay_0003',
    paid_amount: 3750,
    paid_at: at(60 * 29),
    hold: false,
    authorized_amount: null,
    hold_expires_at: null,
    failure_reason: null,
    reference: null,
    source: 'pay-page',
    created_at: at(60 * 29 + 2),
  },
  {
    id: 5,
    uuid: ulid(5),
    order_id: 5,
    method_key: 'gateway-a-card',
    type: 'gateway-a.card',
    amount: 15000,
    status: 'authorized',
    problem: null,
    gateway_ref: 'ga_pay_0005',
    paid_amount: null,
    paid_at: null,
    hold: true,
    authorized_amount: 15000,
    hold_expires_at: new Date(now + 6 * 24 * 3600_000).toISOString(),
    failure_reason: null,
    reference: null,
    source: 'checkout',
    created_at: at(90),
  },
  {
    id: 6,
    uuid: ulid(6),
    order_id: 6,
    method_key: 'gateway-a-unsigned',
    type: 'gateway-a.unsigned',
    amount: 4999,
    status: 'pending',
    problem: null,
    gateway_ref: 'ga_pay_0006',
    paid_amount: null,
    paid_at: null,
    hold: false,
    authorized_amount: null,
    hold_expires_at: null,
    failure_reason: null,
    reference: null,
    source: 'checkout',
    created_at: at(12),
  },
]

const API = 'https://gateway-a.test'
const json = (value: unknown) => JSON.stringify(value)
const jsonHeaders = { 'Content-Type': ['application/json'] }

let nextEvent = 1
const events: Event[] = []

function event(fields: Partial<Event> & Pick<Event, 'trace' | 'direction' | 'kind' | 'at'>): void {
  events.push({
    id: nextEvent++,
    gateway: GATEWAY,
    method_key: 'gateway-a-card',
    payment_id: null,
    order_id: null,
    source: null,
    http_method: null,
    url: null,
    ip: null,
    status: null,
    transport_error: null,
    request_headers: null,
    request_body: null,
    response_headers: null,
    response_body: null,
    duration_ms: null,
    signature: null,
    reported: null,
    status_before: null,
    status_after: null,
    outcome: null,
    message: null,
    masked: {},
    ...fields,
  })
}

/* A card payment of gateway A, start to settle: the shape a lab run leaves behind. */
function cardRun(attempt: Attempt, minutes: number, outcome: 'paid' | 'failed'): void {
  const trace = `TRACE${String(attempt.id).padStart(21, '0')}`
  const common = {
    method_key: attempt.method_key,
    payment_id: attempt.id,
    order_id: attempt.order_id,
  }

  event({
    ...common,
    trace,
    direction: 'out',
    kind: 'key',
    source: attempt.source,
    http_method: 'POST',
    url: `${API}/oauth/token`,
    status: 200,
    request_headers: { ...jsonHeaders, Authorization: ['[secret:header]'] },
    request_body: json({ grant_type: 'client_credentials', client_secret: '[secret:secret_key]' }),
    response_headers: jsonHeaders,
    response_body: json({ access_token: '[secret:access_token]', expires_in: 3600 }),
    at: at(minutes),
    duration_ms: 84,
    masked: { 'secret:secret_key': 1, 'header:authorization': 1, 'field:access_token': 1 },
  })
  event({
    ...common,
    trace,
    direction: 'out',
    kind: 'start',
    source: attempt.source,
    http_method: 'POST',
    url: `${API}/v1/payments`,
    status: 201,
    request_headers: { ...jsonHeaders, Authorization: ['[secret:header]'] },
    request_body: json({
      shop: 'shop-a',
      amount: (attempt.amount / 100).toFixed(2),
      currency: CURRENCY,
      reference: attempt.uuid,
      buyer: { email: 'buyer@example.com', phone: '+800 1234 5678' },
      return_url: `https://shop.example.com/pay/return/${attempt.uuid}`,
    }),
    response_headers: jsonHeaders,
    response_body: json({
      id: attempt.gateway_ref,
      status: 'new',
      page: `${API}/pay/${attempt.gateway_ref}`,
    }),
    at: at(minutes - 0.1),
    duration_ms: 212,
    masked: { 'header:authorization': 1 },
  })
  event({
    ...common,
    trace,
    direction: 'browser',
    kind: 'handoff',
    source: attempt.source,
    http_method: 'GET',
    url: `${API}/pay/${attempt.gateway_ref}`,
    status: 302,
    at: at(minutes - 0.12),
  })

  const reported = outcome === 'paid' ? 'paid' : 'failed'
  const notifyBody = json({
    payment_id: attempt.gateway_ref,
    status: reported,
    amount: (attempt.amount / 100).toFixed(2),
    currency: CURRENCY,
    signature: 'c2lnbmVkLWJ5LWdhdGV3YXktYQ==',
  })

  event({
    ...common,
    trace: `NOTIFY${String(attempt.id).padStart(20, '0')}`,
    direction: 'in',
    kind: 'notify',
    source: 'notify',
    http_method: 'POST',
    url: `https://shop.example.com/payments/notify/${attempt.method_key}`,
    ip: '192.0.2.10',
    status: 200,
    request_headers: { ...jsonHeaders, 'User-Agent': ['GatewayA/1.0'] },
    request_body: notifyBody,
    response_headers: { 'Content-Type': ['text/plain'] },
    response_body: 'OK',
    at: at(minutes - 2),
    duration_ms: 31,
    signature: 'valid',
    reported,
    status_before: 'pending',
    status_after: outcome,
    outcome: 'applied',
  })
}

cardRun(attempts[0]!, 60 * 24 * 9, 'failed')
cardRun(attempts[1]!, 60 * 30 + 3, 'paid')
cardRun(attempts[2]!, 60 * 29 + 2, 'paid')
events[events.length - 1]!.message = 'The order is paid already: problem «duplicate»'

/* «Repeat notification» of the paid one: written, nothing new. */
event({
  trace: 'NOTIFYREPEAT00000000000002',
  direction: 'in',
  kind: 'notify',
  method_key: 'gateway-a-card',
  payment_id: 2,
  order_id: 4,
  source: 'notify',
  http_method: 'POST',
  url: 'https://shop.example.com/payments/notify/gateway-a-card',
  ip: '192.0.2.10',
  status: 200,
  request_headers: jsonHeaders,
  request_body: json({
    payment_id: 'ga_pay_0002',
    status: 'paid',
    amount: '37.50',
    currency: CURRENCY,
    signature: 'c2lnbmVkLWJ5LWdhdGV3YXktYQ==',
  }),
  response_body: 'OK',
  at: at(60 * 28),
  duration_ms: 12,
  signature: 'valid',
  reported: 'paid',
  status_before: 'paid',
  status_after: 'paid',
  outcome: 'duplicate',
})

/* «Wrong signature» on the failed order: rejected, the order untouched. */
event({
  trace: 'NOTIFYBADSIGN0000000000001',
  direction: 'in',
  kind: 'notify',
  method_key: 'gateway-a-card',
  payment_id: 1,
  order_id: 3,
  source: 'notify',
  http_method: 'POST',
  url: 'https://shop.example.com/payments/notify/gateway-a-card',
  ip: '198.51.100.7',
  status: 400,
  request_headers: jsonHeaders,
  request_body: json({
    payment_id: 'ga_pay_0001',
    status: 'paid',
    amount: '49.99',
    currency: CURRENCY,
    signature: 'Zm9yZ2Vk',
  }),
  response_body: 'Invalid signature',
  at: at(60 * 24 * 8),
  duration_ms: 9,
  signature: 'invalid',
  reported: 'paid',
  status_before: 'failed',
  status_after: 'failed',
  outcome: 'rejected',
  message:
    'The signature does not match; claimed for this attempt — not believed, the status is unchanged',
})

/* «Someone else's payment»: no attempt, no order — the journal keeps it all the same. */
event({
  trace: 'NOTIFYUNMATCHED00000000000',
  direction: 'in',
  kind: 'notify',
  method_key: 'gateway-a-card',
  source: 'notify',
  http_method: 'POST',
  url: 'https://shop.example.com/payments/notify/gateway-a-card',
  ip: '192.0.2.10',
  status: 200,
  request_headers: jsonHeaders,
  request_body: json({
    payment_id: 'ga_pay_9999',
    status: 'paid',
    amount: '10.00',
    currency: CURRENCY,
    signature: 'c2lnbmVk',
  }),
  response_body: 'OK',
  at: at(60 * 5),
  duration_ms: 7,
  signature: 'valid',
  reported: 'paid',
  outcome: 'unmatched',
  message: 'No attempt with this reference',
})

/* The unsigned type: the notification is not believed, the status is asked. */
event({
  trace: 'UNSIGNED000000000000000006',
  direction: 'in',
  kind: 'notify',
  method_key: 'gateway-a-unsigned',
  payment_id: 6,
  order_id: 6,
  source: 'notify',
  http_method: 'POST',
  url: 'https://shop.example.com/payments/notify/gateway-a-unsigned',
  ip: '192.0.2.10',
  status: 200,
  request_headers: { 'Content-Type': ['application/x-www-form-urlencoded'] },
  request_body: 'payment_id=ga_pay_0006&status=paid',
  response_body: 'OK',
  at: at(10),
  duration_ms: 240,
  signature: 'untrusted',
  outcome: 'same',
  message: 'The gateway says the payment is still pending',
})
event({
  trace: 'UNSIGNED000000000000000006',
  direction: 'out',
  kind: 'status',
  method_key: 'gateway-a-unsigned',
  payment_id: 6,
  order_id: 6,
  source: 'notify',
  http_method: 'GET',
  url: `${API}/v1/payments/ga_pay_0006`,
  status: 200,
  request_headers: { Authorization: ['[secret:header]'] },
  response_headers: jsonHeaders,
  response_body: json({ id: 'ga_pay_0006', status: 'pending' }),
  at: at(9.9),
  duration_ms: 180,
  reported: 'pending',
  masked: { 'header:authorization': 1 },
})

/* A timeout of «Check with the gateway». */
event({
  trace: 'TIMEOUT0000000000000000005',
  direction: 'out',
  kind: 'status',
  method_key: 'gateway-a-card',
  payment_id: 5,
  order_id: 5,
  source: 'panel',
  http_method: 'GET',
  url: `${API}/v1/payments/ga_pay_0005`,
  request_headers: { Authorization: ['[secret:header]'] },
  transport_error: 'timeout after 15 s',
  at: at(30),
  duration_ms: 15000,
})

const number = (order: number) => `WX-${1000 + order}`

/* What the orders of the fixtures owe and how they stand — the balance of `module-orders`. */
const ORDERS: Record<
  number,
  {
    total: number
    paid: number
    block: string | null
    awaiting: boolean
    failed?: boolean
    cancelled?: boolean
  }
> = {
  1: { total: 6399, paid: 0, block: 'on-receipt', awaiting: false },
  2: { total: 4999, paid: 0, block: 'bank-transfer', awaiting: true },
  3: { total: 4999, paid: 0, block: 'gateway-a-card', awaiting: true, failed: true },
  4: { total: 3750, paid: 3750, block: 'gateway-a-card', awaiting: false },
  5: { total: 15000, paid: 0, block: 'gateway-a-card', awaiting: true },
  6: { total: 4999, paid: 0, block: 'gateway-a-unsigned', awaiting: true },
}

/**
 * «Mark as paid» by hand (spec §12.2): an attempt \`paid\` for what the order owes and the event
 * \`internal/manual\` — what the server writes for the card's button, which \`orders.ts\` calls too.
 * False — the order is paid already, or not one of the fixtures.
 */
export function recordManualPayment(order: number, reference: string | null): boolean {
  const record = ORDERS[order]

  if (!record || record.paid >= record.total) return false

  const id = Math.max(...attempts.map((one) => one.id)) + 1
  const due = record.total - record.paid

  attempts.push({
    id,
    uuid: ulid(id),
    order_id: order,
    method_key: record.block ?? 'bank-transfer',
    type: 'store.bank_transfer',
    amount: due,
    status: 'paid',
    problem: null,
    gateway_ref: null,
    paid_amount: due,
    paid_at: new Date().toISOString(),
    hold: false,
    authorized_amount: null,
    hold_expires_at: null,
    failure_reason: null,
    reference: reference,
    source: 'panel',
    admin_id: 1,
    created_at: new Date().toISOString(),
  })
  event({
    trace: `MANUAL${String(id).padStart(20, '0')}`,
    direction: 'internal',
    kind: 'manual',
    gateway: null,
    method_key: record.block,
    payment_id: id,
    order_id: order,
    source: 'panel',
    at: new Date().toISOString(),
    status_after: 'paid',
    outcome: 'applied',
    message: `marked paid by admin #1${reference ? `, document ${reference}` : ''}`,
  })
  record.paid = record.total
  record.awaiting = false

  return true
}

export function registerPayments(on: On, fail: Fail, line: Line): void {
  const panel = (locale: string, path: string) => line(locale, 'webx-payments', `panel.${path}`)
  const words = (locale: string, key: string) => line(locale, 'webx-payments', key)

  const typeOf = (key: string) => TYPES.find((one) => one.key === key)
  const typeTitle = (key: string, locale: string) => {
    if (key === 'store.on_receipt') return words(locale, 'methods.on_receipt.title')
    if (key === 'store.bank_transfer') return words(locale, 'methods.bank_transfer.title')

    return typeOf(key)?.title ?? key
  }
  const pick = (titles: Localized, locale: string) =>
    titles[locale] ?? titles.en ?? Object.values(titles)[0]

  const keyState = (method: Method) => {
    if (typeOf(method.type)?.kind !== 'online') return null
    if (method.environment) return 'environment'

    return method.keys.length > 0 ? 'set' : 'missing'
  }

  const row = (method: Method, locale: string) => {
    const type = typeOf(method.type)
    const keys = keyState(method)

    return {
      id: method.id,
      key: method.key,
      type: method.type,
      type_title: typeTitle(method.type, locale),
      kind: type?.kind ?? null,
      title: pick(method.title, locale) ?? typeTitle(method.type, locale),
      gateway: type?.gateway ?? null,
      gateway_title: type?.gateway ? GATEWAY_TITLE : null,
      mode: type?.gateway ? String(method.settings.mode ?? 'test') : null,
      credentials: keys,
      hold: method.type === 'gateway-a.card' && Boolean(method.settings.hold),
      warnings: keys === 'missing' ? ['no_credentials'] : [],
      enabled: method.enabled,
      position: method.position,
    }
  }

  /* `MethodForm::typeParts()`: the type's fields and the gateway's, the keys, the hold, the addresses. */
  const parts = (type: string, method: Method | null, locale: string) => {
    const online = typeOf(type)?.kind === 'online'
    const fields =
      type === 'store.bank_transfer'
        ? (['recipient', 'account', 'purpose', 'note'] as const).map((name) => ({
            name,
            type: name === 'account' ? 'textarea' : 'text',
            label: words(locale, `methods.bank_transfer.settings.${name}`),
            help: null,
            localized: true,
            options: [],
            default: null,
          }))
        : type === 'store.on_receipt'
          ? (['title_pickup', 'cod_note'] as const).map((name) => ({
              name,
              type: name === 'cod_note' ? 'textarea' : 'text',
              label: words(locale, `methods.on_receipt.settings.${name}`),
              help: null,
              localized: true,
              options: [],
              default: null,
            }))
          : [
              {
                name: 'expires_minutes',
                type: 'number',
                label: 'Minutes the payment page works',
                help: null,
                localized: false,
                options: [],
                default: null,
              },
              {
                name: 'shop_id',
                type: 'text',
                label: 'Shop id',
                help: null,
                localized: false,
                options: [],
                default: 'shop-a',
              },
              {
                name: 'mode',
                type: 'select',
                label: 'Mode',
                help: null,
                localized: false,
                options: [{ value: 'test', label: 'Test' }],
                default: 'test',
              },
              ...(type === 'gateway-a.card'
                ? [
                    {
                      name: 'instalments',
                      type: 'switch',
                      label: 'Instalments',
                      help: null,
                      localized: false,
                      options: [],
                      default: false,
                      off_with: 'hold',
                      off_help: HOLD_ONLY_CARDS,
                    },
                  ]
                : []),
            ]

    return {
      settings: {
        fields,
        values: Object.fromEntries(
          fields
            .filter((field) => method && field.name in method.settings)
            .map((field) => [field.name, method!.settings[field.name]]),
        ),
      },
      credentials: online
        ? [
            {
              name: 'secret_key',
              label: 'Secret key',
              multiline: false,
              set: Boolean(method?.keys.includes('secret_key')),
              environment: Boolean(method?.environment),
            },
          ]
        : [],
      credentials_unreadable: false,
      hold:
        type === 'gateway-a.card'
          ? { value: Boolean(method?.settings.hold), partial: true, days: 7 }
          : null,
      online,
      testable: online,
      notify_url:
        online && method ? `https://shop.example.com/payments/notify/${method.key}` : null,
    }
  }

  const values = (method: Method) => ({
    titles: method.title,
    descriptions: method.description,
    enabled: method.enabled,
    delivery_mode: method.delivery_rule.mode,
    delivery_methods: method.delivery_rule.methods ?? [],
    delivery_keys: (method.delivery_rule.methods ?? []).join(', '),
    min_amount: method.min_amount === null ? null : method.min_amount / 100,
    max_amount: method.max_amount === null ? null : method.max_amount / 100,
  })

  const describe = (method: Method, locale: string) => ({
    method: row(method, locale),
    values: values(method),
    ...parts(method.type, method, locale),
  })

  const save = (method: Method, body: Record<string, unknown>, locale: string) => {
    const own = (body.values ?? {}) as Record<string, unknown>
    const min =
      own.min_amount === null || own.min_amount === undefined || own.min_amount === ''
        ? null
        : Math.round(Number(own.min_amount) * 100)
    const max =
      own.max_amount === null || own.max_amount === undefined || own.max_amount === ''
        ? null
        : Math.round(Number(own.max_amount) * 100)

    if (min !== null && max !== null && min > max) {
      const text = panel(locale, 'errors.amounts')

      throw fail(422, text, undefined, { max_amount: [text] })
    }

    if ('titles' in own) method.title = (own.titles ?? {}) as Localized
    if ('descriptions' in own) method.description = (own.descriptions ?? {}) as Localized
    if ('enabled' in own) method.enabled = Boolean(own.enabled)
    if ('delivery_mode' in own) {
      const mode = String(own.delivery_mode)
      method.delivery_rule = ['methods', 'except'].includes(mode)
        ? {
            mode,
            methods: String(own.delivery_keys ?? '')
              .split(',')
              .map((key) => key.trim())
              .filter(Boolean),
          }
        : { mode }
    }
    method.min_amount = min
    method.max_amount = max
    const settings = { ...method.settings, ...((body.settings ?? {}) as Record<string, unknown>) }

    // `GatewayACard::validateSettings()`: the form takes the field away, the server still refuses.
    if (method.type === 'gateway-a.card' && settings.hold && settings.instalments) {
      throw fail(422, HOLD_ONLY_CARDS, undefined, { 'settings.instalments': [HOLD_ONLY_CARDS] })
    }

    method.settings = settings

    // A key typed is «set» from now on; its value goes nowhere, here as on the server.
    for (const [name, value] of Object.entries(
      (body.credentials ?? {}) as Record<string, string>,
    )) {
      if (String(value).trim() !== '' && !method.keys.includes(name)) method.keys.push(name)
    }
    for (const name of (body.clear ?? []) as string[])
      method.keys = method.keys.filter((one) => one !== name)

    return method
  }

  const find = (id: number) =>
    methods.find((one) => one.id === id) ??
    (() => {
      throw fail(404, 'Not found')
    })()

  on('GET', '/payments/methods', ({ locale }) => ({
    data: {
      methods: [...methods].sort((a, b) => a.position - b.position).map((one) => row(one, locale)),
      types: TYPES.map((one) => ({
        key: one.key,
        title: typeTitle(one.key, locale),
        kind: one.kind,
        gateway: one.gateway,
        gateway_title: one.gateway ? GATEWAY_TITLE : null,
      })),
    },
  }))

  on('GET', '/payments/methods/blank', ({ query, locale }) => {
    const type = query.get('type') ?? ''

    if (!typeOf(type)) {
      const text = panel(locale, 'errors.unknown_type')

      throw fail(422, text, undefined, { type: [text] })
    }

    return { data: { type_title: typeTitle(type, locale), ...parts(type, null, locale) } }
  })

  on('GET', '/payments/methods/(\\d+)', ({ params, locale }) => ({
    data: describe(find(Number(params[0])), locale),
  }))

  on('POST', '/payments/methods', ({ body, locale }) => {
    const type = String(body.type ?? '')

    if (!typeOf(type)) {
      const text = panel(locale, 'errors.unknown_type')

      throw fail(422, text, undefined, { type: [text] })
    }

    const base = type.replace(/[^a-z0-9]+/g, '-')
    let key = base
    for (let n = 2; methods.some((one) => one.key === key); n++) key = `${base}-${n}`

    const method: Method = {
      id: Math.max(...methods.map((one) => one.id)) + 1,
      key,
      type,
      title: {},
      description: {},
      enabled: false,
      position: methods.length,
      delivery_rule: { mode: 'any' },
      min_amount: null,
      max_amount: null,
      settings: {},
      keys: [],
    }

    methods.push(save(method, body, locale))

    return { data: describe(method, locale) }
  })

  on('PUT', '/payments/methods/order', ({ body, locale }) => {
    const ids = ((body.ids ?? []) as number[]).map(Number)

    if (
      [...ids].sort().join() !==
      methods
        .map((one) => one.id)
        .sort()
        .join()
    ) {
      const text = panel(locale, 'errors.order')

      throw fail(422, text, undefined, { ids: [text] })
    }

    methods = ids.map((id, position) => ({ ...find(id), position }))

    return new Reply(204, undefined)
  })

  on('PUT', '/payments/methods/(\\d+)', ({ params, body, locale }) => ({
    data: describe(save(find(Number(params[0])), body, locale), locale),
  }))

  on('POST', '/payments/methods/(\\d+)/test', ({ params, body }) => {
    const method = find(Number(params[0]))
    const typed = Object.values((body.credentials ?? {}) as Record<string, string>).some(
      (value) => value.trim() !== '',
    )

    return new Reply(200, {
      data:
        method.keys.length > 0 || method.environment || typed
          ? { ok: true, message: 'Connected to shop shop-a' }
          : { ok: false, message: 'The gateway answered with HTTP 401' },
    })
  })

  const methodTitle = (key: string, locale: string) => {
    const method = methods.find((one) => one.key === key)

    return method ? (pick(method.title, locale) ?? typeTitle(method.type, locale)) : key
  }

  const attemptLine = (one: Attempt, locale: string) => ({
    ...one,
    admin_id: one.admin_id ?? null,
    admin_name: one.admin_id ? ADMIN_NAME : null,
    order_number: number(one.order_id),
    method_title: methodTitle(one.method_key, locale),
    amount_formatted: money(one.amount),
    currency: CURRENCY,
    resolved: Boolean(one.resolved),
    paid_amount_formatted: one.paid_amount === null ? null : money(one.paid_amount),
    authorized_amount_formatted:
      one.authorized_amount === null ? null : money(one.authorized_amount),
    unfinished: ['created', 'pending'].includes(one.status),
  })

  /** The attempt that took the money — method, document, date and who. */
  const paidBy = (mine: Attempt[], locale: string) => {
    const one = [...mine].reverse().find((attempt) => attempt.status === 'paid')

    return one
      ? {
          id: one.id,
          method_title: methodTitle(one.method_key, locale),
          reference: one.reference,
          paid_at: one.paid_at,
          amount_formatted: money(one.paid_amount ?? one.amount),
          source: one.source,
          admin_name: one.admin_id ? ADMIN_NAME : null,
          gateway_ref: one.gateway_ref,
        }
      : null
  }

  const size = (body: string | null) =>
    body === null ? null : new TextEncoder().encode(body).length

  const eventLine = (one: Event) => ({
    id: one.id,
    trace: one.trace,
    direction: one.direction,
    kind: one.kind,
    gateway: one.gateway,
    gateway_title: one.gateway === GATEWAY ? GATEWAY_TITLE : null,
    method_key: one.method_key,
    method_title: one.method_key ? methodTitle(one.method_key, 'en') : null,
    payment_id: one.payment_id,
    order_id: one.order_id,
    order_number: one.order_id === null ? null : number(one.order_id),
    payment_uuid:
      one.payment_id === null
        ? null
        : (attempts.find((a) => a.id === one.payment_id)?.uuid ?? null),
    source: one.source,
    http_method: one.http_method,
    status: one.status,
    transport_error: one.transport_error,
    request_size: size(one.request_body),
    response_size: size(one.response_body),
    truncated: false,
    at: one.at,
    duration_ms: one.duration_ms,
    signature: one.signature,
    reported: one.reported,
    status_before: one.status_before,
    status_after: one.status_after,
    outcome: one.outcome,
    message: one.message?.replace(/\badmin #1\b/, ADMIN_NAME) ?? null,
    claimed: one.outcome === 'rejected' && one.signature === 'invalid' && one.payment_id !== null,
    error:
      ['rejected', 'unmatched', 'error'].includes(one.outcome ?? '') ||
      (one.status ?? 0) >= 400 ||
      one.transport_error !== null ||
      one.signature === 'invalid',
  })

  const half = (headers: Record<string, string[]> | null, body: string | null) => ({
    headers: headers ?? {},
    body,
    has_body: body !== null && body !== '',
    download: false,
    size: size(body),
    sha256: body === null ? null : 'a3f1c2…fixture',
    truncated: false,
  })

  const tabOf = (status: string) =>
    ['created', 'pending', 'authorized'].includes(status)
      ? 'pending'
      : ['failed', 'cancelled', 'expired'].includes(status)
        ? 'failed'
        : 'other'

  on('GET', '/payments/attempts', ({ query, locale }) => {
    const tab = query.get('tab')
    const method = query.get('method')
    const search = (query.get('search') ?? '').trim()
    const list = attempts
      .filter(
        (one) => !tab || (tab === 'problems' ? one.problem !== null : tabOf(one.status) === tab),
      )
      .filter((one) => !method || one.method_key === method)
      .filter(
        (one) =>
          !search ||
          one.uuid === search ||
          one.gateway_ref === search ||
          number(one.order_id) === search,
      )
      .sort((a, b) => b.id - a.id)
    const perPage = Number(query.get('per_page') ?? 25)
    const page = Number(query.get('page') ?? 1)
    const slice = list.slice((page - 1) * perPage, page * perPage)

    return {
      data: slice.map((one) => attemptLine(one, locale)),
      current_page: page,
      last_page: Math.max(1, Math.ceil(list.length / perPage)),
      per_page: perPage,
      total: list.length,
      from: slice.length ? (page - 1) * perPage + 1 : null,
      to: slice.length ? (page - 1) * perPage + slice.length : null,
      filters: {
        methods: methods.map((one) => ({ value: one.key, label: methodTitle(one.key, locale) })),
        problems: attempts.filter((one) => one.problem !== null).length,
      },
    }
  })

  const attemptCard = (id: number, locale: string) => {
    const one =
      attempts.find((a) => a.id === id) ??
      (() => {
        throw fail(404, 'Not found')
      })()

    return {
      data: {
        ...attemptLine(one, locale),
        events: events.filter((e) => e.payment_id === one.id).map(eventLine),
        can_check: ['pending', 'authorized'].includes(one.status),
      },
    }
  }

  on('GET', '/payments/attempts/(\\d+)', ({ params, locale }) =>
    attemptCard(Number(params[0]), locale),
  )
  on(
    'POST',
    '/payments/attempts/(\\d+)/check',
    ({ params, locale }) => new Reply(200, attemptCard(Number(params[0]), locale)),
  )

  on('GET', '/payments/exchange', ({ query }) => {
    const before = Number(query.get('before') ?? 0)
    const perPage = Number(query.get('per_page') ?? 50)
    const orderNumber = query.get('order')
    const orderId = query.get('order_id')
    const payment = query.get('payment')
    const list = events
      .filter((one) => !query.get('gateway') || one.gateway === query.get('gateway'))
      .filter((one) => !query.get('method') || one.method_key === query.get('method'))
      .filter((one) => !query.get('kind') || one.kind === query.get('kind'))
      .filter((one) => !query.get('direction') || one.direction === query.get('direction'))
      .filter((one) => !orderId || one.order_id === Number(orderId))
      .filter(
        (one) => !orderNumber || (one.order_id !== null && number(one.order_id) === orderNumber),
      )
      .filter((one) => !payment || attempts.find((a) => a.id === one.payment_id)?.uuid === payment)
      .filter((one) => query.get('signature') !== '1' || one.signature === 'invalid')
      .filter((one) => query.get('errors') !== '1' || eventLine(one).error)
      .filter((one) => !before || one.id < before)
      .sort((a, b) => b.id - a.id)
    const page = list.slice(0, perPage)

    return {
      data: {
        data: page.map(eventLine),
        next: list.length > perPage ? (page[page.length - 1]?.id ?? null) : null,
        filters: {
          gateways: [{ value: GATEWAY, label: GATEWAY_TITLE }],
          methods: methods.map((one) => ({ value: one.key, label: one.key })),
          kinds: [
            'start',
            'status',
            'cancel',
            'complete',
            'capture',
            'void',
            'connection',
            'key',
            'other',
            'notify',
            'handoff',
            'return',
            'expire',
            'manual',
          ],
          directions: ['out', 'in', 'browser', 'internal'],
        },
      },
    }
  })

  on('GET', '/payments/exchange/(\\d+)', ({ params }) => {
    const one =
      events.find((e) => e.id === Number(params[0])) ??
      (() => {
        throw fail(404, 'Not found')
      })()

    return {
      data: {
        ...eventLine(one),
        url: one.url,
        ip: one.ip,
        event_key: null,
        masked: one.masked,
        request_sha256: one.request_body === null ? null : 'a3f1c2…fixture',
        response_sha256: one.response_body === null ? null : 'a3f1c2…fixture',
        // The playground's administrator is a super-user: the bodies come.
        bodies: true,
        request: one.direction === 'internal' ? null : half(one.request_headers, one.request_body),
        response:
          one.direction === 'internal' ? null : half(one.response_headers, one.response_body),
        trace_events: events.filter((e) => e.trace === one.trace).map(eventLine),
      },
    }
  })

  const orderCard = (order: number, locale: string) => {
    const record = ORDERS[order]
    const mine = attempts.filter((one) => one.order_id === order)
    const block = record?.block ? methods.find((one) => one.key === record.block) : null
    const due = record ? Math.max(0, record.total - record.paid) : 0
    const held = mine.some((one) => one.status === 'authorized')
    const paid = Boolean(record && record.paid >= record.total)
    const online = block ? typeOf(block.type)?.kind === 'online' : false
    const transfer = block?.type === 'store.bank_transfer'

    return {
      data: {
        order_id: order,
        number: number(order),
        currency: CURRENCY,
        block: block
          ? {
              method: block.key,
              type: block.type,
              kind: typeOf(block.type)?.kind ?? null,
              title: pick(block.title, locale) ?? typeTitle(block.type, locale),
              instructions: transfer
                ? [
                    {
                      label: words(locale, 'methods.bank_transfer.settings.recipient'),
                      text: 'Shop A Ltd',
                    },
                    {
                      label: words(locale, 'methods.bank_transfer.settings.account'),
                      text: 'Account 0000 0000 0000 0000\nBank A, code 000000',
                    },
                    {
                      label: words(locale, 'methods.bank_transfer.settings.purpose'),
                      text: `Payment for order ${number(order)}`,
                    },
                  ]
                : [],
            }
          : null,
        state: record?.cancelled
          ? 'cancelled'
          : paid
            ? 'paid'
            : held
              ? 'held'
              : record?.failed
                ? 'failed'
                : record?.awaiting
                  ? 'awaiting'
                  : 'none',
        paid_at: paid ? (mine.find((one) => one.paid_at)?.paid_at ?? null) : null,
        due,
        due_formatted: money(due),
        paid: record?.paid ?? 0,
        paid_formatted: money(record?.paid ?? 0),
        top_up: false,
        attempts: [...mine].reverse().map((one) => ({
          ...attemptLine(one, locale),
          events: events.filter((e) => e.payment_id === one.id).map(eventLine),
          can_resolve:
            one.problem !== null &&
            !one.resolved &&
            !['hold_expiring', 'capture_failed'].includes(one.problem),
        })),
        other_events: events
          .filter((e) => e.order_id === order && e.payment_id === null)
          .map(eventLine),
        paid_by: paidBy(mine, locale),
        bodies: true,
        can_manage: true,
        pay_link:
          due > 0 && !held
            ? `https://shop.example.com/pay/${number(order)}?signature=fixture`
            : null,
        can_mark_paid: !paid && !online && Boolean(record),
        can_check: mine.some((one) => ['pending', 'authorized'].includes(one.status)),
        can_switch: due > 0 && !held,
        methods:
          due > 0 && !held
            ? methods
                .filter((one) => one.enabled && one.key !== block?.key)
                .map((one) => ({
                  key: one.key,
                  title: pick(one.title, locale) ?? typeTitle(one.type, locale),
                  available:
                    keyState(one) !== 'missing' &&
                    (one.min_amount === null || due >= one.min_amount),
                  reason:
                    keyState(one) === 'missing' ? words(locale, 'reasons.no_credentials') : null,
                }))
            : [],
      },
    }
  }

  on('GET', '/payments/orders/(\\d+)', ({ params, locale }) => orderCard(Number(params[0]), locale))

  on('POST', '/payments/orders/(\\d+)/mark-paid', ({ params, body, locale }) => {
    const order = Number(params[0])

    if (
      !recordManualPayment(
        order,
        typeof body.reference === 'string' && body.reference !== '' ? body.reference : null,
      )
    ) {
      const text = words(locale, 'errors.mark.paid')

      throw fail(422, text, undefined, { order: [text] })
    }

    return new Reply(200, orderCard(order, locale))
  })

  on(
    'POST',
    '/payments/orders/(\\d+)/check',
    ({ params, locale }) => new Reply(200, orderCard(Number(params[0]), locale)),
  )

  on('POST', '/payments/orders/(\\d+)/method', ({ params, body, locale }) => {
    const order = Number(params[0])
    const method = methods.find((one) => one.enabled && one.key === body.method)

    if (!method || !ORDERS[order]) {
      const text = panel(locale, 'errors.unknown_method')

      throw fail(422, text, undefined, { method: [text] })
    }

    ORDERS[order]!.block = method.key
    ORDERS[order]!.failed = false

    return new Reply(200, orderCard(order, locale))
  })

  on('POST', '/payments/orders/(\\d+)/attempts/(\\d+)/resolve', ({ params, locale }) => {
    const one = attempts.find((a) => a.id === Number(params[1]) && a.order_id === Number(params[0]))

    if (!one?.problem) {
      const text = words(locale, 'errors.problem.none')

      throw fail(422, text, undefined, { payment: [text] })
    }

    one.resolved = true

    return new Reply(200, orderCard(Number(params[0]), locale))
  })
}
