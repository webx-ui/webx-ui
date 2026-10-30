import type { AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type {
  BuyerEvents,
  NotifyPreview,
  CancelConsequences,
  MassCancelAnswer,
  MassStatusAnswer,
  MoveConsequences,
  NewOrderInput,
  NewOrderMeta,
  NewOrderPreview,
  OrderDetail,
  OrderGlance,
  OrderQuery,
  OrdersMeta,
  OrdersPage,
  OrdersSettings,
  OrdersSummaryData,
  PaymentInput,
  ItemsEditInput,
  ItemsEditPreview,
  PaymentPreview,
  ProductChoice,
  PublicStatusInput,
  RefundInput,
  RefundPreview,
  RefundReversalPreview,
  StageInput,
  StatusArrangement,
  StatusBoardData,
  StatusConsequences,
  VisitorChoice,
  VisitorLinkPreview,
} from './types'

/** Where «Orders» answer under the panel's API (§11). */
export const ORDERS_API = 'orders'

/** The list's question as a query string; a filter left unset is not sent at all. */
export function orderSearch(query: OrderQuery): URLSearchParams {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') continue

    search.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }

  return search
}

/** The refund as the server reads it: the empty parts left out. */
function refundBody(input: RefundInput, dryRun: boolean) {
  const tenders = Object.fromEntries(
    Object.entries(input.tenders).filter(([, amount]) => amount !== '' && Number(amount) > 0),
  )
  const items = Object.fromEntries(Object.entries(input.items).filter(([, qty]) => qty > 0))

  return {
    amount: input.amount === '' ? null : input.amount,
    tenders,
    items,
    reference: input.reference || null,
    reason: input.reason || null,
    dry_run: dryRun,
  }
}

/** A top-up as the server reads it: an empty day is today, empty words are none. */
function paymentBody(input: PaymentInput, dryRun: boolean) {
  return {
    amount: input.amount,
    reference: input.reference || null,
    at: input.at || null,
    note: input.note || null,
    dry_run: dryRun,
  }
}

/** An edit of the lines as the server reads it; the reason is asked for only on saving. */
function itemsBody(input: ItemsEditInput) {
  return {
    revision: input.revision,
    add: input.add,
    qty: input.qty,
    remove: input.remove,
    reason: input.reason || null,
    message: input.message || null,
    notify: input.notify,
  }
}

