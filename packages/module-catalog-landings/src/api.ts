import type { AdminContext } from '@webx-ui/module-admin'
import type {
  BaseFacet,
  GenerateParams,
  GeneratePreview,
  GenerateRun,
  LandingDetail,
  LandingFilters,
  LandingQuery,
  LandingsPage,
  SetCount,
} from './types'

/** Where the landings answer under the panel's API (§8.4 of the landings spec). */
export const LANDINGS_API = 'catalog/landings'

/** The list's question as a query string; a filter left unset is not sent at all. */
export function landingSearch(query: LandingQuery): URLSearchParams {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') continue

    search.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }

  return search
}

export interface CatalogLandingsApi {
  list(query?: LandingQuery): Promise<LandingsPage>
  get(id: number): Promise<LandingDetail>
  create(values: Record<string, unknown>): Promise<LandingDetail>
  save(id: number, values: Record<string, unknown>): Promise<LandingDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<LandingDetail>
  publish(id: number, on: boolean): Promise<LandingDetail>
  count(
    categoryId: number | null,
    filters: LandingFilters,
    except?: number | null,
  ): Promise<SetCount>
  facets(categoryId: number | null): Promise<BaseFacet[]>
  preview(params: GenerateParams): Promise<GeneratePreview>
  generate(params: GenerateParams): Promise<GenerateRun>
  run(id: number): Promise<GenerateRun>
}

export function createLandingsApi(admin: AdminContext): CatalogLandingsApi {
  const base = `${admin.apiPath}/${LANDINGS_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = landingSearch(query)

      return admin.http.get<LandingsPage>(search.size > 0 ? `${base}?${search}` : base)
    },
    get: (id) => admin.http.get<{ data: LandingDetail }>(`${base}/${id}`).then(data),
    create: (values) => admin.http.post<{ data: LandingDetail }>(base, values).then(data),
    save: (id, values) =>
      admin.http.put<{ data: LandingDetail }>(`${base}/${id}`, values).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) =>
      admin.http.post<{ data: LandingDetail }>(`${base}/${id}/restore`, {}).then(data),
    publish: (id, on) =>
      admin.http
        .post<{ data: LandingDetail }>(`${base}/${id}/${on ? 'publish' : 'unpublish'}`, {})
        .then(data),
    count: (categoryId, filters, except = null) =>
      admin.http
        .post<{ data: SetCount }>(`${base}/count`, { category_id: categoryId, filters, except })
        .then(data),
    facets: (categoryId) =>
      admin.http
        .get<{
          data: BaseFacet[]
        }>(categoryId === null ? `${base}/facets` : `${base}/facets?category=${categoryId}`)
        .then(data),
    preview: (params) =>
      admin.http
        .post<{ data: GeneratePreview }>(`${base}/generate`, { ...params, dry_run: true })
        .then(data),
    generate: (params) =>
      admin.http.post<{ data: GenerateRun }>(`${base}/generate`, params).then(data),
    run: (id) => admin.http.get<{ data: GenerateRun }>(`${base}/generate/${id}`).then(data),
  }
}

/** The fields of the form a save sends; what the list shows beside them is not the form's. */
export const FORM_FIELDS = [
  'category_id',
  'filters',
  'name',
  'slug',
  'h1',
  'text_above',
  'text_below',
  'sort',
  'on_category',
  'position',
  'recommended',
  'seo',
] as const

/** A landing's values as the form holds them. */
export function formValues(detail: LandingDetail): Record<string, unknown> {
  const values: Record<string, unknown> = {}

  for (const field of FORM_FIELDS) values[field] = detail[field] ?? null

  // The table keeps an empty set as `[]` when PHP encodes an empty array.
  if (Array.isArray(values.filters)) values.filters = {}

  return values
}

/**
 * A set without the facets that say nothing — a list with no value, a range with no end — so
 * that a half-built facet neither counts nor fails a save.
 */
export function cleanSet(filters: LandingFilters | null | undefined): LandingFilters {
  const clean: LandingFilters = {}

  for (const [key, choice] of Object.entries(filters ?? {})) {
    if ('values' in choice) {
      if (choice.values.length > 0) clean[key] = { values: [...choice.values] }
    } else if (choice.min !== null || choice.max !== null) {
      clean[key] = { min: choice.min, max: choice.max }
    }
  }

  return clean
}
