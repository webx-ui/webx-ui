import type { ScreenModel } from '@webx-ui/schema'

/** A page of a list, as Laravel's `->paginate()` answers. */
export interface Page<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Option {
  value: string
  label: string
}

/** The short line of a locality: a picker's suggestion, a popular town, a candidate. */
export interface LocalityBrief {
  id: number
  name: string
  /** «Town A, District C, Region B» — what tells namesakes apart. */
  label: string
  kind: string
  /** «Town», «Village»: the type in the panel's language. */
  kind_label: string
  country: string
  active: boolean
}

export interface MethodType {
  key: string
  title: string
  /** One line of what the type is, under its title in «What kind of method?». */
  description: string
  carrier: string
  carrier_title: string
  asks: 'nothing' | 'address' | 'point'
}

export interface MethodRow {
  id: number
  key: string
  type: string
  type_title: string
  carrier: string | null
  carrier_title: string | null
  /** The type is not installed any more: the method stays and is never offered. */
  missing: boolean
  title: string
  price: number | null
  price_text: string | null
  countries: string[]
  enabled: boolean
  position: number
}

export interface MethodDetail {
  method: MethodRow
  values: ScreenModel
  /** Node ids of the form this type has no use for. */
  hidden: string[]
  point_kinds: Option[]
}

export interface MethodBlank {
  hidden: string[]
  point_kinds: Option[]
  type_title: string
}

export interface PointRow {
  id: number
  kind: string
  kind_label: string
  title: string
  address: string
  locality: LocalityBrief | null
  phone: string | null
  active: boolean
  today: string | null
}

export interface PointDetail {
  point: PointRow
  values: ScreenModel
}

export interface PointsPage extends Page<PointRow> {
  filters: { kinds: Option[] }
}

export interface SyncRun {
  id: number
  directory: string
  status: 'running' | 'succeeded' | 'failed' | 'held'
  trigger: string
  pages: number
  seen: number
  created: number
  updated: number
  removed: number
  queued: number
  held: number | null
  error: string | null
  started_at: string | null
  finished_at: string | null
}

export interface CarrierDirectory {
  directory: 'localities' | 'points' | 'streets'
  cron: string | null
  last_success: string | null
  latest: SyncRun | null
  running: boolean
  held: number | null
  stale: boolean
  records: number
  remarks: { key: string; count: number }[]
}

export interface CarrierRow {
  key: string
  title: string
  icon: string | null
  enabled: boolean
  countries: string[]
  syncs: boolean
  directories: CarrierDirectory[]
  queue: number
  /** Labels of chosen settings the carrier's account no longer has, from the options last asked for. */
  missing: string[]
}

/**
 * A field a carrier asks for (§13.2): the name it is stored by and how to draw it. `options:
 * 'carrier'` — its options come from the carrier's account (`ProvidesSettingOptions`), `depends`
 * — the fields they depend on, `search` — they are searched by what is typed, not listed.
 */
export interface CarrierField {
  name: string
  type?: string
  label?: string | Record<string, string>
  options?: Option[] | 'carrier'
  depends?: string[]
  search?: boolean
  default?: unknown
  required?: boolean
  min?: number
  max?: number
  /** A number's step and decimal places: a weight of 0.5 kg needs 0.1, not the default 1. */
  step?: number
  precision?: number
  [key: string]: unknown
}

/** One option of a field from the carrier's account. */
export interface SettingOption {
  value: string
  label: string
  hint: string | null
}

export interface CarrierDetail extends CarrierRow {
  settings: {
    fields: CarrierField[]
    /** The stored values, with `_labels` — field → label of the chosen option. */
    values: Record<string, unknown>
    /** Chosen values the carrier's account no longer has: field → label. */
    missing: Record<string, string>
  }
  /** The keys: whether each is set, never what it is. */
  credentials: (CarrierField & { set: boolean })[]
  runs: SyncRun[]
}

export interface CountryRow {
  code: string
  name: string
  active: boolean
  position: number
  regions: number
  localities: number
  leader: string | null
  /** The config names the leader: the panel cannot change it. */
  leader_fixed: boolean
}

export interface Countries {
  countries: CountryRow[]
  choices: { code: string; name: string }[]
  /** The carriers that may lead a country, by their titles. */
  carriers: Option[]
}

export interface RegionRow {
  id: number
  name: string
  names: Record<string, string>
  code: string | null
  country: string
  /** Where it stands in the country's own order; alphabetical breaks a tie. */
  position: number
  localities: number
}

export interface LocalityRow extends LocalityBrief {
  region: { id: number; name: string } | null
  district: string | null
  weight: number
  created_by: string
  source: 'manual' | 'import' | 'carrier'
  bindings: { carrier: string; carrier_title: string; external_id: string; status: string }[]
}

export interface LocalityDetail {
  locality: LocalityBrief & {
    source: LocalityRow['source']
    created_by: string
    manual_fields: string[]
    held_by: string[]
  }
  values: ScreenModel
}

/** The answer to a locality like one that exists (409): the ones to pick instead. */
export interface DuplicateAnswer {
  message: string
  similar: (LocalityBrief & { source: LocalityRow['source'] })[]
}

