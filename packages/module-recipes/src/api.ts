import { reorderItems, type AdminContext } from '@webx-ui/module-admin'
import type {
  RecipeDetail,
  RecipeInput,
  RecipeQuery,
  RecipeRow,
  RecipeSave,
  RecipesList,
  RecipeVersion,
} from './types'

export interface RecipesApi {
  /** Every recipe, with what the list can be narrowed by. */
  list(query?: RecipeQuery): Promise<RecipesList>
  /** One recipe as its editor opens it: the record, the screen's values and the revision. */
  get(id: number): Promise<RecipeDetail>
  /** A new recipe, made in one transaction: a refused address leaves no bare row behind. */
  create(input: RecipeInput): Promise<Pick<RecipeDetail, 'recipe' | 'values'>>
  /**
   * Save the draft. Refused with a 409 when the revision is stale — the body is a
   * {@link RecipeConflict} with the recipe as it now is.
   */
  save(id: number, input: RecipeSave): Promise<RecipeDetail>
  /** Throw away what is waiting and keep what the site is showing. */
  discard(id: number): Promise<RecipeDetail>
  publish(id: number): Promise<RecipeRow>
  unpublish(id: number): Promise<RecipeRow>
  remove(id: number): Promise<void>
  restore(id: number): Promise<RecipeRow>
  /** The publications, newest first. */
  versions(id: number): Promise<RecipeVersion[]>
  /** An old publication becomes the draft; putting it on the site is a separate step. */
  restoreVersion(id: number, number: number): Promise<RecipeDetail>
  /** The one order recipes have — never a category's (decision 4). */
  reorder(ids: number[]): Promise<void>
}

/** The path of the list under the panel's API. */
export const RECIPES_API = 'recipes'

/**
 * Everything under `/recipes`, below the panel's API path — except the categories and the
 * nutrients, which are the panel's shared category API under `recipes/categories` and
 * `recipes/nutrients`.
 */
export function createRecipesApi(admin: AdminContext): RecipesApi {
  const base = `${admin.apiPath}/${RECIPES_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      // Only what was asked for: `category=` would ask for the recipes of no category.
      if (query.q) search.set('q', query.q)
      if (query.category != null) search.set('category', String(query.category))
      if (query.nutrient != null) search.set('nutrient', String(query.nutrient))
      if (query.service != null) search.set('service', String(query.service))
      if (query.status) search.set('status', query.status)
      if (query.trashed) search.set('trashed', '1')

      return admin.http.get<RecipesList>(search.size > 0 ? `${base}?${search}` : base)
    },
    get: (id) => admin.http.get<{ data: RecipeDetail }>(`${base}/${id}`).then(data),
    create: (input) =>
      admin.http.post<{ data: Pick<RecipeDetail, 'recipe' | 'values'> }>(base, input).then(data),
    save: (id, input) => admin.http.put<{ data: RecipeDetail }>(`${base}/${id}`, input).then(data),
    discard: (id) =>
      admin.http.post<{ data: RecipeDetail }>(`${base}/${id}/discard`, {}).then(data),
    publish: (id) => admin.http.post<{ data: RecipeRow }>(`${base}/${id}/publish`, {}).then(data),
    unpublish: (id) =>
      admin.http.post<{ data: RecipeRow }>(`${base}/${id}/unpublish`, {}).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) => admin.http.post<{ data: RecipeRow }>(`${base}/${id}/restore`, {}).then(data),
    versions: (id) =>
      admin.http.get<{ data: RecipeVersion[] }>(`${base}/${id}/versions`).then(data),
    restoreVersion: (id, number) =>
      admin.http
        .post<{ data: RecipeDetail }>(`${base}/${id}/versions/${number}/restore`, {})
        .then(data),
    reorder: (ids) => reorderItems(admin, RECIPES_API, ids, null),
  }
}
