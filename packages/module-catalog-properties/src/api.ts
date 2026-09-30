import type { AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type {
  CategorySet,
  EffectiveSet,
  PropertiesPage,
  PropertyDetail,
  PropertyInterval,
  PropertyQuery,
  PropertyValue,
  Words,
} from './types'

/** Where the properties answer under the panel's API — `CatalogPropertiesServiceProvider::SOURCE`. */
export const PROPERTIES_API = 'catalog/properties'

/** Where their groups do: the panel's shared category API. */
export const GROUPS_API = 'catalog/property-groups'

/** Where the set in force for a product form is — `catalog/property-sets/{category}`. */
export const SETS_API = 'catalog/property-sets'

/** What a value is written with; a field left out is not touched. */
export interface ValueInput {
  title?: Words | string
  slug?: Words | string
  parent_id?: number | null
  color?: string | null
  image_id?: number | null
}

export interface CatalogPropertiesApi {
  list(query?: PropertyQuery): Promise<PropertiesPage>
  get(id: number): Promise<PropertyDetail>
  /** The values of `catalog.property-form`; a refused field comes back as a 422 under its name. */
  create(values: ScreenModel): Promise<PropertyDetail>
  save(id: number, values: ScreenModel): Promise<PropertyDetail>
  reorder(ids: number[]): Promise<void>
  remove(id: number): Promise<void>
  restore(id: number): Promise<PropertyDetail>
  /** All the intervals at once, in order: one without an id is new, one left out is gone. */
  saveIntervals(id: number, intervals: PropertyInterval[]): Promise<PropertyInterval[]>

  /** The children of `parent` (null — the top) in the book's order. */
  values(id: number, parent?: number | null): Promise<PropertyValue[]>
  /** Whatever matches, at any depth — one page. */
  searchValues(id: number, q: string, perPage?: number): Promise<PropertyValue[]>
  /** These values, each with the path down to it. */
  valuesById(id: number, ids: number[]): Promise<PropertyValue[]>
  createValue(id: number, input: ValueInput): Promise<PropertyValue>
  saveValue(id: number, value: number, input: ValueInput): Promise<PropertyValue>
  /** Under `parent` (null — the top), before `before` (null — last). */
  moveValue(
    id: number,
    value: number,
    parent: number | null,
    before: number | null,
  ): Promise<PropertyValue>
  /** `A → B`: how many products moved, or would move with `dryRun`. */
  mergeValue(id: number, value: number, into: number, dryRun?: boolean): Promise<number>
  /** A 422 with `meta.products` while products hold it. */
  removeValue(id: number, value: number): Promise<void>

  categorySet(category: number): Promise<CategorySet>
  saveCategorySet(category: number, ids: number[]): Promise<CategorySet>
  effectiveSet(category: number): Promise<EffectiveSet>
}

export function propertySearch(query: PropertyQuery): URLSearchParams {
  const search = new URLSearchParams()

  if (query.q) search.set('q', query.q)
  if (query.type) search.set('type', query.type)
  if (query.group != null) search.set('group', String(query.group))
  if (query.trashed) search.set('trashed', '1')
  for (const id of query.ids ?? []) search.append('ids[]', String(id))
  if (query.page && query.page > 1) search.set('page', String(query.page))
  if (query.per_page) search.set('per_page', String(query.per_page))

  return search
}

export function createPropertiesApi(admin: AdminContext): CatalogPropertiesApi {
  const base = `${admin.apiPath}/${PROPERTIES_API}`
  const data = <T>(body: { data: T }): T => body.data
  const withQuery = (path: string, search: URLSearchParams) =>
    search.size > 0 ? `${path}?${search}` : path

  return {
    list: (query = {}) =>
      admin.http.get<PropertiesPage>(withQuery(base, propertySearch({ per_page: 500, ...query }))),
    get: (id) => admin.http.get<{ data: PropertyDetail }>(`${base}/${id}`).then(data),
    create: (values) => admin.http.post<{ data: PropertyDetail }>(base, { values }).then(data),
    save: (id, values) =>
      admin.http.put<{ data: PropertyDetail }>(`${base}/${id}`, { values }).then(data),
    reorder: (ids) => admin.http.post<void>(`${base}/reorder`, { ids }).then(() => undefined),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) =>
      admin.http.post<{ data: PropertyDetail }>(`${base}/${id}/restore`, {}).then(data),
    saveIntervals: (id, intervals) =>
      admin.http
        .put<{ data: PropertyInterval[] }>(`${base}/${id}/intervals`, {
          intervals: intervals.map(({ id: interval, title, slug, min, max }) => ({
            id: interval ?? null,
            title,
            slug,
            min,
            max,
          })),
        })
        .then(data),

    values: (id, parent = null) => {
      const search = new URLSearchParams()

      if (parent !== null) search.set('parent_id', String(parent))

      return admin.http
        .get<{ data: PropertyValue[] }>(withQuery(`${base}/${id}/values`, search))
        .then(data)
    },
    searchValues: (id, q, perPage = 50) =>
      admin.http
        .get<{
          data: PropertyValue[]
        }>(withQuery(`${base}/${id}/values`, new URLSearchParams({ q, per_page: String(perPage) })))
        .then(data),
    valuesById: (id, ids) => {
      if (ids.length === 0) return Promise.resolve([])

      const search = new URLSearchParams()

      for (const one of ids) search.append('ids[]', String(one))

      return admin.http
        .get<{ data: PropertyValue[] }>(withQuery(`${base}/${id}/values`, search))
        .then(data)
    },
    createValue: (id, input) =>
      admin.http.post<{ data: PropertyValue }>(`${base}/${id}/values`, input).then(data),
    saveValue: (id, value, input) =>
      admin.http.put<{ data: PropertyValue }>(`${base}/${id}/values/${value}`, input).then(data),
    moveValue: (id, value, parent, before) =>
      admin.http
        .post<{ data: PropertyValue }>(`${base}/${id}/values/${value}/move`, {
          parent_id: parent,
          before_id: before,
        })
        .then(data),
    mergeValue: (id, value, into, dryRun = false) =>
      admin.http
        .post<{ data: { moved: number } }>(`${base}/${id}/values/${value}/merge`, {
          into,
          dry_run: dryRun,
        })
        .then((body) => body.data.moved),
    removeValue: (id, value) =>
      admin.http.delete<void>(`${base}/${id}/values/${value}`).then(() => undefined),

    categorySet: (category) =>
      admin.http
        .get<{ data: CategorySet }>(`${admin.apiPath}/catalog/categories/${category}/properties`)
        .then(data),
    saveCategorySet: (category, ids) =>
      admin.http
        .put<{
          data: CategorySet
        }>(`${admin.apiPath}/catalog/categories/${category}/properties`, { ids })
        .then(data),
    effectiveSet: (category) =>
      admin.http.get<{ data: EffectiveSet }>(`${admin.apiPath}/${SETS_API}/${category}`).then(data),
  }
}
