import type { AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type {
  Administrator,
  ChannelDetail,
  ChannelsList,
  CheckAnswer,
  DeliveriesPage,
  DeliveriesQuery,
  DeliveryRow,
  FoundChat,
  NotificationsMeta,
  ServerPreview,
  TestResult,
} from './types'

export interface NotificationsApi {
  /** The providers and the events the site has. */
  meta(): Promise<NotificationsMeta>
  /** Every channel, the newest first; `event` narrows it (`none` — the ones for any event). */
  channels(filter?: { trashed?: boolean; event?: string }): Promise<ChannelsList>
  get(id: number): Promise<ChannelDetail>
  /** A new channel out of the values of its form; a new channel of an event starts with its sample. */
  create(values: ScreenModel): Promise<ChannelDetail>
  /**
   * Only the fields given are written, so the switch in the list sends `{ enabled }` alone. An
   * empty secret keeps the one saved; `forget` lists the ones to wipe.
   */
  save(id: number, values: ScreenModel): Promise<ChannelDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<ChannelDetail>
  purge(id: number): Promise<void>
  /** A copy, switched off, its secrets carried over on the server. */
  copy(id: number, name?: string): Promise<ChannelDetail>
  /** Sends now, past the queue, on the event's examples — through the form's values, not saved. */
  test(id: number | null, values: ScreenModel, recipient?: string | null): Promise<TestResult[]>
  /** The server's render of the form's values: the check of the live preview. */
  preview(id: number | null, values: ScreenModel, locale?: string): Promise<ServerPreview>
  /** The settings tried without sending: Telegram's getMe, an SMTP handshake. */
  check(id: number | null, values: ScreenModel): Promise<CheckAnswer>
  /** The chats the bot has seen; with a token typed into the form or the saved one. */
  findRecipients(input: {
    provider: string
    channel?: number | null
    token?: string
  }): Promise<{ recipients: FoundChat[]; hint: string | null }>
  administrators(): Promise<Administrator[]>
  deliveries(query?: DeliveriesQuery): Promise<DeliveriesPage>
  retry(id: number): Promise<DeliveryRow>
}

/** The path of the section under the panel's API. */
export const NOTIFICATIONS_API = 'notifications'

function search(query: DeliveriesQuery): string {
  const params = new URLSearchParams()

  for (const [key, value] of Object.entries(query)) {
    if (value === null || value === undefined || value === '') continue
    params.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }

  const text = params.toString()

  return text === '' ? '' : `?${text}`
}

export function createNotificationsApi(admin: AdminContext): NotificationsApi {
  const base = `${admin.apiPath}/${NOTIFICATIONS_API}`
  const data = <T>(body: { data: T }): T => body.data
  const channel = (id: number | null) => (id === null ? base : `${base}/channels/${id}`)

  return {
    meta: () => admin.http.get<{ data: NotificationsMeta }>(`${base}/meta`).then(data),
    channels: (filter = {}) => {
      const params = new URLSearchParams()
      if (filter.trashed) params.set('trashed', '1')
      if (filter.event) params.set('event', filter.event)
      const text = params.toString()

      return admin.http.get<ChannelsList>(`${base}/channels${text === '' ? '' : `?${text}`}`)
    },
    get: (id) => admin.http.get<{ data: ChannelDetail }>(`${base}/channels/${id}`).then(data),
    create: (values) =>
      admin.http.post<{ data: ChannelDetail }>(`${base}/channels`, { values }).then(data),
    save: (id, values) =>
      admin.http.put<{ data: ChannelDetail }>(`${base}/channels/${id}`, { values }).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/channels/${id}`).then(() => undefined),
    restore: (id) =>
      admin.http.post<{ data: ChannelDetail }>(`${base}/channels/${id}/restore`, {}).then(data),
    purge: (id) => admin.http.delete<void>(`${base}/channels/${id}/purge`).then(() => undefined),
    copy: (id, name) =>
      admin.http
        .post<{ data: ChannelDetail }>(`${base}/channels/${id}/copy`, name ? { name } : {})
        .then(data),
    test: (id, values, recipient) =>
      admin.http
        .post<{ data: { results: TestResult[] } }>(`${channel(id)}/test`, {
          values,
          ...(recipient ? { recipient } : {}),
        })
        .then((body) => body.data.results),
    preview: (id, values, locale) =>
      admin.http
        .post<{ data: ServerPreview }>(`${channel(id)}/preview`, { values, locale })
        .then(data),
    check: (id, values) =>
      admin.http
        .post<{ data: CheckAnswer }>(`${base}/check`, { values, ...(id ? { channel: id } : {}) })
        .then(data),
    findRecipients: ({ provider, channel: id, token }) =>
      admin.http
        .post<{ data: { recipients: FoundChat[]; hint: string | null } }>(
          `${base}/find-recipients`,
          { provider, ...(id ? { channel: id } : {}), ...(token ? { token } : {}) },
        )
        .then(data),
    administrators: () =>
      admin.http.get<{ data: Administrator[] }>(`${base}/administrators`).then(data),
    deliveries: (query = {}) =>
      admin.http.get<DeliveriesPage>(`${base}/deliveries${search(query)}`),
    retry: (id) =>
      admin.http.post<{ data: DeliveryRow }>(`${base}/deliveries/${id}/retry`, {}).then(data),
  }
}
