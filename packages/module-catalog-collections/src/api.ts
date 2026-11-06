import type { AdminContext } from '@webx-ui/module-admin'
import { cleanSet } from '@webx-ui/module-catalog'
import type { ScreenModel } from '@webx-ui/schema'
import type {
  CollectionDetail,
  CollectionFacets,
  CollectionPreset,
  CollectionPreview,
  CollectionQuery,
  CollectionsPage,
  FacetSetValue,
} from './types'

/** Where the collections answer under the panel's API (§6.5 of the collections spec). */
export const COLLECTIONS_API = 'catalog/collections'

/** The list's question as a query string; a filter left unset is not sent at all. */
export function collectionSearch(query: CollectionQuery): URLSearchParams {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') continue

    search.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }

  return search
}

export interface CatalogCollectionsApi {
  list(query?: CollectionQuery): Promise<CollectionsPage>
  get(id: number): Promise<CollectionDetail>
  create(values: Record<string, unknown>): Promise<CollectionDetail>
  save(id: number, values: Record<string, unknown>): Promise<CollectionDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<CollectionDetail>
  facets(categoryId: number | null): Promise<CollectionFacets>
  preview(values: Record<string, unknown>): Promise<CollectionPreview>
}

export function createCollectionsApi(admin: AdminContext): CatalogCollectionsApi {
  const base = `${admin.apiPath}/${COLLECTIONS_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = collectionSearch(query)

      return admin.http.get<CollectionsPage>(search.size > 0 ? `${base}?${search}` : base)
    },
    get: (id) => admin.http.get<{ data: CollectionDetail }>(`${base}/${id}`).then(data),
    create: (values) => admin.http.post<{ data: CollectionDetail }>(base, values).then(data),
    save: (id, values) =>
      admin.http.put<{ data: CollectionDetail }>(`${base}/${id}`, values).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) =>
      admin.http.post<{ data: CollectionDetail }>(`${base}/${id}/restore`, {}).then(data),
    facets: (categoryId) =>
      admin.http.get<CollectionFacets>(
        categoryId === null ? `${base}/facets` : `${base}/facets?category=${categoryId}`,
      ),
    preview: (values) =>
      admin.http.post<{ data: CollectionPreview }>(`${base}/preview`, values).then(data),
  }
}

/** The fields of the form a save sends; what the list shows beside them is not the form's. */
export const FORM_FIELDS = [
  'key',
  'title',
  'source',
  'category_id',
  'filters',
  'on_sale',
  'sort',
  'only_purchasable',
  'limit',
  'view',
  'link',
  'link_text',
  'is_enabled',
  'products',
] as const

/** What a collection nobody saved yet starts from: the model's own defaults. */
export function blankValues(): ScreenModel {
  return {
    key: null,
    title: {},
    source: 'manual',
    category_id: null,
    filters: {},
    on_sale: false,
    sort: null,
    only_purchasable: true,
    limit: 8,
    view: 'grid',
    link: null,
    link_text: {},
    is_enabled: true,
    products: [],
  }
}

/** A collection's values as the form holds them. */
export function formValues(detail: CollectionDetail): ScreenModel {
  const values: ScreenModel = {}

  for (const field of FORM_FIELDS) values[field] = detail[field] ?? null

  // PHP encodes an empty map as `[]`.
  for (const field of ['filters', 'title', 'link_text'] as const) {
    if (Array.isArray(values[field]) || values[field] === null) values[field] = {}
  }

  return values
}

/** What a save sends: the form's fields, the set without its half-built facets. */
export function payload(values: ScreenModel): Record<string, unknown> {
  return { ...values, filters: cleanSet(values.filters as FacetSetValue) }
}

/** What the preview is asked: only what decides the products, so typing a title asks nothing. */
export function previewQuestion(values: ScreenModel, locale: string): Record<string, unknown> {
  return {
    source: values.source,
    category_id: values.category_id ?? null,
    filters: cleanSet((values.filters ?? {}) as FacetSetValue),
    on_sale: values.on_sale ?? false,
    sort: values.sort ?? null,
    only_purchasable: values.only_purchasable ?? true,
    limit: values.limit,
    products: values.products ?? [],
    locale,
  }
}

/** The fields a preset writes; a preset of a facet keeps what was already picked of it. */
export function presetFields(preset: CollectionPreset, values: ScreenModel): ScreenModel {
  const set = (values.filters ?? {}) as FacetSetValue

  return {
    sort: preset.sort,
    on_sale: preset.on_sale,
    filters: preset.facet === null ? {} : { [preset.facet]: set[preset.facet] ?? { values: [] } },
  }
}

/** The preset the fields still answer to, by the rule of `CollectionPresets::of()`. */
export function presetOf(values: ScreenModel, presets: CollectionPreset[]): string | null {
  if (values.source !== 'filter') return null

  const sort = values.sort === 'default' ? null : ((values.sort as string | null) ?? null)
  const facets = Object.keys((values.filters ?? {}) as FacetSetValue)
  const onSale = Boolean(values.on_sale)

  const found = presets.find(
    (preset) =>
      preset.sort === sort &&
      preset.on_sale === onSale &&
      facets.join() === (preset.facet === null ? '' : preset.facet),
  )

  return found?.key ?? null
}

/** What a developer puts in a template; the list and the form copy it whole. */
export function tagOf(key: string): string {
  return `<x-webx-catalog-collections::collection key="${key}" />`
}
