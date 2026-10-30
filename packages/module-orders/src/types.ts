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
  /** Where the order came from: `checkout`, `quick` («Buy in one click»), `api`, `mcp`… */
  source: string | null
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
  /** The tab the rows are of; the one chosen when the list was asked with `view: 'auto'`. */
  view?: OrdersView
}

/** One currency's figures of a period: placed, paid, the revenue and the average of a paid order. */
export interface SummaryPeriodMoney {
  currency: string
  placed: number
  paid: number
  revenue: number
  revenue_formatted: string
  average: number
  average_formatted: string
}

/** The figures over the list — the agent's `orders_summary`, the same count (§11.2, §12). */
export interface OrdersSummaryData {
  awaiting_payment: number
  payment_failed: number
  paid_status_waits: number
  /** What waits for payment now, whenever it was ordered. */
  awaiting_by_currency: {
    currency: string
    orders: number
    amount: number
    amount_formatted: string
  }[]
  periods: { days: number; by_currency: SummaryPeriodMoney[] }[]
  /** Orders placed each of the last thirty days, today last. */
  daily: { date: string; orders: number }[]
}

/** An opened row of the list: what the order holds and who it is for, without the card. */
export interface OrderGlance {
  id: number
  items: { id: number; name: string; options: string | null; qty: number; total: string }[]
  count: { lines: number; units: number; refunded_units: number }
  /** The totals as the card's «Summary» has them; what is not there is null. */
  totals: {
    subtotal: string
    adjustments: string | null
    total: string
    tenders: string | null
    payable: string
    refunded: string | null
  }
  paid: boolean
  awaiting_payment: boolean
  /** Where the order came from when not through the checkout: `quick`, `panel`. */
  source: string | null
  customer: { name: string; phone: string | null; email: string | null }
  recipient: { name: string; phone: string | null } | null
  comment: string | null
  manager_note: string | null
  last_note: OrderNote | null
  blocks: { key: string; summary: string | null }[]
  milestones: Partial<Record<MilestoneKey, string>>
}

export interface OrdersMeta {
  statuses: PublicStatusGroup[]
  methods: { delivery: string[]; payment: string[] }
  initial_status: number | null
  /** Whether «guest or visitor» means anything: only with the visitors installed. */
  visitors: boolean
}

export interface OrderQuery {
  /** `auto` — «New» while it holds any order, otherwise «All» (decision 92). */
  view?: OrdersView | 'auto'
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
  /** One visitor's orders — the tab «Orders» of the visitor's card. */
  user?: number
  delivery?: string
  payment?: string
  source?: string
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
  /** A refund's line and a reversal's: the record it is about (decision 98). */
  refund_id?: number
  /** A refund's line: the record was reversed later, by mistake. */
  reversed?: boolean
  /** A message sent again by hand (decision 99): its event, in the panel's words. */
  event?: string
  event_label?: string
}

/**
 * A note of the order (decision 90): a line of its history of the kind `note`, which never
 * changes. `author` is the administrator where one is known; otherwise `source` names who.
 */
export interface OrderNote {
  id: number
  body: string
  source: string
  author: { id: number; name: string; avatar: string | null } | null
  created_at: string | null
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
  /** The account the order belongs to as it is now; name and e-mail are null for one deleted. */
  visitor: { id: number; name: string | null; email: string | null } | null
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
  /** The lines and their units; a set is one line, its parts are not counted apart. */
  count: { lines: number; units: number; refunded_units: number }
  /** The refunds recorded, oldest first, each with its reason and the reference of the operation. */
  refunds: OrderRefundEntry[]
  blocks: { key: string; summary: string | null; value: Record<string, unknown> }[]
  timeline: TimelineLine[]
  /** The note pinned over the feed «Notes» (decision 91); null — none. */
  manager_note: string | null
  /** The feed «Notes» under the tabs, oldest first. */
  notes: OrderNote[]
  /** The log of the notifications module about the order; null without the module. */
  notifications: OrderDelivery[] | null
  actions: {
    status: boolean
    paid: boolean
    received: boolean
    cancel: boolean
    details: boolean
    /** Write a note (`orders.manage`); reading them is the card's. */
    note: boolean
    refund: boolean
    /** Reverse a refund record made by mistake (decision 98), each one not reversed yet. */
    refund_reverse: boolean
    /** Link and unlink a visitor — only with the visitors installed. */
    visitor: boolean
    /** Finding one needs the right to read the customers as well. */
    visitor_search: boolean
    /** Send a row of «Notifications» again (decision 99) — only with the notifications module. */
    resend?: boolean
    /** «Send the buyer a letter», and why it cannot be sent now — null when it can. */
    notify?: boolean
    notify_reason?: string | null
  }
}

/** A customer the dialog of the link offers. */
export interface VisitorChoice {
  id: number
  name: string
  email: string
  phone: string | null
  /** The order belongs to them now. */
  current: boolean
  /** The address of the order is theirs. */
  same_email: boolean
}

interface VisitorBrief {
  id: number
  name: string | null
  email: string | null
}

