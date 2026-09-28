import { reorderItems, type AdminContext } from '@webx-ui/module-admin'
import type {
  VacanciesList,
  VacancyDetail,
  VacancyInput,
  VacancyQuery,
  VacancyRow,
  VacancySave,
  VacancyVersion,
} from './types'

export interface VacanciesApi {
  /** Every vacancy the query asks for, with what the list can be narrowed by. */
  list(query?: VacancyQuery): Promise<VacanciesList>
  /** One vacancy as its editor opens it: the record, the screen's values and the revision. */
  get(id: number): Promise<VacancyDetail>
  /** A new vacancy, made in one transaction: a refused address leaves no bare row behind. */
  create(input: VacancyInput): Promise<VacancyDetail>
  /**
   * Save the draft. Refused with a 409 when the revision is stale — the body is a
   * {@link VacancyConflict} with the vacancy as it now is.
   */
  save(id: number, input: VacancySave): Promise<VacancyDetail>
  /** Throw away what is waiting and keep what the site is showing. */
  discard(id: number): Promise<VacancyDetail>
  /**
   * A copy as a draft that was never published (decision 20): every field, the categories and
   * the form, the same title and an address with the next free suffix, right after the original.
   * The answer is the copy's form, so the editor can open it straight away.
   */
  duplicate(id: number): Promise<VacancyDetail>
  publish(id: number): Promise<VacancyRow>
  unpublish(id: number): Promise<VacancyRow>
  /**
   * The hiring is over, before its last day — or it is on again. A publication of its own on the
   * server (`is_closed` is published with everything else), so it is refused with a 409 while
   * edits are waiting: closing would put them on the site too.
   */
  close(id: number): Promise<VacancyRow>
  reopen(id: number): Promise<VacancyRow>
  remove(id: number): Promise<void>
  restore(id: number): Promise<VacancyRow>
  /** The order on screen, written as a whole. There is one order and only one. */
  reorder(ids: number[]): Promise<void>
  /** The publications, newest first. */
  versions(id: number): Promise<VacancyVersion[]>
  /** An old publication becomes the draft; putting it on the site is a separate step. */
  restoreVersion(id: number, number: number): Promise<VacancyDetail>
}

/** The path of the list under the panel's API. */
export const VACANCIES_API = 'vacancies'

/**
 * Everything under `/vacancies`, below the panel's API path (§4.11) — except the categories, which
 * are the panel's shared category API under `vacancies/categories`.
 */
export function createVacanciesApi(admin: AdminContext): VacanciesApi {
  const base = `${admin.apiPath}/${VACANCIES_API}`
  const data = <T>(body: { data: T }): T => body.data
  const row = (id: number, action: string) =>
    admin.http.post<{ data: VacancyRow }>(`${base}/${id}/${action}`, {}).then(data)

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      // The bin holds whatever was deleted, open or closed: `state` does not apply there, and
      // saying it would read as though it did.
      if (query.trashed) search.set('trashed', '1')
      // Always said otherwise: the server's default is the open ones too, but a list that asks
      // for what it shows does not depend on somebody else's default staying put.
      else search.set('state', query.state ?? 'open')

      // Only what was asked for: `category=` would ask for the vacancies of no category.
      if (query.q) search.set('q', query.q)
      if (query.category != null) search.set('category', String(query.category))
      if (query.status) search.set('status', query.status)

      return admin.http.get<VacanciesList>(`${base}?${search}`)
    },
    get: (id) => admin.http.get<{ data: VacancyDetail }>(`${base}/${id}`).then(data),
    create: (input) => admin.http.post<{ data: VacancyDetail }>(base, input).then(data),
    save: (id, input) => admin.http.put<{ data: VacancyDetail }>(`${base}/${id}`, input).then(data),
    discard: (id) =>
      admin.http.post<{ data: VacancyDetail }>(`${base}/${id}/discard`, {}).then(data),
    duplicate: (id) =>
      admin.http.post<{ data: VacancyDetail }>(`${base}/${id}/duplicate`, {}).then(data),
    publish: (id) => row(id, 'publish'),
    unpublish: (id) => row(id, 'unpublish'),
    close: (id) => row(id, 'close'),
    reopen: (id) => row(id, 'reopen'),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) => row(id, 'restore'),
    // No `category`, ever: vacancies have one order (§4.11), unlike services.
    reorder: (ids) => reorderItems(admin, VACANCIES_API, ids, null),
    versions: (id) =>
      admin.http.get<{ data: VacancyVersion[] }>(`${base}/${id}/versions`).then(data),
    restoreVersion: (id, number) =>
      admin.http
        .post<{ data: VacancyDetail }>(`${base}/${id}/versions/${number}/restore`, {})
        .then(data),
  }
}
