import type { AdminContext } from '@webx-ui/module-admin'
import type {
  PageDetail,
  PageDropZone,
  PageInput,
  PageLevel,
  PageMoveResult,
  PageRestore,
  PageRow,
  PageSave,
  PageVersion,
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
  /**
   * Save the draft. Refused with a 409 when the revision is not the current one — the error's
   * body is a {@link PageConflict}, and the page it carries is the page as it now is.
   */
  save(id: number, input: PageSave): Promise<PageDetail>
  move(id: number, target: number, zone: PageDropZone): Promise<PageMoveResult>
  duplicate(id: number): Promise<PageRow>
  /** `revision` is what the editor held: a draft that moved on since is answered 409, not published. */
  publish(id: number, revision?: string): Promise<PageRow>
  unpublish(id: number): Promise<PageRow>
  /** Drops the draft and answers the page as the site shows it. */
  discard(id: number): Promise<PageDetail>
  /** Into the bin, with the branch under it. Answers how many went. */
  remove(id: number): Promise<number>
  /** Out of the bin, with whatever went in with it. Answers how many came back. */
  restore(id: number): Promise<PageRestore>
  /** Deletes a page in the bin for good, with its branch; answers how many pages went. */
  purge(id: number): Promise<number>
  /**
   * How many pages emptying the bin would delete: every page in it, those that went in with a
   * parent included — what the list shows is only the top of each branch, and maybe a search.
   */
  binCount(): Promise<number>
  /** Empties the bin; answers how many pages went. */
  purgeBin(): Promise<number>
  /** The publications, newest first. */
  versions(id: number): Promise<PageVersion[]>
  /** An old publication becomes the draft; putting it on the site is a separate step. */
  restoreVersion(id: number, number: number): Promise<PageDetail>
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
    save: (id, input) => admin.http.put<{ data: PageDetail }>(`${base}/${id}`, input).then(data),
    move: (id, target, zone) =>
      admin.http.post<{ data: PageMoveResult }>(`${base}/${id}/move`, { target, zone }).then(data),
    duplicate: (id) => admin.http.post<{ data: PageRow }>(`${base}/${id}/duplicate`, {}).then(data),
    publish: (id, revision) =>
      admin.http
        .post<{ data: PageRow }>(`${base}/${id}/publish`, revision ? { revision } : {})
        .then(data),
    unpublish: (id) => admin.http.post<{ data: PageRow }>(`${base}/${id}/unpublish`, {}).then(data),
    discard: (id) => admin.http.post<{ data: PageDetail }>(`${base}/${id}/discard`, {}).then(data),
    remove: (id) =>
      admin.http
        .delete<{ data: { trashed: number } }>(`${base}/${id}`)
        .then((body) => body.data.trashed),
    restore: (id) => admin.http.post<{ data: PageRestore }>(`${base}/${id}/restore`, {}).then(data),
    purge: (id) =>
      admin.http
        .delete<{ data: { purged: number } }>(`${base}/${id}/purge`)
        .then((body) => body.data.purged),
    binCount: () =>
      admin.http.get<{ data: { pages: number } }>(`${base}/bin`).then((body) => body.data.pages),
    purgeBin: () =>
      admin.http
        .delete<{ data: { purged: number } }>(`${base}/bin`)
        .then((body) => body.data.purged),
    versions: (id) => admin.http.get<{ data: PageVersion[] }>(`${base}/${id}/versions`).then(data),
    restoreVersion: (id, number) =>
      admin.http
        .post<{ data: PageDetail }>(`${base}/${id}/versions/${number}/restore`, {})
        .then(data),
  }
}