/** What a link or an unlink does, from the service's dry run. */
export interface VisitorLinkPreview {
  outcome: 'linked' | 'unlinked' | 'unchanged'
  visitor: VisitorBrief | null
  previous: VisitorBrief | null
  dry_run: boolean
}

/** One row of the log of the notifications module: a channel, a recipient, what came of it. */
export interface OrderDelivery {
  id: number
  event: string
  /** The event in the panel's words; null for an event not of the orders. */
  event_label?: string | null
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
  /** «Send again» (decision 99): a row that went, failed or got stuck, to somebody. */
  resendable?: boolean
  /** Sent by hand, and the administrator who did — null when unknown. */
  manual?: boolean
  manual_by?: string | null
}

/** One event «Send the buyer a letter» offers (decision 99), and why it cannot be sent. */
export interface BuyerEvent {
  key: string
  label: string
  /** The order has a reason for it: it was placed, paid, cancelled… */
  basis: boolean
  /** Its channels that write to the buyer's e-mail. */
  channels: { id: number; name: string; provider: string }[]
  available: boolean
  reason: string | null
  reason_label: string | null
}

export interface BuyerEvents {
  email: string | null
  reason: string | null
  reason_label: string | null
  events: BuyerEvent[]
}

/** What a letter by hand would send (a dry run): each channel's message. */
export interface NotifyPreview {
  event: string
  dry_run: boolean
  deliveries: {
    id: number | null
    channel: string
    provider: string
    recipient: string
    status: string
  }[]
  previews: {
    channel: string
    provider: string
    recipient: string
    subject: string | null
    body: string
    plain: string
    html: boolean
  }[]
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

export interface OrderRefundEntry {
  id: number
  at: string | null
  amount: string
  reason: string | null
  reference: string | null
  /** Reversed by mistake (decision 98): it stays, struck through, and counts no more. */
  reversed: { at: string | null; reason: string | null } | null
}

/** What reversing a refund record does, from the service's own dry run (decision 98). */
export interface RefundReversalPreview {
  refund_id: number
  amount: number
  amount_formatted: string
  tenders: Record<number, number>
  units: number
  refunded_total: number
  refunded_total_formatted: string
  money_left: number
  money_left_formatted: string
  tenders_left: Record<number, number>
  items_left: Record<number, number>
  milestone_removed: { key: MilestoneKey; label: string } | null
  refusal: string | null
  message: string | null
  dry_run: boolean
}

export interface CancelConsequences {
  was_paid: boolean
  money_to_refund: number
  money_to_refund_text: string | null
  tenders: { label: string; amount: string }[]
  outcome: string
  /** The status the cancellation moves the order into (decision 88); null — it stays. */
  status: (MoveConsequences & { id: number; name: string }) | null
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
  /** How many come into the status that cancels, and what their buyers see of it. */
  moved: number
  public_changed: number
  notifications: number
  status_id: number | null
  status: { id: number; name: string } | null
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

/* ------------------------------------------------------------------ «New order» (§29.2) ----- */

/** A choice a line of a found product must name — a size, a colour — as its provider offers it. */
export interface ProductChoiceOption {
  key: string
  label: string
  /** A value goes back exactly as given: an integer stays an integer. */
  values: { value: string | number; label: string }[]
}

/** A product found for a line, through whichever module sells it. */
export interface ProductChoice {
  type: string
  ref: number
  name: string
  sku: string | null
  /** What tells two of one name apart: a category, a brand. */
  hint: string | null
  image: string | null
  price: number
  price_formatted: string | null
  /** Taken off the site, out of stock or with its price on request: shown, not offered. */
  available: boolean
  options: ProductChoiceOption[]
}

export interface NewOrderMeta {
  locales: { code: string; name: string }[]
  /** The site's own language: what an order is in unless the manager says otherwise. */
  locale: string
}

/** A line as «Add to cart» takes it; the price is never the manager's (§18). */
export interface NewOrderLine {
  type: string
  ref: number
  qty: number
  options?: Record<string, string | number>
}

export interface NewOrderContact {
  first_name: string
  last_name: string
  phone: string
  email?: string
}

export interface NewOrderInput {
  lines: NewOrderLine[]
  locale: string
  customer: NewOrderContact
  /** Empty — the buyer takes it. */
  recipient: NewOrderContact | null
  comment: string
  /** The form's token: a second press is the order of the first. */
  key: string
}

/** A line of the count, as the cart's chain answers it (`CartState::toArray()`). */
export interface CountedLine {
  id: number | string
  name: string
  qty: number
  options_label: string | null
  price_formatted: string | null
  line_total_formatted: string | null
  available: boolean
  label: string | null
  children: CountedLine[]
}

/** The lines counted as the order would count them, before the contacts (§29.2). */
export interface NewOrderPreview {
  orderable: boolean
  /** Why it cannot be ordered, in the panel's words. */
  message: string | null
  totals: {
    lines: CountedLine[]
    subtotal_formatted: string | null
    adjustments: { label: string; amount_formatted: string | null }[]
    total_formatted: string | null
    tenders: { label: string; amount_formatted: string | null }[]
    payable: number
    payable_formatted: string | null
    warnings: { code: string; line: number | string | null; message: string }[]
  }
}
