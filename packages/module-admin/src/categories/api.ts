import type { ScreenModel } from '@webx-ui/schema'
import type { AdminContext } from '../admin'
import type { CategoriesPayload, CategoryDetail, CategoryRow } from './types'

/**
 * The categories of one module, behind the routes `CategoryRoutes::register()` gives it.
 *
 * The path is the module's (`blog/rubrics`), the answers are everybody's: one controller serves
 * every module, so one client does too.
 */
export interface CategoriesApi {
  /** Every category, in the order of the site's menu. `q` narrows by name or address. */
  list(query?: { q?: string; visible?: boolean }): Promise<CategoriesPayload>
  /** A name, and an address made of it unless one is given. */
  create(input: { title: string; slug?: string }): Promise<CategoryRow>
  get(id: number): Promise<CategoryDetail>
  /** The values of the screen. A refused field comes back as a 422 under its name. */
  save(id: number, values: ScreenModel): Promise<CategoryDetail>
  /** Into the bin — a 422 naming the number of items while it still holds any. */
  remove(id: number): Promise<void>
  restore(id: number): Promise<CategoryRow>
  /** The whole order, as it is on screen. */
  reorder(ids: number[]): Promise<void>
}

export function createCategoriesApi(admin: AdminContext, path: string): CategoriesApi {
  const base = `${admin.apiPath}/${path.replace(/^\/+|\/+$/g, '')}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      if (query.q) search.set('q', query.q)
      if (query.visible) search.set('visible', '1')

      return admin.http.get<CategoriesPayload>(search.size > 0 ? `${base}?${search}` : base)
    },
    create: (input) => admin.http.post<{ data: CategoryRow }>(base, input).then(data),
    get: (id) => admin.http.get<{ data: CategoryDetail }>(`${base}/${id}`).then(data),
    save: (id, values) =>
      admin.http.put<{ data: CategoryDetail }>(`${base}/${id}`, { values }).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) => admin.http.post<{ data: CategoryRow }>(`${base}/${id}/restore`, {}).then(data),
    reorder: (ids) => admin.http.post<void>(`${base}/reorder`, { ids }).then(() => undefined),
  }
}

/**
 * The order of a module's records, written the way it is dragged (§2.5 of the services spec):
 * without `category` the whole list, with it the order inside that one category.
 *
 * `path` is the module's list of records (`services/items`); the server half is
 * `CategoryRoutes::items()`. A module that orders by date — the blog — never calls it.
 */
export function reorderItems(
  admin: AdminContext,
  path: string,
  ids: number[],
  category: number | null = null,
): Promise<void> {
  const base = `${admin.apiPath}/${path.replace(/^\/+|\/+$/g, '')}`

  return admin.http
    .post<void>(`${base}/reorder`, category === null ? { ids } : { ids, category })
    .then(() => undefined)
}