export interface OrdersApi {
  list(query?: OrderQuery): Promise<OrdersPage>
  meta(): Promise<OrdersMeta>
  /** The figures over the list, asked apart so that the rows never wait for them. */
  summary(): Promise<OrdersSummaryData>
  /** What an opened row shows. */
  glance(id: number): Promise<OrderGlance>
  get(id: number): Promise<OrderDetail>
  /** The line of consequences under the choice of a status — nothing is written. */
  previewStatus(id: number, statusId: number): Promise<MoveConsequences>
  moveTo(id: number, statusId: number, note: string): Promise<OrderDetail>
  mark(
    id: number,
    milestone: 'paid' | 'received',
    reference: string,
    note: string,
  ): Promise<OrderDetail>
  /** A top-up of a paid order (§36.3): the balance after it, then the card. */
  previewPayment(id: number, input: PaymentInput): Promise<PaymentPreview>
  recordPayment(id: number, input: PaymentInput): Promise<OrderDetail>
  /** «Change the lines» (§42.1): the live totals, then the card with the edit beside it. */
  previewItems(id: number, input: ItemsEditInput): Promise<ItemsEditPreview>
  saveItems(id: number, input: ItemsEditInput): Promise<OrderDetail>
  previewCancel(id: number): Promise<CancelConsequences>
  cancel(id: number, reason: string): Promise<OrderDetail>
  saveDetails(id: number, values: ScreenModel): Promise<OrderDetail>
  previewRefund(id: number, input: RefundInput): Promise<RefundPreview>
  refund(id: number, input: RefundInput): Promise<OrderDetail>
  /** What reversing a refund record does (decision 98) — nothing is written. */
  previewReverse(id: number, refundId: number): Promise<RefundReversalPreview>
  reverseRefund(id: number, refundId: number, reason: string): Promise<OrderDetail>
  /** A row of the order's «Notifications» sent again by hand (decision 99). */
  resend(id: number, deliveryId: number): Promise<OrderDetail>
  /** What «Send the buyer a letter» chooses from. */
  buyerEvents(id: number): Promise<BuyerEvents>
  /** Each channel's letter as it would go — nothing is sent. */
  previewNotify(id: number, event: string): Promise<NotifyPreview>
  notify(id: number, event: string): Promise<OrderDetail>
  massStatus(
    ids: number[],
    statusId: number,
    note: string,
    dryRun: boolean,
  ): Promise<MassStatusAnswer>
  massCancel(ids: number[], reason: string, dryRun: boolean): Promise<MassCancelAnswer>
  /** Both levels of the statuses with the orders in each (§11.4). */
  statuses(): Promise<StatusBoardData>
  savePublic(id: number | null, input: PublicStatusInput): Promise<StatusBoardData>
  /** `move_to` takes the group's stages elsewhere first; a dry run says what it does. */
  previewRemovePublic(id: number, moveTo: number | null): Promise<StatusConsequences>
  removePublic(id: number, moveTo: number | null): Promise<StatusBoardData>
  restorePublic(id: number): Promise<StatusBoardData>
  /** Moved to another group, a stage's buyers are counted first. */
  previewStage(id: number, input: StageInput): Promise<StatusConsequences>
  saveStage(id: number | null, input: StageInput): Promise<StatusBoardData>
  previewRemoveStage(id: number): Promise<StatusConsequences>
  removeStage(id: number): Promise<StatusBoardData>
  restoreStage(id: number): Promise<StatusBoardData>
  previewArrange(arrangement: StatusArrangement): Promise<StatusConsequences>
  arrange(arrangement: StatusArrangement): Promise<StatusBoardData>
  settings(): Promise<OrdersSettings>
  saveSettings(values: ScreenModel): Promise<OrdersSettings>
  /** Customers to link the order to (§13.3), by a part of the name, the e-mail or the phone. */
  searchVisitors(id: number, search: string): Promise<VisitorChoice[]>
  previewLink(id: number, userId: number): Promise<VisitorLinkPreview>
  link(id: number, userId: number): Promise<OrderDetail>
  previewUnlink(id: number): Promise<VisitorLinkPreview>
  unlink(id: number): Promise<OrderDetail>
  /** A note in the order's history (decision 90); the answer is the card with it. */
  note(id: number, body: string): Promise<OrderDetail>
  /** «New order» (§29.2): the languages an order may be in. */
  newMeta(): Promise<NewOrderMeta>
  /** Products for a line, found by a word through whichever module sells them. */
  findProducts(search: string, locale: string): Promise<ProductChoice[]>
  /** The lines counted as the order would count them; nothing is written. */
  previewNew(lines: NewOrderInput['lines'], locale: string): Promise<NewOrderPreview>
  create(input: NewOrderInput): Promise<{ id: number; number: string }>
}

