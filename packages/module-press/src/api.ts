import { reorderItems, type AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type { OutletDetail, OutletQuery, OutletRow, OutletsList } from './types'

export interface PressApi {
  list(query?: OutletQuery): Promise<OutletsList>
  get(id: number): Promise<OutletDetail>
  /**
   * A new outlet out of the values of its form, articles included, in one request: the form is
   * filled in first and the record made on the first save, so nobody leaves an empty outlet behind.
   */
  create(values: ScreenModel): Promise<OutletDetail>
  /** Refused with a 422 under the name of the field — `articles.<n>.<field>` for an article. */
  save(id: number, values: ScreenModel): Promise<OutletDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<OutletRow>
  /** The order of the whole list. The order of the articles is the order of the form's rows. */
  reorder(ids: number[]): Promise<void>
}

/** The path of the section under the panel's API, and of its order. */
export const PRESS_API = 'press'

/** Everything under `/press`, below the panel's API path (§4.10). */
export function createPressApi(admin: AdminContext): PressApi {
  const base = `${admin.apiPath}/${PRESS_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      if (query.search) search.set('search', query.search)
      if (query.trashed) search.set('trashed', '1')

      return admin.http.get<OutletsList>(search.size > 0 ? `${base}?${search}` : base)
    },
    get: (id) => admin.http.get<{ data: OutletDetail }>(`${base}/${id}`).then(data),
    create: (values) => admin.http.post<{ data: OutletDetail }>(base, { values }).then(data),
    save: (id, values) =>
      admin.http.put<{ data: OutletDetail }>(`${base}/${id}`, { values }).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    // The resource of the list line, and not the form's answer: a restored outlet stays closed.
    restore: (id) => admin.http.post<{ data: OutletRow }>(`${base}/${id}/restore`, {}).then(data),
    reorder: (ids) => reorderItems(admin, PRESS_API, ids, null),
  }
}
