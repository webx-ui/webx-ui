import type { ScreenModel } from '@webx-ui/schema'

/** The closed palette of internal statuses (spec §3); the panel turns it into tokens. */
export type StatusColor = 'neutral' | 'info' | 'success' | 'warning' | 'danger' | 'accent'

export type MilestoneKey =
  'placed' | 'awaiting_payment' | 'paid' | 'received' | 'cancelled' | 'refunded'

export interface OrderStatus {
  id: number
  name: string
  color: StatusColor
  milestone: MilestoneKey | null
  active: boolean
  deleted: boolean
  public?: { id: number; code: string; name: string } | null
}

export interface PublicStatusGroup {
  id: number
  code: string
  name: string
  deleted: boolean
  statuses: OrderStatus[]
}

/** The tabs over the list (§11.2). */
export type OrdersView = 'new' | 'awaiting' | 'failed' | 'all'

export interface OrderRow extends Record<string, unknown> {
  id: number
  number: string | null
  placed_at: string
  customer: { name: string; phone: string | null; email: string | null }
  guest: boolean
  items: { first: string | null; more: number }
  total: string
  payable: string
  status: OrderStatus | null
  awaiting_payment: boolean
  payment_failed: boolean
  paid_at: string | null
  received_at: string | null
  cancelled_at: string | null
  refunded: boolean
  delivery: string | null
  payment: string | null
}

export interface OrdersPage {
  data: OrderRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  counts: Record<OrdersView, number>
}

export interface OrdersMeta {
  statuses: PublicStatusGroup[]
  methods: { delivery: string[]; payment: string[] }
  initial_status: number | null
  /** Whether «guest or visitor» means anything: only with the visitors installed. */
  visitors: boolean
}

export interface OrderQuery {
  view?: OrdersView
  search?: string
  status?: string
  public?: string
  paid?: boolean
  received?: boolean
  cancelled?: boolean
  refunded?: boolean
  awaiting?: boolean
  failed?: boolean
  visitor?: 'guest' | 'visitor'
  delivery?: string
  payment?: string
  from?: string
  to?: string
  sort?: string
  page?: number
  per_page?: number
}

export interface OrderLine {
  id: number
  name: string
  sku: string | null
  options: string | null
  image: string | null
  label: string | null
  price: string
  qty: number
  refunded_qty: number
  total: string
  discounts: { label: string; amount: string }[]
  children: OrderLine[]
}

export interface OrderTender {
  id: number
  label: string
  source: string
  amount: string
  amount_minor: number
  refunded: string | null
  left_minor: number
}

export interface MilestoneMark {
  at: string
  source: string
  reference: string | null
  note: string | null
}

export interface TimelineLine {
  key: string
  kind: string
  at: string | null
  source: string
  admin: string | null
  note: string | null
  from?: string | null
  to?: string | null
  to_color?: StatusColor | null
  public?: string | null
  milestone?: { key: MilestoneKey; label: string }
  reference?: string | null
  amount?: string
  units?: number
}

export interface OrderCardData {
  id: number
  number: string | null
  placed_at: string
  locale: string
  currency: string
  exponent: number
  guest: boolean
  user_id: number | null
  status: OrderStatus | null
  statuses: PublicStatusGroup[]
  awaiting_payment: boolean
  payment_failed_at: string | null
  milestones: Partial<Record<MilestoneKey, MilestoneMark>>
  items: OrderLine[]
  adjustments: { id: number; label: string; source: string; amount: string }[]
  tenders: OrderTender[]
  benefits: { key: string; label: string }[]
  totals: {
    subtotal: string
    adjustments: string | null
    total: string
    tenders: string | null
    payable: string
    refunded: string | null
    refundable: string
    refundable_minor: number
  }
  blocks: { key: string; summary: string | null; value: Record<string, unknown> }[]
  timeline: TimelineLine[]
  /** The log of the notifications module about the order; null without the module. */
  notifications: OrderDelivery[] | null
  actions: {
    status: boolean
    paid: boolean
    received: boolean
    cancel: boolean
    details: boolean
    refund: boolean
  }
}

