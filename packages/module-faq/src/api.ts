import { reorderItems, type AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type { QuestionDetail, QuestionQuery, QuestionRow, QuestionsList } from './types'

export interface FaqApi {
  /** Every question, with the categories the list can be narrowed to. */
  list(query?: QuestionQuery): Promise<QuestionsList>
  get(id: number): Promise<QuestionDetail>
  /**
   * A new question out of the values of its form, in one request: the form is filled in first
   * and the record made on the first save, so nobody leaves an empty question behind.
   */
  create(values: ScreenModel): Promise<QuestionDetail>
  /** Refused with a 422 under the name of the field. */
  save(id: number, values: ScreenModel): Promise<QuestionDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<QuestionRow>
  /** The order on screen: of the whole list, or — with `category` — of that category alone. */
  reorder(ids: number[], category?: number | null): Promise<void>
}

/** The path of the list under the panel's API, and of its order (`CategoryRoutes::items()`). */
export const FAQ_API = 'faq/questions'

/**
 * Everything under `/faq/questions`, below the panel's API path — except the categories, which
 * are the panel's shared ones and are asked for with `createCategoriesApi(admin, 'faq/categories')`.
 */
export function createFaqApi(admin: AdminContext): FaqApi {
  const base = `${admin.apiPath}/${FAQ_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      // Only what was asked for: `category=` would ask for the questions of no category.
      if (query.search) search.set('search', query.search)
      if (query.category != null) search.set('category', String(query.category))
      if (query.trashed) search.set('trashed', '1')

      return admin.http.get<QuestionsList>(search.size > 0 ? `${base}?${search}` : base)
    },
    get: (id) => admin.http.get<{ data: QuestionDetail }>(`${base}/${id}`).then(data),
    create: (values) => admin.http.post<{ data: QuestionDetail }>(base, { values }).then(data),
    save: (id, values) =>
      admin.http.put<{ data: QuestionDetail }>(`${base}/${id}`, { values }).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) => admin.http.post<{ data: QuestionRow }>(`${base}/${id}/restore`, {}).then(data),
    reorder: (ids, category = null) => reorderItems(admin, FAQ_API, ids, category),
  }
}
