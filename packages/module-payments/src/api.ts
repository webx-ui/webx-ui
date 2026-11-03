import type { AdminContext } from '@webx-ui/module-admin'
import type {
  AttemptDetail,
  AttemptsPage,
  EventDetail,
  ExchangeFilters,
  ExchangePage,
  MethodBlank,
  MethodDetail,
  MethodRow,
  MethodSave,
  MethodType,
  OrderPayment,
} from './types'

/** Only what was asked for: an empty filter is no filter. */
export function query(params: object): string {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === undefined || value === '' || value === false) continue
    search.set(key, String(value === true ? 1 : value))
  }

  return search.size > 0 ? `?${search}` : ''
}

/** Everything under `/payments`, below the panel's API path (spec §13). */
export function createPaymentsApi(admin: AdminContext) {
  const base = `${admin.apiPath}/payments`
  const http = admin.http
  const data = <T>(body: { data: T }): T => body.data

  return {
    methods: () =>
      http
        .get<{ data: { methods: MethodRow[]; types: MethodType[] } }>(`${base}/methods`)
        .then(data),
    method: (id: number) => http.get<{ data: MethodDetail }>(`${base}/methods/${id}`).then(data),
    blank: (type: string) =>
      http.get<{ data: MethodBlank }>(`${base}/methods/blank${query({ type })}`).then(data),
    createMethod: (type: string, body: MethodSave) =>
      http.post<{ data: MethodDetail }>(`${base}/methods`, { type, ...body }).then(data),
    saveMethod: (id: number, body: MethodSave) =>
      http.put<{ data: MethodDetail }>(`${base}/methods/${id}`, body).then(data),
    orderMethods: (ids: number[]) =>
      http.put<void>(`${base}/methods/order`, { ids }).then(() => undefined),
    /** With the stored keys and whatever is typed over them; nothing is saved. */
    testMethod: (id: number, credentials: Record<string, string>) =>
      http
        .post<{
          data: { ok: boolean; message: string | null }
        }>(`${base}/methods/${id}/test`, { credentials })
        .then(data),

    attempts: (params: {
      page?: number
      per_page?: number
      tab?: string
      method?: string
      search?: string
      from?: string
      to?: string
    }) => http.get<AttemptsPage>(`${base}/attempts${query(params)}`),
    attempt: (id: number) => http.get<{ data: AttemptDetail }>(`${base}/attempts/${id}`).then(data),
    checkAttempt: (id: number) =>
      http.post<{ data: AttemptDetail }>(`${base}/attempts/${id}/check`, {}).then(data),

    exchange: (filters: ExchangeFilters) =>
      http.get<{ data: ExchangePage }>(`${base}/exchange${query(filters)}`).then(data),
    event: (id: number) => http.get<{ data: EventDetail }>(`${base}/exchange/${id}`).then(data),
    /** Where «Download» points: an attachment, never drawn in the panel's page. */
    bodyUrl: (id: number, part: 'request' | 'response') => `${base}/exchange/${id}/${part}`,

    orderPayment: (order: number) =>
      http.get<{ data: OrderPayment }>(`${base}/orders/${order}`).then(data),
    markPaid: (
      order: number,
      body: { reference?: string | null; paid_at?: string | null; note?: string | null },
    ) => http.post<{ data: OrderPayment }>(`${base}/orders/${order}/mark-paid`, body).then(data),
    checkOrder: (order: number) =>
      http.post<{ data: OrderPayment }>(`${base}/orders/${order}/check`, {}).then(data),
    switchMethod: (order: number, method: string) =>
      http.post<{ data: OrderPayment }>(`${base}/orders/${order}/method`, { method }).then(data),
    resolve: (order: number, payment: number, note: string | null) =>
      http
        .post<{
          data: OrderPayment
        }>(`${base}/orders/${order}/attempts/${payment}/resolve`, { note })
        .then(data),
  }
}

export type PaymentsApi = ReturnType<typeof createPaymentsApi>
