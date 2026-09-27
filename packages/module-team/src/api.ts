import { reorderItems, type AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type { MemberDetail, MemberQuery, MemberRow, MembersList } from './types'

export interface TeamApi {
  list(query?: MemberQuery): Promise<MembersList>
  get(id: number): Promise<MemberDetail>
  /**
   * A new person out of the values of their form, in one request: the form is filled in first
   * and the record made on the first save, so nobody leaves an empty person behind.
   */
  create(values: ScreenModel): Promise<MemberDetail>
  /** Refused with a 422 under the name of the field — `socials.<n>.url` for a link. */
  save(id: number, values: ScreenModel): Promise<MemberDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<MemberRow>
  /** The one order there is (decision 5). */
  reorder(ids: number[]): Promise<void>
}

/** The path of the section under the panel's API, and of its order. */
export const TEAM_API = 'team'

/** Everything under `/team`, below the panel's API path (§5.7). */
export function createTeamApi(admin: AdminContext): TeamApi {
  const base = `${admin.apiPath}/${TEAM_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      if (query.search) search.set('search', query.search)
      if (query.trashed) search.set('trashed', '1')

      return admin.http.get<MembersList>(search.size > 0 ? `${base}?${search}` : base)
    },
    get: (id) => admin.http.get<{ data: MemberDetail }>(`${base}/${id}`).then(data),
    create: (values) => admin.http.post<{ data: MemberDetail }>(base, { values }).then(data),
    save: (id, values) =>
      admin.http.put<{ data: MemberDetail }>(`${base}/${id}`, { values }).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    // The resource of the list line, and not the form's pair: a restored person stays closed.
    restore: (id) => admin.http.post<{ data: MemberRow }>(`${base}/${id}/restore`, {}).then(data),
    reorder: (ids) => reorderItems(admin, TEAM_API, ids, null),
  }
}
