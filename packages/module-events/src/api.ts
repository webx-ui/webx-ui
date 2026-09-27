import type { AdminContext } from '@webx-ui/module-admin'
import type {
  EventDetail,
  EventInput,
  EventQuery,
  EventRow,
  EventSave,
  EventsPage,
  EventVersion,
} from './types'

export interface EventsApi {
  /** A page of events, with what the list can be narrowed by. */
  list(query?: EventQuery): Promise<EventsPage>
  /** One event as its editor opens it: the record, the screen's values and the revision. */
  get(id: number): Promise<EventDetail>
  /** A new event, made in one transaction: a refused address leaves no bare row behind. */
  create(input: EventInput): Promise<EventDetail>
  /**
   * Save the draft. Refused with a 409 when the revision is stale — the body is an
   * {@link EventConflict} with the event as it now is.
   */
  save(id: number, input: EventSave): Promise<EventDetail>
  /** Throw away what is waiting and keep what the site is showing. */
  discard(id: number): Promise<EventDetail>
  /**
   * A copy as a draft that was never published: every field, the categories and the services,
   * the same title and an address with the next free suffix (decision 9). The answer is the
   * copy's form, so the editor can open it straight away.
   */
  duplicate(id: number): Promise<EventDetail>
  publish(id: number): Promise<EventRow>
  unpublish(id: number): Promise<EventRow>
  remove(id: number): Promise<void>
  restore(id: number): Promise<EventRow>
  /** The publications, newest first. */
  versions(id: number): Promise<EventVersion[]>
  /** An old publication becomes the draft; putting it on the site is a separate step. */
  restoreVersion(id: number, number: number): Promise<EventDetail>
}

/** The path of the list under the panel's API. */
export const EVENTS_API = 'events'

/**
 * Everything under `/events`, below the panel's API path (§4.10) — except the categories, which
 * are the panel's shared category API under `events/categories`.
 */
export function createEventsApi(admin: AdminContext): EventsApi {
  const base = `${admin.apiPath}/${EVENTS_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      // Always said: the server's default is the upcoming ones too, but a list that asks for
      // what it shows does not depend on somebody else's default staying put.
      search.set('when', query.when ?? 'upcoming')

      // Only what was asked for: `category=` would ask for the events of no category.
      if (query.q) search.set('q', query.q)
      if (query.category != null) search.set('category', String(query.category))
      if (query.service != null) search.set('service', String(query.service))
      if (query.status) search.set('status', query.status)
      if (query.trashed) search.set('trashed', '1')
      if (query.page && query.page > 1) search.set('page', String(query.page))
      if (query.per_page) search.set('per_page', String(query.per_page))

      return admin.http
        .get<{
          data: EventRow[]
          meta: Omit<EventsPage, 'data' | 'filters'>
          filters: EventsPage['filters']
        }>(`${base}?${search}`)
        .then((body) => ({ ...body.meta, data: body.data, filters: body.filters }))
    },
    get: (id) => admin.http.get<{ data: EventDetail }>(`${base}/${id}`).then(data),
    create: (input) => admin.http.post<{ data: EventDetail }>(base, input).then(data),
    save: (id, input) => admin.http.put<{ data: EventDetail }>(`${base}/${id}`, input).then(data),
    discard: (id) => admin.http.post<{ data: EventDetail }>(`${base}/${id}/discard`, {}).then(data),
    duplicate: (id) =>
      admin.http.post<{ data: EventDetail }>(`${base}/${id}/duplicate`, {}).then(data),
    publish: (id) => admin.http.post<{ data: EventRow }>(`${base}/${id}/publish`, {}).then(data),
    unpublish: (id) =>
      admin.http.post<{ data: EventRow }>(`${base}/${id}/unpublish`, {}).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) => admin.http.post<{ data: EventRow }>(`${base}/${id}/restore`, {}).then(data),
    versions: (id) => admin.http.get<{ data: EventVersion[] }>(`${base}/${id}/versions`).then(data),
    restoreVersion: (id, number) =>
      admin.http
        .post<{ data: EventDetail }>(`${base}/${id}/versions/${number}/restore`, {})
        .then(data),
  }
}
