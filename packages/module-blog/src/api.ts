import type { AdminContext } from '@webx-ui/module-admin'
import type { ArticleInput, ArticleQuery, ArticleRow, ArticlesPage } from './types'

export interface BlogApi {
  /** A page of articles, with what the filters over them can be set to. */
  articles(query?: ArticleQuery): Promise<ArticlesPage>
  article(id: number): Promise<ArticleRow>
  create(input: ArticleInput): Promise<ArticleRow>
  /**
   * Save. Refused with a 409 when the revision is not the current one — the body carries the
   * article as it now is, so the panel can offer to re-read rather than keep one of two edits.
   */
  save(id: number, input: ArticleInput): Promise<ArticleRow>
  /** On the site. `at` in the future schedules it; left out, it goes on now (§7). */
  publish(id: number, at?: string | null): Promise<ArticleRow>
  unpublish(id: number): Promise<ArticleRow>
  remove(id: number): Promise<void>
  restore(id: number): Promise<ArticleRow>
}

/** Everything under `/blog`, below the panel's API path. */
export function createBlogApi(admin: AdminContext): BlogApi {
  const base = `${admin.apiPath}/blog/articles`
  const data = <T>(body: { data: T }): T => body.data

  return {
    articles: (query = {}) => {
      const search = new URLSearchParams()

      // Only what was asked for: `rubric=` would ask for the articles of no rubric, which is a
      // question the list never means to put.
      if (query.q) search.set('q', query.q)
      if (query.rubric != null) search.set('rubric', String(query.rubric))
      if (query.tag != null) search.set('tag', String(query.tag))
      if (query.author != null) search.set('author', String(query.author))
      if (query.status) search.set('status', query.status)
      if (query.sort) search.set('sort', query.sort)
      if (query.trashed) search.set('trashed', '1')
      if (query.page && query.page > 1) search.set('page', String(query.page))
      if (query.per_page) search.set('per_page', String(query.per_page))

      const suffix = search.size > 0 ? `?${search}` : ''

      return admin.http
        .get<{
          data: ArticleRow[]
          meta: Omit<ArticlesPage, 'data' | 'filters'>
          filters: ArticlesPage['filters']
        }>(`${base}${suffix}`)
        .then((body) => ({ ...body.meta, data: body.data, filters: body.filters }))
    },
    article: (id) => admin.http.get<{ data: ArticleRow }>(`${base}/${id}`).then(data),
    create: (input) => admin.http.post<{ data: ArticleRow }>(base, input).then(data),
    save: (id, input) => admin.http.put<{ data: ArticleRow }>(`${base}/${id}`, input).then(data),
    publish: (id, at) =>
      admin.http
        .post<{ data: ArticleRow }>(`${base}/${id}/publish`, at == null ? {} : { at })
        .then(data),
    unpublish: (id) =>
      admin.http.post<{ data: ArticleRow }>(`${base}/${id}/unpublish`, {}).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) => admin.http.post<{ data: ArticleRow }>(`${base}/${id}/restore`, {}).then(data),
  }
}
