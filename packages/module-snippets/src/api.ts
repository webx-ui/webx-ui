import type { AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type { SnippetDetail, SnippetRow, SnippetsList } from './types'

export interface SnippetsApi {
  /** Every place with its codes, and the environment the site runs in. */
  list(): Promise<SnippetsList>
  /** The bin, the last thing thrown away first. */
  trashed(): Promise<SnippetRow[]>
  get(id: number): Promise<SnippetDetail>
  /** A new code out of the values of its form: made on the first save, never empty. */
  create(values: ScreenModel): Promise<SnippetDetail>
  /**
   * On the site at once. Only the fields given are written, so the switch in the list sends
   * `{ enabled }` alone. Refused with a 422 under the name of the field.
   */
  save(id: number, values: ScreenModel): Promise<SnippetDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<SnippetDetail>
  purge(id: number): Promise<void>
  /** The whole place in its new order — a code dragged in from another place moves with it. */
  reorder(place: string, ids: number[]): Promise<void>
}

/** The path of the section under the panel's API. */
export const SNIPPETS_API = 'snippets'

export function createSnippetsApi(admin: AdminContext): SnippetsApi {
  const base = `${admin.apiPath}/${SNIPPETS_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: () => admin.http.get<SnippetsList>(base),
    trashed: () => admin.http.get<{ data: SnippetRow[] }>(`${base}?trashed=1`).then(data),
    get: (id) => admin.http.get<{ data: SnippetDetail }>(`${base}/${id}`).then(data),
    create: (values) => admin.http.post<{ data: SnippetDetail }>(base, { values }).then(data),
    save: (id, values) =>
      admin.http.put<{ data: SnippetDetail }>(`${base}/${id}`, { values }).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) =>
      admin.http.post<{ data: SnippetDetail }>(`${base}/${id}/restore`, {}).then(data),
    purge: (id) => admin.http.delete<void>(`${base}/${id}/purge`).then(() => undefined),
    reorder: (place, ids) =>
      admin.http.post<void>(`${base}/reorder`, { place, ids }).then(() => undefined),
  }
}
