import type { AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'

/** A profile as the list and the head of its form show it (§19.5 of the blog spec). */
export interface AuthorRow {
  id: number
  /** The name a reader sees, in the panel's language or the nearest there is. */
  name: string
  /** The account the profile belongs to — whose articles these are. */
  user: { id: number; name: string } | null
  /** The profile asked for a page. */
  is_public: boolean
  /** The page answers: the profile asked and the site allows author pages. */
  open: boolean
  /** Where the page is in the panel's language, whether or not it answers. */
  url: string | null
  photo: string | null
  articles_count: number
}

/** One profile as its form opens it: the row, and the values of `blog.author-form`. */
export interface AuthorDetail {
  author: AuthorRow
  values: ScreenModel
}

/** An account a new profile can be made for. */
export interface AuthorAccount {
  id: number
  name: string
  articles_count: number
}

export interface AuthorsApi {
  list(q?: string): Promise<AuthorRow[]>
  /** The accounts without a profile, the ones who wrote something first. */
  accounts(): Promise<AuthorAccount[]>
  get(id: number): Promise<AuthorDetail>
  /** A profile for an account, out of the values of its form, in one request. */
  create(userId: number, values: ScreenModel): Promise<AuthorDetail>
  /** Refused with a 422 under the name of the field. */
  save(id: number, values: ScreenModel): Promise<AuthorDetail>
  /** The profile goes; the articles stay the account's. */
  remove(id: number): Promise<void>
}

/** Everything under `/blog/authors`, below the panel's API path. */
export function createAuthorsApi(admin: AdminContext): AuthorsApi {
  const base = `${admin.apiPath}/blog/authors`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (q = '') => {
      const suffix = q.trim() === '' ? '' : `?q=${encodeURIComponent(q.trim())}`

      return admin.http.get<{ data: AuthorRow[] }>(`${base}${suffix}`).then(data)
    },
    accounts: () => admin.http.get<{ data: AuthorAccount[] }>(`${base}/accounts`).then(data),
    get: (id) => admin.http.get<{ data: AuthorDetail }>(`${base}/${id}`).then(data),
    create: (userId, values) =>
      admin.http.post<{ data: AuthorDetail }>(base, { user_id: userId, values }).then(data),
    save: (id, values) =>
      admin.http.put<{ data: AuthorDetail }>(`${base}/${id}`, { values }).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
  }
}