/** One row of the log of the notifications module: a channel, a recipient, what came of it. */
export interface OrderDelivery {
  id: number
  event: string
  channel_id: number | null
  channel: string
  provider: string
  recipient: string
  recipient_label: string | null
  status: 'queued' | 'sent' | 'failed' | 'skipped'
  /** Sent into a trap — the `log` mailer, a sandbox — and delivered to nobody. */
  trap: string | null
  status_label: string
  reason: string | null
  reason_label: string | null
  error: string | null
  created_at: string | null
  sent_at: string | null
}

export interface OrderDetail {
  order: OrderCardData
  values: ScreenModel
}

/** What a move would do, from the service's own dry run (§11.3). */
export interface MoveConsequences {
  moved: boolean
  public_changed: boolean
  public: string
  milestone: { key: MilestoneKey; label: string } | null
  channels: string[]
  refusal: string | null
  message: string | null
}

export interface CancelConsequences {
  was_paid: boolean
  money_to_refund: number
  money_to_refund_text: string | null
  tenders: { label: string; amount: string }[]
  outcome: string
}

export interface RefundInput {
  amount: string
  tenders: Record<number, string>
  items: Record<number, number>
  reference: string
  reason: string
}

export interface RefundPreview {
  money_left: number
  money_left_text: string
  tenders_left: Record<number, number>
  items_left: Record<number, number>
  full: boolean
  milestone: { key: MilestoneKey; label: string } | null
  refusal: string | null
  field: string | null
  message: string | null
}

export interface MassRefusal {
  id: number
  number: string | null
  reason: string
  message: string
}

export interface MassStatusAnswer {
  orders: number
  moved: number
  unchanged: number
  public_changed: number
  notifications: number
  milestones: number
  /** How many of them the move would cancel — each wants the reason. */
  cancels: number
  refused: MassRefusal[]
  dry_run: boolean
}

export interface MassCancelAnswer {
  orders: number
  cancelled: number
  unchanged: number
  paid: number
  refused: MassRefusal[]
  dry_run: boolean
}

/** Words of a status in every language of the site, as the dialogs edit them. */
export type StatusWords = Record<string, string>

/** An internal status on the screen of the statuses (spec §11.4), with the orders standing in it. */
export interface BoardStatus {
  id: number
  public_status_id: number
  name: StatusWords
  /** The name in the panel's language. */
  label: string
  color: StatusColor
  milestone: MilestoneKey | null
  active: boolean
  deleted: boolean
  orders: number
}

export interface BoardGroup {
  id: number
  code: string
  name: StatusWords
  description: StatusWords
  label: string
  deleted: boolean
  statuses: BoardStatus[]
  orders?: number
  /** The channels told when an order comes into the group (§10.1). */
  channels: number[]
}

export interface DeletedStatus extends BoardStatus {
  public: { id: number; name: string; deleted: boolean }
}

export interface StatusBoardData {
  groups: BoardGroup[]
  deleted: { groups: BoardGroup[]; statuses: DeletedStatus[] }
  /** The statuses new orders come into now. */
  initial: number[]
  colors: { value: StatusColor; label: string }[]
  milestones: { value: MilestoneKey; label: string }[]
  /** Whether the notifications module is there: only then a group chooses channels. */
  notifications: boolean
}

/** What a change of the statuses does, said before it is made (§5.6) — or why it cannot be. */
export interface StatusConsequences {
  refusal: string | null
  message: string | null
  orders: number
  rebound: {
    id: number
    from: number
    to: number
    orders: number
    name: string
    from_name: string
    to_name: string
  }[]
  active: { id: number; name: string }[]
  lines: string[]
}

export interface PublicStatusInput {
  code: string
  name: StatusWords
  description: StatusWords
  /** Only with the notifications module there; left out, the channels stay as they are. */
  notify_channels?: number[]
}

export interface StageInput {
  public_status_id: number
  name: StatusWords
  color: StatusColor
  sets_milestone: MilestoneKey | null
  is_active: boolean
}

/** The order of both levels as dragged: the groups, and each group's statuses. */
export interface StatusArrangement {
  publics: number[]
  groups: Record<number, number[]>
}

/** The settings of the section (§11.4): stored only with the settings module installed. */
export interface OrdersSettings {
  stored: boolean
  values: Record<string, unknown>
  /** The id the next order would get — what the trial number is made of. */
  next_id: number
  sample: string
}
