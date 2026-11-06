import type { AdminContext } from '@webx-ui/module-admin'
import type {
  ExclusionsPage,
  FeedBuildDetail,
  FeedBuildRow,
  FeedBuildsPage,
  FeedCount,
  FeedDetail,
  FeedFacet,
  FeedFilter,
  FeedSample,
  FeedSaved,
  FeedsMeta,
  FeedsPage,
  FeedTrial,
  FeedValues,
  FeedVariables,
  MappedCategories,
  TaxonomyItem,
  TaxonomyState,
} from './types'

/** Where the feeds answer under the panel's API (§11 of the feeds spec). */
export const FEEDS_API = 'catalog/feeds'

/** What a form sends; the currency is the site's, the class a preset's, the key nobody's to write. */
export const FORM_FIELDS = [
  'name',
  'preset',
  'format',
  'locale',
  'head',
  'item',
  'foot',
  'separator',
  'filter',
  'stock_map',
  'taxonomy',
  'custom_labels',
  'sources',
  'options',
  'schedule',
  'schedule_at',
  'rebuild_on_change',
  'is_active',
] as const satisfies readonly Exclude<keyof FeedValues, 'driver'>[]

export function formValues(feed: FeedValues): FeedValues {
  const values = Object.fromEntries(FORM_FIELDS.map((field) => [field, feed[field]]))

  return { ...(values as unknown as FeedValues), currency: feed.currency, driver: feed.driver }
}

/** The form without what it only shows. */
export function payloadOf(values: FeedValues): Record<string, unknown> {
  return Object.fromEntries(FORM_FIELDS.map((field) => [field, values[field]]))
}

export interface CatalogFeedsApi {
  list(page?: number, perPage?: number): Promise<FeedsPage>
  get(id: number): Promise<FeedDetail>
  create(values: Record<string, unknown>): Promise<FeedSaved>
  save(id: number, values: Record<string, unknown>): Promise<FeedSaved>
  remove(id: number): Promise<void>
  meta(): Promise<FeedsMeta>
  variables(): Promise<FeedVariables>
  facets(locale: string): Promise<FeedFacet[]>
  count(filter: FeedFilter, locale: string, feed: number | null): Promise<FeedCount>
  sample(values: Record<string, unknown>, feed: number | null, ids?: number[]): Promise<FeedSample>
  preview(values: Record<string, unknown>, feed: number | null, ids?: number[]): Promise<FeedTrial>
  rebuild(id: number): Promise<FeedBuildRow>
  reissueKey(id: number): Promise<FeedDetail>
  builds(id: number, page?: number): Promise<FeedBuildsPage>
  build(id: number, build: number): Promise<FeedBuildDetail>
  exclusions(id: number, q?: string, page?: number): Promise<ExclusionsPage>
  exclude(id: number, products: number[]): Promise<void>
  include(id: number, product: number): Promise<void>
  categories(id: number): Promise<MappedCategories>
  mapCategories(
    id: number,
    rows: { category_id: number; taxonomy_item_id?: number | null; value?: string | null }[],
  ): Promise<MappedCategories>
  taxonomySearch(
    key: string,
    locale: string,
    q: string,
  ): Promise<{ data: TaxonomyItem[]; taxonomy: TaxonomyState }>
  taxonomyImport(
    key: string,
    source: { locale: string; url?: string; upload?: string },
  ): Promise<{ taxonomy: TaxonomyState }>
}

export function createFeedsApi(admin: AdminContext): CatalogFeedsApi {
  const base = `${admin.apiPath}/${FEEDS_API}`
  const data = <T>(body: { data: T }): T => body.data
  const query = (values: Record<string, string | number | undefined>) => {
    const search = new URLSearchParams()

    for (const [key, value] of Object.entries(values)) {
      if (value !== undefined && value !== '') search.set(key, String(value))
    }

    return search.size > 0 ? `?${search}` : ''
  }

  return {
    list: (page, perPage) =>
      admin.http.get<FeedsPage>(`${base}${query({ page, per_page: perPage })}`),
    get: (id) => admin.http.get<{ data: FeedDetail }>(`${base}/${id}`).then(data),
    create: (values) => admin.http.post<FeedSaved>(base, values),
    save: (id, values) => admin.http.put<FeedSaved>(`${base}/${id}`, values),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`),
    meta: () => admin.http.get<{ data: FeedsMeta }>(`${base}/meta`).then(data),
    variables: () => admin.http.get<{ data: FeedVariables }>(`${base}/variables`).then(data),
    facets: (locale) =>
      admin.http.get<{ data: FeedFacet[] }>(`${base}/facets${query({ locale })}`).then(data),
    count: (filter, locale, feed) =>
      admin.http.post<{ data: FeedCount }>(`${base}/count`, { filter, locale, feed }).then(data),
    sample: (values, feed, ids = []) =>
      admin.http
        .post<{ data: FeedSample }>(`${base}/sample`, { ...values, feed, product_ids: ids })
        .then(data),
    preview: (values, feed, ids = []) =>
      admin.http
        .post<{ data: FeedTrial }>(`${base}/preview`, { ...values, feed, product_ids: ids })
        .then(data),
    rebuild: (id) =>
      admin.http.post<{ data: FeedBuildRow }>(`${base}/${id}/rebuild`, {}).then(data),
    reissueKey: (id) =>
      admin.http.post<{ data: FeedDetail }>(`${base}/${id}/reissue-key`, {}).then(data),
    builds: (id, page) => admin.http.get<FeedBuildsPage>(`${base}/${id}/builds${query({ page })}`),
    build: (id, build) =>
      admin.http.get<{ data: FeedBuildDetail }>(`${base}/${id}/builds/${build}`).then(data),
    exclusions: (id, q, page) =>
      admin.http.get<ExclusionsPage>(`${base}/${id}/exclusions${query({ q, page })}`),
    exclude: (id, products) =>
      admin.http.post<void>(`${base}/${id}/exclusions`, { product_ids: products }),
    include: (id, product) => admin.http.delete<void>(`${base}/${id}/exclusions/${product}`),
    categories: (id) => admin.http.get<MappedCategories>(`${base}/${id}/categories`),
    mapCategories: (id, rows) =>
      admin.http.put<MappedCategories>(`${base}/${id}/categories`, { categories: rows }),
    taxonomySearch: (key, locale, q) =>
      admin.http.get(`${base}/taxonomies/${key}/search${query({ locale, q })}`),
    taxonomyImport: (key, source) => admin.http.post(`${base}/taxonomies/${key}/import`, source),
  }
}