export function createOrdersApi(admin: AdminContext): OrdersApi {
  const base = `${admin.apiPath}/${ORDERS_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = orderSearch(query)

      return admin.http.get<OrdersPage>(search.size > 0 ? `${base}?${search}` : base)
    },
    meta: () => admin.http.get<{ data: OrdersMeta }>(`${base}/meta`).then(data),
    summary: () => admin.http.get<{ data: OrdersSummaryData }>(`${base}/summary`).then(data),
    glance: (id) => admin.http.get<{ data: OrderGlance }>(`${base}/${id}/glance`).then(data),
    get: (id) => admin.http.get<{ data: OrderDetail }>(`${base}/${id}`).then(data),
    previewStatus: (id, statusId) =>
      admin.http
        .post<{ data: MoveConsequences }>(`${base}/${id}/status`, {
          status_id: statusId,
          dry_run: true,
        })
        .then(data),
    moveTo: (id, statusId, note) =>
      admin.http
        .post<{ data: OrderDetail }>(`${base}/${id}/status`, {
          status_id: statusId,
          note: note || null,
        })
        .then(data),
    mark: (id, milestone, reference, note) =>
      admin.http
        .post<{ data: OrderDetail }>(`${base}/${id}/milestones`, {
          milestone,
          reference: reference || null,
          note: note || null,
        })
        .then(data),
    previewPayment: (id, input) =>
      admin.http
        .post<{ data: PaymentPreview }>(`${base}/${id}/payments`, paymentBody(input, true))
        .then(data),
    recordPayment: (id, input) =>
      admin.http
        .post<{ data: OrderDetail }>(`${base}/${id}/payments`, paymentBody(input, false))
        .then(data),
    previewItems: (id, input) =>
      admin.http
        .post<{ data: ItemsEditPreview }>(`${base}/${id}/items/preview`, itemsBody(input))
        .then(data),
    saveItems: (id, input) =>
      admin.http.put<{ data: OrderDetail }>(`${base}/${id}/items`, itemsBody(input)).then(data),
    previewCancel: (id) =>
      admin.http
        .post<{ data: CancelConsequences }>(`${base}/${id}/cancel`, { dry_run: true })
        .then(data),
    cancel: (id, reason) =>
      admin.http.post<{ data: OrderDetail }>(`${base}/${id}/cancel`, { reason }).then(data),
    saveDetails: (id, values) =>
      admin.http.put<{ data: OrderDetail }>(`${base}/${id}/details`, { values }).then(data),
    previewRefund: (id, input) =>
      admin.http
        .post<{ data: RefundPreview }>(`${base}/${id}/refunds`, refundBody(input, true))
        .then(data),
    refund: (id, input) =>
      admin.http
        .post<{ data: OrderDetail }>(`${base}/${id}/refunds`, refundBody(input, false))
        .then(data),
    previewReverse: (id, refundId) =>
      admin.http
        .post<{ data: RefundReversalPreview }>(`${base}/${id}/refunds/${refundId}/reverse`, {
          dry_run: true,
        })
        .then(data),
    reverseRefund: (id, refundId, reason) =>
      admin.http
        .post<{ data: OrderDetail }>(`${base}/${id}/refunds/${refundId}/reverse`, { reason })
        .then(data),
    resend: (id, deliveryId) =>
      admin.http
        .post<{ data: OrderDetail }>(`${base}/${id}/deliveries/${deliveryId}/resend`, {})
        .then(data),
    buyerEvents: (id) => admin.http.get<{ data: BuyerEvents }>(`${base}/${id}/notify`).then(data),
    previewNotify: (id, event) =>
      admin.http
        .post<{ data: NotifyPreview }>(`${base}/${id}/notify`, { event, dry_run: true })
        .then(data),
    notify: (id, event) =>
      admin.http.post<{ data: OrderDetail }>(`${base}/${id}/notify`, { event }).then(data),
    massStatus: (ids, statusId, note, dryRun) =>
      admin.http
        .post<{ data: MassStatusAnswer }>(`${base}/status`, {
          ids,
          status_id: statusId,
          note: note || null,
          dry_run: dryRun,
        })
        .then(data),
    massCancel: (ids, reason, dryRun) =>
      admin.http
        .post<{ data: MassCancelAnswer }>(`${base}/cancel`, { ids, reason, dry_run: dryRun })
        .then(data),
    statuses: () => admin.http.get<{ data: StatusBoardData }>(`${base}/statuses`).then(data),
    savePublic: (id, input) =>
      (id === null
        ? admin.http.post<{ data: StatusBoardData }>(`${base}/statuses/public`, input)
        : admin.http.put<{ data: StatusBoardData }>(`${base}/statuses/public/${id}`, input)
      ).then(data),
    previewRemovePublic: (id, moveTo) =>
      admin.http
        .delete<{ data: StatusConsequences }>(`${base}/statuses/public/${id}`, {
          query: { move_to: moveTo, dry_run: 1 },
        })
        .then(data),
    removePublic: (id, moveTo) =>
      admin.http
        .delete<{ data: StatusBoardData }>(`${base}/statuses/public/${id}`, {
          query: { move_to: moveTo },
        })
        .then(data),
    restorePublic: (id) =>
      admin.http
        .post<{ data: StatusBoardData }>(`${base}/statuses/public/${id}/restore`)
        .then(data),
    previewStage: (id, input) =>
      admin.http
        .put<{ data: StatusConsequences }>(`${base}/statuses/internal/${id}`, {
          ...input,
          dry_run: true,
        })
        .then(data),
    saveStage: (id, input) =>
      (id === null
        ? admin.http.post<{ data: StatusBoardData }>(`${base}/statuses/internal`, input)
        : admin.http.put<{ data: StatusBoardData }>(`${base}/statuses/internal/${id}`, input)
      ).then(data),
    previewRemoveStage: (id) =>
      admin.http
        .delete<{ data: StatusConsequences }>(`${base}/statuses/internal/${id}`, {
          query: { dry_run: 1 },
        })
        .then(data),
    removeStage: (id) =>
      admin.http.delete<{ data: StatusBoardData }>(`${base}/statuses/internal/${id}`).then(data),
    restoreStage: (id) =>
      admin.http
        .post<{ data: StatusBoardData }>(`${base}/statuses/internal/${id}/restore`)
        .then(data),
    previewArrange: (arrangement) =>
      admin.http
        .put<{ data: StatusConsequences }>(`${base}/statuses/arrange`, {
          ...arrangement,
          dry_run: true,
        })
        .then(data),
    arrange: (arrangement) =>
      admin.http.put<{ data: StatusBoardData }>(`${base}/statuses/arrange`, arrangement).then(data),
    settings: () => admin.http.get<{ data: OrdersSettings }>(`${base}/settings`).then(data),
    saveSettings: (values) =>
      admin.http.put<{ data: OrdersSettings }>(`${base}/settings`, { values }).then(data),
    searchVisitors: (id, search) =>
      admin.http
        .get<{ data: VisitorChoice[] }>(`${base}/${id}/visitors?${new URLSearchParams({ search })}`)
        .then(data),
    previewLink: (id, userId) =>
      admin.http
        .post<{ data: VisitorLinkPreview }>(`${base}/${id}/visitor`, {
          user_id: userId,
          dry_run: true,
        })
        .then(data),
    link: (id, userId) =>
      admin.http
        .post<{ data: OrderDetail }>(`${base}/${id}/visitor`, { user_id: userId })
        .then(data),
    previewUnlink: (id) =>
      admin.http
        .delete<{ data: VisitorLinkPreview }>(`${base}/${id}/visitor`, { query: { dry_run: 1 } })
        .then(data),
    unlink: (id) => admin.http.delete<{ data: OrderDetail }>(`${base}/${id}/visitor`).then(data),
    note: (id, body) =>
      admin.http.post<{ data: OrderDetail }>(`${base}/${id}/notes`, { body }).then(data),
    newMeta: () => admin.http.get<{ data: NewOrderMeta }>(`${base}/new`).then(data),
    findProducts: (search, locale) =>
      admin.http
        .get<{
          data: ProductChoice[]
        }>(`${base}/new/products?${new URLSearchParams({ search, locale })}`)
        .then(data),
    previewNew: (lines, locale) =>
      admin.http
        .post<{ data: NewOrderPreview }>(`${base}/new/preview`, { lines, locale })
        .then(data),
    create: (input) =>
      admin.http.post<{ data: { id: number; number: string } }>(`${base}/new`, input).then(data),
  }
}
