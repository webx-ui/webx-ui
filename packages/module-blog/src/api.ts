import type { AdminContext } from '@webx-ui/module-admin'
import type {
  ArticleDetail,
  ArticleInput,
  ArticleQuery,
  ArticleRow,
  ArticleSave,
  ArticleVersion,
  ArticlesPage,
  BlogTag,
  TagInput,
  TagMassAction,
  TagMerged,
  TagQuery,
  TagRow,
  TagsPage,
} from './types'

export interface BlogApi {
  /** A page of articles, with what the filters over them can be set to. */
  articles(query?: ArticleQuery): Promise<ArticlesPage>
  /** One article as its editor opens it: the record, the screen's values and the revision. */
  get(id: number): Promise<ArticleDetail>
  create(input: ArticleInput): Promise<ArticleRow>
  /**
   * Save. Refused with a 409 when the revision is not the current one — the body is an
   * {@link ArticleConflict} carrying the article as it now is, so the panel can offer to
   * re-read rather than quietly keep one of two edits.
   */
  save(id: number, input: ArticleSave): Promise<ArticleDetail>
  /** Throw away what is waiting and keep what the site is showing. */
  discard(id: number): Promise<ArticleDetail>
  /** The publications, newest first. */
  versions(id: number): Promise<ArticleVersion[]>
  /** An old publication becomes the draft; putting it on the site is a separate step. */
  restoreVersion(id: number, number: number): Promise<ArticleDetail>
  /** Tags matching what is being typed, most used first. */
  tags(term?: string): Promise<BlogTag[]>
  /**
   * A tag made from the article being written, because that is where tags come from (§2.8) —
   * and from the tags screen, which is the other place somebody spells a new word.
   */
  createTag(input: TagInput): Promise<TagRow>
  /** On the site. `at` in the future schedules it; left out, it goes on now (§7). */
  publish(id: number, at?: string | null): Promise<ArticleRow>
  unpublish(id: number): Promise<ArticleRow>
  remove(id: number): Promise<void>
  restore(id: number): Promise<ArticleRow>

  /** A page of tags with the counts the pills wear (§10). */
  tagsPage(query?: TagQuery): Promise<TagsPage>
  /** A rename, or the switch on the index. Only what travels is touched. */
  saveTag(id: number, input: TagInput): Promise<TagRow>
  removeTag(id: number): Promise<void>
  /** What the selection bar does to a pile of them at once. */
  massTags(ids: number[], action: TagMassAction): Promise<number>
  /** Several into one. Irreversible, and `redirect` is what outlives the tags that go (§6). */
  mergeTags(ids: number[], keep: number, redirect: boolean): Promise<TagMerged>
}

/**
 * Everything under `/blog`, below the panel's API path — except the rubrics, which are the
 * panel's shared categories and are asked for with `createCategoriesApi(admin, 'blog/rubrics')`.
 */
export function createBlogApi(admin: AdminContext): BlogApi {
  const base = `${admin.apiPath}/blog/articles`
  const tagsBase = `${admin.apiPath}/blog/tags`
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
    get: (id) => admin.http.get<{ data: ArticleDetail }>(`${base}/${id}`).then(data),
    create: (input) => admin.http.post<{ data: ArticleRow }>(base, input).then(data),
    save: (id, input) => admin.http.put<{ data: ArticleDetail }>(`${base}/${id}`, input).then(data),
    discard: (id) =>
      admin.http.post<{ data: ArticleDetail }>(`${base}/${id}/discard`, {}).then(data),
    versions: (id) =>
      admin.http.get<{ data: ArticleVersion[] }>(`${base}/${id}/versions`).then(data),
    restoreVersion: (id, number) =>
      admin.http
        .post<{ data: ArticleDetail }>(`${base}/${id}/versions/${number}/restore`, {})
        .then(data),
    tags: (term = '') => {
      const suffix = term.trim() === '' ? '' : `?q=${encodeURIComponent(term.trim())}`

      return admin.http.get<{ data: BlogTag[] }>(`${tagsBase}${suffix}`).then(data)
    },
    createTag: (input) => admin.http.post<{ data: TagRow }>(tagsBase, input).then(data),
    publish: (id, at) =>
      admin.http
        .post<{ data: ArticleRow }>(`${base}/${id}/publish`, at == null ? {} : { at })
        .then(data),
    unpublish: (id) =>
      admin.http.post<{ data: ArticleRow }>(`${base}/${id}/unpublish`, {}).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) => admin.http.post<{ data: ArticleRow }>(`${base}/${id}/restore`, {}).then(data),

    tagsPage: (query = {}) => {
      const search = new URLSearchParams()

      if (query.q) search.set('q', query.q)
      // Only when they are on: `empty=0` would be a question the screen never means to put.
      if (query.empty) search.set('empty', '1')
      if (query.noindex) search.set('noindex', '1')
      if (query.sort) search.set('sort', query.sort)
      if (query.page && query.page > 1) search.set('page', String(query.page))
      if (query.per_page) search.set('per_page', String(query.per_page))

      const suffix = search.size > 0 ? `?${search}` : ''

      return admin.http
        .get<{
          data: TagRow[]
          meta: Omit<TagsPage, 'data' | 'filters'>
          filters: TagsPage['filters']
        }>(`${tagsBase}${suffix}`)
        .then((body) => ({ ...body.meta, data: body.data, filters: body.filters }))
    },
    saveTag: (id, input) => admin.http.put<{ data: TagRow }>(`${tagsBase}/${id}`, input).then(data),
    removeTag: (id) => admin.http.delete<void>(`${tagsBase}/${id}`).then(() => undefined),
    massTags: (ids, action) =>
      admin.http
        .post<{ data: { affected: number } }>(`${tagsBase}/mass`, { ids, action })
        .then((body) => body.data.affected),
    mergeTags: (ids, keep, redirect) =>
      admin.http.post<{ data: TagMerged }>(`${tagsBase}/merge`, { ids, keep, redirect }).then(data),
  }
}