export interface BindingRow {
  id: number
  carrier: string
  carrier_title: string
  external_id: string
  reason: string | null
  record: {
    name: string
    region: string | null
    district: string | null
    kind: string | null
    country: string | null
    lat: number | null
    lng: number | null
  }
  candidates: (LocalityBrief & { distance_km: number | null })[]
}

export interface BindingsPage extends Page<BindingRow> {
  filters: {
    carriers: { value: string; label: string; count: number }[]
    reasons: { value: string; count: number }[]
  }
}

export interface BatchResult {
  done: number
  failed: { id: number; message: string }[]
  left: number
}

/** The snapshot of the order's delivery in the panel's language (§11.1), read from the copy alone. */
export interface OrderDeliveryView {
  method: string
  method_key: string | null
  carrier: string
  carrier_title: string
  locality: string
  point: {
    title: string
    number: string | null
    address: string | null
    schedule: string | null
  } | null
  address: string | null
  note: string | null
  price: string | null
  was: string | null
  estimate: string | null
  paid_by_store: boolean
  payer: 'sender' | 'recipient' | null
  waybill: string | null
}

export type ShipmentStatus =
  | 'created'
  | 'in_transit'
  | 'at_point'
  | 'out_for_delivery'
  | 'received'
  | 'refused'
  | 'returning'
  | 'returned'
  | 'cancelled'

export interface ShipmentEvent {
  status: ShipmentStatus
  label: string
  at: string
  text: string | null
}

/** A parcel of the order (§11.2–§11.3). */
export interface ShipmentRow {
  id: number
  number: string | null
  carrier: string
  method: string
  payer: 'sender' | 'recipient'
  cost: number | null
  cost_formatted: string | null
  status: ShipmentStatus
  status_label: string
  status_at: string | null
  final: boolean
  next_check_at: string | null
  cancellable: boolean
  label: boolean
  events: ShipmentEvent[]
  created_at: string | null
}

/** What the carrier would get (§11.2): the dialog shows it and lets the manager change a few lines. */
export interface WaybillDraft {
  order_id: number
  order_number: string | null
  carrier: string
  type: string
  method: string | null
  recipient: {
    first_name: string
    last_name: string
    name: string
    phone: string | null
    email: string | null
  }
  sender: Record<string, unknown>
  lines: { name: string; sku: string | null; qty: number; price: number }[]
  declared_value: number
  currency: string
  payer: 'sender' | 'recipient'
  cod: number | null
  carrier_locality: string | null
  /** The carrier's own fields of the waybill, by name. */
  extra: Record<string, unknown>
  /** What the dialog draws besides the base's lines: the type's own fields and whether cash on delivery is asked. */
  form?: { fields: CarrierField[]; cod: boolean }
}

/** The tab «Delivery» of the order's card. */
export interface OrderDelivery {
  order_id: number
  currency: string
  exponent: number
  delivery: OrderDeliveryView | null
  waybills: boolean
  can_ship: boolean
  draft: WaybillDraft | null
  active: number | null
  /** «Change delivery» for whoever edits the order (orders.manage); null — not theirs. */
  change: OrderDeliveryChangeState | null
  shipments: ShipmentRow[]
}

/** What the dialog of «Change delivery» starts from: the choice as the order has it. */
export interface OrderDeliveryChangeState {
  /** Why it cannot be changed now — a waybill stands; null — it can. */
  refusal: string | null
  locality: { id: number; label: string } | null
  /** The method's key. */
  method: string | null
  point: number | null
  address: Record<string, string | null> | null
  country: string | null
}

/** A town of the dialog's search and of its popular buttons. */
export interface ChangeTown {
  id: number
  name: string
  label: string
  region: string | null
}

/** A method of the town, priced for the order's lines — the checkout's row. */
export interface ChangeMethod {
  id: number
  key: string
  title: string
  description: string | null
  asks: 'nothing' | 'point' | 'address' | null
  carrier: string
  points: number | null
  disabled: boolean
  hint: string | null
  price: { kind: string; text: string; was: string | null; estimate: string | null } | null
}

export interface ChangeOption {
  value: string
  label: string
  hint?: string | null
}

/** The input of «Change delivery». */
export interface ChangeInput {
  locality: number
  method: number
  point?: number | null
  address?: {
    street?: string | null
    street_id?: string | null
    house?: string | null
    apartment?: string | null
    note?: string | null
  } | null
  reason?: string | null
  dry_run?: boolean
}

/** «Was → will be» of the sum, in the order's money. */
export interface ChangeOutcome {
  summary_from: string | null
  summary_to: string | null
  amount_from: string
  amount_to: string
  total_from: string
  total_to: string
  total_changes: boolean
  balance: { kind: 'topup' | 'over'; amount: string } | null
  dry_run: boolean
}

export interface WaybillChanges {
  recipient_first_name?: string | null
  recipient_last_name?: string | null
  recipient_phone?: string | null
  declared_value?: number | null
  cod?: number | null
  extra?: Record<string, unknown>
  dry_run?: boolean
}

export type ShipmentLabel = { url: string } | { pdf: string; filename: string }
