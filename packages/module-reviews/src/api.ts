import { reorderItems, type AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type { ReviewDetail, ReviewQuery, ReviewRow, ReviewsList } from './types'

export interface ReviewsApi {
  /** Every review, with the categories the list can be narrowed to. */
  list(query?: ReviewQuery): Promise<ReviewsList>
  get(id: number): Promise<ReviewDetail>
  /**
   * A new review out of the values of its form, in one request: the form is filled in first and
   * the record made on the first save, so nobody leaves an empty review behind.
   */
  create(values: ScreenModel): Promise<ReviewDetail>
  /** Refused with a 422 under the name of the field. */
  save(id: number, values: ScreenModel): Promise<ReviewDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<ReviewRow>
  /** The order on screen: of the whole list, or — with `category` — of that category alone. */
  reorder(ids: number[], category?: number | null): Promise<void>
}

/** The path of the list under the panel's API, and of its order (`CategoryRoutes::items()`). */
export const REVIEWS_API = 'reviews'

/**
 * Everything under `/reviews`, below the panel's API path — except the categories, which are the
 * panel's shared ones and are asked for with `createCategoriesApi(admin, 'reviews/categories')`.
 */
export function createReviewsApi(admin: AdminContext): ReviewsApi {
  const base = `${admin.apiPath}/${REVIEWS_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      // Only what was asked for: `category=` would ask for the reviews of no category.
      if (query.search) search.set('search', query.search)
      if (query.category != null) search.set('category', String(query.category))
      if (query.trashed) search.set('trashed', '1')

      return admin.http.get<ReviewsList>(search.size > 0 ? `${base}?${search}` : base)
    },
    get: (id) => admin.http.get<{ data: ReviewDetail }>(`${base}/${id}`).then(data),
    create: (values) => admin.http.post<{ data: ReviewDetail }>(base, { values }).then(data),
    save: (id, values) =>
      admin.http.put<{ data: ReviewDetail }>(`${base}/${id}`, { values }).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    // The resource of the list line, and not the form's pair: a restored review stays closed.
    restore: (id) => admin.http.post<{ data: ReviewRow }>(`${base}/${id}/restore`, {}).then(data),
    reorder: (ids, category = null) => reorderItems(admin, REVIEWS_API, ids, category),
  }
}
