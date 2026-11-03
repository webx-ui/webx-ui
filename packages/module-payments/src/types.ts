import type { ScreenModel } from '@webx-ui/schema'

export interface Option {
  value: string
  label: string
}

/** How the keys of a method stand: none of them leaves the server, only this. */
export type KeyState = 'set' | 'environment' | 'missing' | 'unreadable'

export type MethodWarning =
  'type_missing' | 'gateway_missing' | 'no_credentials' | 'credentials_unreadable'

/** A line of «Methods» (spec §13.2). */
export type MethodRow = {
  id: number
  key: string
  type: string
  type_title: string
  kind: 'online' | 'offline' | null
  title: string
  gateway: string | null
  gateway_title: string | null
  mode: string | null
  /** Null — the type keeps no keys. */
  credentials: KeyState | null
  hold: boolean
  warnings: MethodWarning[]
  enabled: boolean
  position: number
}

/** A type «Add method» offers. */
export interface MethodType {
  key: string
  title: string
  kind: 'online' | 'offline'
  gateway: string | null
  gateway_title: string | null
}

/** A field of a type's or a gateway's settings. */
export interface SettingField {
  name: string
  type: 'text' | 'textarea' | 'select' | 'number' | 'switch'
  label: string
  help: string | null
  localized: boolean
  options: Option[]
  default: unknown
}

/** A key of the gateway: whether it is there, never what it is. */
export interface KeyField {
  name: string
  label: string
  multiline: boolean
  set: boolean
  environment: boolean
}

export interface TypeParts {
  settings: { fields: SettingField[]; values: Record<string, unknown> }
  credentials: KeyField[]
  credentials_unreadable: boolean
  hold: { value: boolean; partial: boolean; days: number } | null
  online: boolean
  testable: boolean
  notify_url: string | null
}

export interface MethodDetail extends TypeParts {
  method: MethodRow
  values: ScreenModel
}

export interface MethodBlank extends TypeParts {
  type_title: string
}

export interface MethodSave {
  values: ScreenModel
  settings?: Record<string, unknown>
  credentials?: Record<string, string>
  clear?: string[]
}

export type PaymentStatus =
  'created' | 'pending' | 'authorized' | 'paid' | 'failed' | 'cancelled' | 'expired'

/** One attempt (spec §13.2): money in minor units and as the shop writes it. */
export type AttemptRow = {
  id: number
  uuid: string
  order_id: number
  order_number: string | null
  method_key: string
  method_title: string
  type: string
  amount: number
  amount_formatted: string
  currency: string
  status: PaymentStatus
  problem: string | null
  resolved: boolean
  gateway_ref: string | null
  paid_amount: number | null
  paid_amount_formatted: string | null
  paid_at: string | null
  hold: boolean
  authorized_amount: number | null
  authorized_amount_formatted: string | null
  hold_expires_at: string | null
  failure_reason: string | null
  reference: string | null
  source: string
  /** Who marked it paid by hand, and their name. */
  admin_id?: number | null
  admin_name?: string | null
  created_at: string | null
  unfinished: boolean
}

export interface AttemptsPage {
  data: AttemptRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  filters: { methods: Option[]; problems: number }
}

export type AttemptDetail = AttemptRow & {
  events: EventLine[]
  can_check: boolean
}

/** A line of the exchange journal: never a header, never a body (§20.7). */
export type EventLine = {
  id: number
  trace: string
  direction: 'out' | 'in' | 'browser' | 'internal'
  kind: string
  gateway: string | null
  method_key: string | null
  payment_id: number | null
  order_id: number | null
  order_number?: string | null
  payment_uuid?: string | null
  /** The titles beside the keys, where the server knows them. */
  gateway_title?: string | null
  method_title?: string | null
  source: string | null
  http_method: string | null
  status: number | null
  transport_error: string | null
  request_size: number | null
  response_size: number | null
  truncated: boolean
  at: string | null
  duration_ms: number | null
  signature: 'valid' | 'invalid' | 'untrusted' | 'none' | null
  reported: string | null
  status_before: string | null
  status_after: string | null
  outcome: string | null
  message: string | null
  error: boolean
  /** A body that named this attempt under a signature that did not match: linked, not believed. */
  claimed?: boolean
}

export interface EventHalf {
  headers: Record<string, string[] | string>
  /** Text as stored; null — none, binary, or too large to draw. */
  body: string | null
  has_body: boolean
  download: boolean
  size: number | null
  sha256: string | null
  truncated: boolean
}

export type EventDetail = EventLine & {
  url: string | null
  ip: string | null
  event_key: string | null
  masked: Record<string, number>
  request_sha256: string | null
  response_sha256: string | null
  /** Whether the bodies came: `payments.exchange`. */
  bodies: boolean
  request: EventHalf | null
  response: EventHalf | null
  trace_events: EventLine[]
}

export interface ExchangeFilters {
  gateway?: string
  method?: string
  payment?: string
  order?: string
  order_id?: number
  kind?: string
  direction?: string
  signature?: boolean
  errors?: boolean
  from?: string
  to?: string
  before?: number
}

export interface ExchangePage {
  data: EventLine[]
  next: number | null
  filters: { gateways: Option[]; methods: Option[]; kinds: string[]; directions: string[] }
}

export type OrderPaymentState = 'awaiting' | 'paid' | 'failed' | 'held' | 'cancelled' | 'none'

export type OrderAttempt = AttemptRow & {
  events: EventLine[]
  can_resolve: boolean
}

export interface MethodChoice {
  key: string
  title: string
  available: boolean
  reason: string | null
}

/** The tab «Payment» of an order (spec §13.4). */
export interface OrderPayment {
  order_id: number
  number: string | null
  currency: string
  block: {
    method: string | null
    type: string | null
    kind: string | null
    title: string
    instructions: { label: string; text: string }[]
  } | null
  state: OrderPaymentState
  paid_at: string | null
  due: number
  due_formatted: string
  paid: number
  paid_formatted: string
  top_up: boolean
  attempts: OrderAttempt[]
  other_events: EventLine[]
  /** The attempt that took the money: method, document, date and who — a paid order. */
  paid_by: {
    id: number
    method_title: string
    reference: string | null
    paid_at: string | null
    amount_formatted: string
    source: string
    admin_name: string | null
    gateway_ref: string | null
  } | null
  bodies: boolean
  can_manage: boolean
  pay_link: string | null
  can_mark_paid: boolean
  can_check: boolean
  can_switch: boolean
  methods: MethodChoice[]
}
