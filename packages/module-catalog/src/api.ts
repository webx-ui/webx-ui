import { readCookie, type AdminContext } from '@webx-ui/module-admin'
import type { Paginated } from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import type {
  BulkActionInfo,
  BulkRun,
  BulkSelection,
  CategoryDetail,
  CategoryNode,
  CategoryRow,
  DeletedCategory,
  DeletedKind,
  DeletedProduct,
  FacetInfo,
  FacetRegistryAnswer,
  SortInfo,
  ProductDetail,
  ProductImage,
  ProductQuery,
  QueuedVideo,
  ProductRow,
  ProductsPage,
} from './types'

export interface CatalogApi {
  products(query?: ProductQuery): Promise<ProductsPage>
  product(id: number): Promise<ProductDetail>
  /** The values of `catalog.product-form`; a refused field comes back as a 422 under its name. */
  createProduct(values: ScreenModel): Promise<ProductDetail>
  saveProduct(id: number, values: ScreenModel): Promise<ProductDetail>
  removeProduct(id: number): Promise<void>
  restoreProduct(id: number): Promise<ProductRow>

  /** A file off the disk, with how far it has got; or an address the server fetches itself. */
  addImage(
    id: number,
    source: File | string,
    onProgress?: (percent: number) => void,
  ): Promise<ProductImage>
  /** The whole gallery in its order, with every picture's words (§11.2). */
  saveImages(
    id: number,
    images: Pick<ProductImage, 'id' | 'alt' | 'title'>[],
  ): Promise<ProductImage[]>
  removeImage(id: number, image: number): Promise<void>
  /**
   * An address the server fetches: a picture or a YouTube video comes back as the new row, a
   * direct link to a video file as `QueuedVideo` — the row appears once the queue has it.
   */
  fetchImage(id: number, url: string): Promise<ProductImage | QueuedVideo>
  /** A finished chunked upload (`upload` is its id) or an address, onto a picture of the gallery. */
  attachVideo(
    id: number,
    image: number,
    source: { upload: string; duration?: number | null } | { url: string },
  ): Promise<ProductImage | QueuedVideo>
  /** The file, if it is one, is deleted at once. */
  detachVideo(id: number, image: number): Promise<ProductImage>

  categories(): Promise<CategoryNode[]>
  category(id: number): Promise<CategoryDetail>
  createCategory(values: ScreenModel, parent: number | null): Promise<CategoryDetail>
  saveCategory(id: number, values: ScreenModel): Promise<CategoryDetail>
  /** Under `parent` (null — the top), before `before` (null — last). Answers with the whole tree. */
  moveCategory(id: number, parent: number | null, before: number | null): Promise<CategoryNode[]>
  removeCategory(id: number): Promise<void>
  restoreCategory(id: number): Promise<CategoryRow>

  /** The registry of facets, with the sorts the list is offered. */
  facets(): Promise<FacetRegistryAnswer>

  /** The bulk actions this administrator may start, with what each asks for (§11.4). */
  bulkActions(): Promise<BulkActionInfo[]>
  /** Done at once when small (`id` null in the answer), queued otherwise. */
  startBulk(
    action: string,
    params: Record<string, unknown>,
    selection: BulkSelection,
  ): Promise<BulkRun>
  bulkRun(id: number): Promise<BulkRun>

  deleted(
    kind: 'products',
    query?: { q?: string; page?: number; per_page?: number },
  ): Promise<Paginated<DeletedProduct>>
  deleted(
    kind: 'categories',
    query?: { q?: string; page?: number; per_page?: number },
  ): Promise<Paginated<DeletedCategory>>
  deleted(
    kind: DeletedKind,
    query?: { q?: string; page?: number; per_page?: number },
  ): Promise<Paginated<DeletedProduct | DeletedCategory>>
}

/** The path of the catalogue under the panel's API. */
export const CATALOG_API = 'catalog'

/**
 * The list's query as Laravel reads nested parameters: `facets[brand][]=3`,
 * `facets[price][min]=100`. Every facet's choice is a list, a toggle's too — on is
 * `facets[in-stock][]=1`, the value the storefront's own links send; a bare `facets[in-stock]=1`
 * is refused. Empty choices are left out, so a filter that was opened and closed again asks for
 * nothing.
 */
export function productSearch(query: ProductQuery): URLSearchParams {
  const search = new URLSearchParams()

  if (query.q) search.set('q', query.q)
  if (query.q && query.typed) search.set('typed', '1')
  if (query.state) search.set('state', query.state)
  if (query.sort && query.sort !== 'default') search.set('sort', query.sort)
  if (query.page && query.page > 1) search.set('page', String(query.page))
  if (query.per_page) search.set('per_page', String(query.per_page))

  for (const [key, choice] of Object.entries(query.facets ?? {})) {
    if (choice === true) {
      search.set(`facets[${key}][]`, '1')
    } else if (Array.isArray(choice)) {
      for (const value of choice) search.append(`facets[${key}][]`, String(value))
    } else {
      if (choice.min != null) search.set(`facets[${key}][min]`, String(choice.min))
      if (choice.max != null) search.set(`facets[${key}][max]`, String(choice.max))
    }
  }

  return search
}

/**
 * The upload is the one request that is not JSON, and the one worth a progress bar: a phone
 * sending a twelve-megapixel photo over a shop's wifi takes long enough for "is it doing
 * anything?". XHR for that, as `module-media` does it.
 */
