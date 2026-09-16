import type { AdminContext } from '@webx-ui/module-admin'
import type {
  PageDetail,
  PageDropZone,
  PageInput,
  PageLevel,
  PageMoveResult,
  PageRow,
} from './types'

export interface PagesApi {
  /** One level of the tree, or a flat list when searching or looking in the bin. */
  list(query?: {
    parent?: number | null
    search?: string
    status?: string
    trashed?: boolean
    /** The whole tree in one flat list, in the order it reads top to bottom. */
    flat?: boolean
  }): Promise<PageLevel>
  get(id: number): Promise<PageDetail>
  create(input: PageInput): Promise<PageRow>
  update(id: number, input: PageInput): Promise<PageRow>
  move(id: number, target: number, zone: PageDropZone): Promise<PageMoveResult>
  duplicate(id: number): Promise<PageRow>
  publish(id: number): Promise<PageRow>
  unpublish(id: number): Promise<PageRow>
  /** Into the bin, with the branch under it. Answers how many went. */
  remove(id: number): Promise<number>
  /** Out of the bin, with whatever went in with it. Answers how many came back. */
  restore(id: number): Promise<number>
}

/** Everything under `/pages`, below the panel's API path. */
export function createPagesApi(admin: AdminContext): PagesApi {
  const base = `${admin.apiPath}/pages`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      // Only what was asked for: `parent` left out means the home page's children, and
      // `parent=` would ask for the children of nothing.
      if (query.parent != null) search.set('parent', String(query.parent))
      if (query.search) search.set('search', query.search)
      if (query.status) search.set('status', query.status)
      if (query.trashed) search.set('trashed', '1')
      if (query.flat) search.set('flat', '1')

      const suffix = search.size > 0 ? `?${search}` : ''

      return admin.http.get<{ data: PageLevel }>(`${base}${suffix}`).then(data)
    },
    get: (id) => admin.http.get<{ data: PageDetail }>(`${base}/${id}`).then(data),
    create: (input) => admin.http.post<{ data: PageRow }>(base, input).then(data),
    update: (id, input) => admin.http.put<{ data: PageRow }>(`${base}/${id}`, input).then(data),
    move: (id, target, zone) =>
      admin.http.post<{ data: PageMoveResult }>(`${base}/${id}/move`, { target, zone }).then(data),
    duplicate: (id) => admin.http.post<{ data: PageRow }>(`${base}/${id}/duplicate`, {}).then(data),
    publish: (id) => admin.http.post<{ data: PageRow }>(`${base}/${id}/publish`, {}).then(data),
    unpublish: (id) => admin.http.post<{ data: PageRow }>(`${base}/${id}/unpublish`, {}).then(data),
    remove: (id) =>
      admin.http
        .delete<{ data: { trashed: number } }>(`${base}/${id}`)
        .then((body) => body.data.trashed),
    restore: (id) =>
      admin.http
        .post<{ data: { restored: number } }>(`${base}/${id}/restore`, {})
        .then((body) => body.data.restored),
  }
}
