import type { AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type {
  CancelConsequences,
  MassCancelAnswer,
  MassStatusAnswer,
  MoveConsequences,
  OrderDetail,
  OrderQuery,
  OrdersMeta,
  OrdersPage,
  RefundInput,
  RefundPreview,
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

export interface OrdersApi {
  list(query?: OrderQuery): Promise<OrdersPage>
  meta(): Promise<OrdersMeta>
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
  previewCancel(id: number): Promise<CancelConsequences>
  cancel(id: number, reason: string): Promise<OrderDetail>
  saveDetails(id: number, values: ScreenModel): Promise<OrderDetail>
  previewRefund(id: number, input: RefundInput): Promise<RefundPreview>
  refund(id: number, input: RefundInput): Promise<OrderDetail>
  massStatus(
    ids: number[],
    statusId: number,
    note: string,
    dryRun: boolean,
  ): Promise<MassStatusAnswer>
  massCancel(ids: number[], reason: string, dryRun: boolean): Promise<MassCancelAnswer>
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
  }
}
