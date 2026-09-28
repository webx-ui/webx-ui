import { reorderItems, type AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type { TariffDetail, TariffQuery, TariffRow, TariffsList } from './types'

export interface TariffsApi {
  /** Every tariff, with the groups the list can be narrowed to. */
  list(query?: TariffQuery): Promise<TariffsList>
  get(id: number): Promise<TariffDetail>
  /**
   * A new tariff out of the values of its form, in one request: the form is filled in first and
   * the record made on the first save, so nobody leaves an empty card behind.
   */
  create(values: ScreenModel): Promise<TariffDetail>
  /** Refused with a 422 under the name of the field — a line of the list under `features.<n>.text`. */
  save(id: number, values: ScreenModel): Promise<TariffDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<TariffRow>
  /** The order on screen: of the whole list, or — with `category` — of that group alone. */
  reorder(ids: number[], category?: number | null): Promise<void>
}

/** The path of the list under the panel's API, and of its order (`CategoryRoutes::items()`). */
export const TARIFFS_API = 'tariffs'

/**
 * Everything under `/tariffs`, below the panel's API path — except the groups, which are the
 * panel's shared categories and are asked for with `createCategoriesApi(admin, 'tariffs/categories')`.
 */
export function createTariffsApi(admin: AdminContext): TariffsApi {
  const base = `${admin.apiPath}/${TARIFFS_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      // Only what was asked for: `category=` would ask for the tariffs of no group.
      if (query.search) search.set('search', query.search)
      if (query.category != null) search.set('category', String(query.category))
      if (query.trashed) search.set('trashed', '1')

      return admin.http.get<TariffsList>(search.size > 0 ? `${base}?${search}` : base)
    },
    get: (id) => admin.http.get<{ data: TariffDetail }>(`${base}/${id}`).then(data),
    create: (values) => admin.http.post<{ data: TariffDetail }>(base, { values }).then(data),
    save: (id, values) =>
      admin.http.put<{ data: TariffDetail }>(`${base}/${id}`, { values }).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    // The resource of the list line, and not the form's pair: a restored tariff stays closed.
    restore: (id) => admin.http.post<{ data: TariffRow }>(`${base}/${id}/restore`, {}).then(data),
    reorder: (ids, category = null) => reorderItems(admin, TARIFFS_API, ids, category),
  }
}
