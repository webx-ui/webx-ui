import { reorderItems, type AdminContext } from '@webx-ui/module-admin'
import type {
  ServiceDetail,
  ServiceInput,
  ServiceQuery,
  ServiceRow,
  ServiceSave,
  ServicesList,
  ServiceVersion,
} from './types'

export interface ServicesApi {
  /** Every service, with the categories the list can be narrowed to. */
  list(query?: ServiceQuery): Promise<ServicesList>
  /** One service as its editor opens it: the record, the screen's values and the revision. */
  get(id: number): Promise<ServiceDetail>
  create(input: ServiceInput): Promise<ServiceRow>
  /**
   * Save the draft. Refused with a 409 when the revision is stale — the body is a
   * {@link ServiceConflict} with the service as it now is.
   */
  save(id: number, input: ServiceSave): Promise<ServiceDetail>
  /** Throw away what is waiting and keep what the site is showing. */
  discard(id: number): Promise<ServiceDetail>
  publish(id: number): Promise<ServiceRow>
  unpublish(id: number): Promise<ServiceRow>
  remove(id: number): Promise<void>
  restore(id: number): Promise<ServiceRow>
  /** The publications, newest first. */
  versions(id: number): Promise<ServiceVersion[]>
  /** An old publication becomes the draft; putting it on the site is a separate step. */
  restoreVersion(id: number, number: number): Promise<ServiceDetail>
  /** The order on screen: of the whole list, or — with `category` — of that category alone. */
  reorder(ids: number[], category?: number | null): Promise<void>
}

/** The path of the list under the panel's API, and of its order (`CategoryRoutes::items()`). */
export const SERVICES_API = 'services'

/**
 * Everything under `/services`, below the panel's API path — except the categories, which are the
 * panel's shared ones and are asked for with `createCategoriesApi(admin, 'services/categories')`.
 */
export function createServicesApi(admin: AdminContext): ServicesApi {
  const base = `${admin.apiPath}/${SERVICES_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      // Only what was asked for: `category=` would ask for the services of no category.
      if (query.q) search.set('q', query.q)
      if (query.category != null) search.set('category', String(query.category))
      if (query.status) search.set('status', query.status)
      if (query.trashed) search.set('trashed', '1')

      return admin.http.get<ServicesList>(search.size > 0 ? `${base}?${search}` : base)
    },
    get: (id) => admin.http.get<{ data: ServiceDetail }>(`${base}/${id}`).then(data),
    create: (input) => admin.http.post<{ data: ServiceRow }>(base, input).then(data),
    save: (id, input) => admin.http.put<{ data: ServiceDetail }>(`${base}/${id}`, input).then(data),
    discard: (id) =>
      admin.http.post<{ data: ServiceDetail }>(`${base}/${id}/discard`, {}).then(data),
    publish: (id) => admin.http.post<{ data: ServiceRow }>(`${base}/${id}/publish`, {}).then(data),
    unpublish: (id) =>
      admin.http.post<{ data: ServiceRow }>(`${base}/${id}/unpublish`, {}).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) => admin.http.post<{ data: ServiceRow }>(`${base}/${id}/restore`, {}).then(data),
    versions: (id) =>
      admin.http.get<{ data: ServiceVersion[] }>(`${base}/${id}/versions`).then(data),
    restoreVersion: (id, number) =>
      admin.http
        .post<{ data: ServiceDetail }>(`${base}/${id}/versions/${number}/restore`, {})
        .then(data),
    reorder: (ids, category = null) => reorderItems(admin, SERVICES_API, ids, category),
  }
}