function upload<T>(admin: AdminContext, url: string, file: File, onProgress?: (p: number) => void) {
  const body = new FormData()
  body.append('file', file)

  return new Promise<T>((resolve, reject) => {
    const request = new XMLHttpRequest()

    request.open('POST', url)
    request.responseType = 'json'
    request.withCredentials = true
    request.setRequestHeader('Accept', 'application/json')
    request.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
    // The panel's own client sends the language on every request; this one is not that client,
    // and without it a refusal comes back in English under a Russian panel.
    request.setRequestHeader('X-Webx-Locale', admin.i18n.state.locale)

    const token = readCookie('XSRF-TOKEN')
    if (token) request.setRequestHeader('X-XSRF-TOKEN', token)

    request.upload.addEventListener('progress', (event) => {
      if (event.lengthComputable) onProgress?.(Math.round((event.loaded / event.total) * 100))
    })

    request.addEventListener('load', () => {
      const answer = request.response as { data?: T; message?: string } | null

      if (request.status >= 200 && request.status < 300 && answer?.data) {
        resolve(answer.data)

        return
      }

      reject(
        Object.assign(new Error(answer?.message ?? 'Upload failed'), {
          status: request.status,
          body: answer,
        }),
      )
    })

    request.addEventListener('error', () => reject(new Error('Upload failed')))
    request.send(body)
  })
}

export function createCatalogApi(admin: AdminContext): CatalogApi {
  const base = `${admin.apiPath}/${CATALOG_API}`
  const data = <T>(body: { data: T }): T => body.data
  const withQuery = (path: string, search: URLSearchParams) =>
    search.size > 0 ? `${path}?${search}` : path

  return {
    products: (query = {}) =>
      admin.http.get<ProductsPage>(withQuery(`${base}/products`, productSearch(query))),
    product: (id) => admin.http.get<{ data: ProductDetail }>(`${base}/products/${id}`).then(data),
    createProduct: (values) =>
      admin.http.post<{ data: ProductDetail }>(`${base}/products`, { values }).then(data),
    saveProduct: (id, values) =>
      admin.http.put<{ data: ProductDetail }>(`${base}/products/${id}`, { values }).then(data),
    removeProduct: (id) => admin.http.delete<void>(`${base}/products/${id}`).then(() => undefined),
    restoreProduct: (id) =>
      admin.http.post<{ data: ProductRow }>(`${base}/products/${id}/restore`, {}).then(data),

    addImage: (id, source, onProgress) =>
      typeof source === 'string'
        ? admin.http
            .post<{ data: ProductImage }>(`${base}/products/${id}/images`, { url: source })
            .then(data)
        : upload<ProductImage>(admin, `${base}/products/${id}/images`, source, onProgress),
    saveImages: (id, images) =>
      admin.http
        .put<{ data: ProductImage[] }>(`${base}/products/${id}/images`, {
          images: images.map(({ id: image, alt, title }) => ({ id: image, alt, title })),
        })
        .then(data),
    removeImage: (id, image) =>
      admin.http.delete<void>(`${base}/products/${id}/images/${image}`).then(() => undefined),
    fetchImage: (id, url) =>
      admin.http
        .post<{ data: ProductImage | QueuedVideo }>(`${base}/products/${id}/images`, { url })
        .then(data),
    attachVideo: (id, image, source) =>
      admin.http
        .post<{
          data: ProductImage | QueuedVideo
        }>(`${base}/products/${id}/images/${image}/video`, source)
        .then(data),
    detachVideo: (id, image) =>
      admin.http
        .delete<{ data: ProductImage }>(`${base}/products/${id}/images/${image}/video`)
        .then(data),

    categories: () => admin.http.get<{ data: CategoryNode[] }>(`${base}/categories`).then(data),
    category: (id) =>
      admin.http.get<{ data: CategoryDetail }>(`${base}/categories/${id}`).then(data),
    createCategory: (values, parent) =>
      admin.http
        .post<{ data: CategoryDetail }>(`${base}/categories`, { values, parent_id: parent })
        .then(data),
    saveCategory: (id, values) =>
      admin.http.put<{ data: CategoryDetail }>(`${base}/categories/${id}`, { values }).then(data),
    moveCategory: (id, parent, before) =>
      admin.http
        .post<{ data: CategoryNode[] }>(`${base}/categories/${id}/move`, {
          parent_id: parent,
          before_id: before,
        })
        .then(data),
    removeCategory: (id) =>
      admin.http.delete<void>(`${base}/categories/${id}`).then(() => undefined),
    restoreCategory: (id) =>
      admin.http.post<{ data: CategoryRow }>(`${base}/categories/${id}/restore`, {}).then(data),

    facets: () =>
      admin.http
        .get<{ data: FacetInfo[]; meta?: { sorts?: SortInfo[] } }>(`${base}/facets`)
        .then((body) => ({ facets: body.data, sorts: body.meta?.sorts ?? [] })),

    bulkActions: () => admin.http.get<{ data: BulkActionInfo[] }>(`${base}/bulk`).then(data),
    startBulk: (action, params, selection) =>
      admin.http.post<{ data: BulkRun }>(`${base}/bulk`, { action, params, selection }).then(data),
    bulkRun: (id) => admin.http.get<{ data: BulkRun }>(`${base}/bulk/${id}`).then(data),

    deleted: ((kind: DeletedKind, query: { q?: string; page?: number; per_page?: number } = {}) => {
      const search = new URLSearchParams({ type: kind })

      if (query.q) search.set('q', query.q)
      if (query.page && query.page > 1) search.set('page', String(query.page))
      if (query.per_page) search.set('per_page', String(query.per_page))

      return admin.http.get(`${base}/deleted?${search}`)
    }) as CatalogApi['deleted'],
  }
}
