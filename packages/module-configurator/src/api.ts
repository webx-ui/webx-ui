import type { AdminContext } from '@webx-ui/module-admin'
import type { ConfiguratorSchema } from './engine/types'
import type {
  ConfiguratorDetail,
  ConfiguratorQuery,
  ConfiguratorRow,
  ConfiguratorValues,
  ConfiguratorVersion,
  SchemaProblem,
  ServerQuote,
} from './types'

export interface ConfiguratorsApi {
  /** The whole list, without pages: a site has a handful. */
  list(query?: ConfiguratorQuery): Promise<ConfiguratorRow[]>
  get(id: number): Promise<ConfiguratorDetail>
  /** A draft with a name and a key and no steps yet. Refused under `key` or `title`. */
  create(title: string, key: string): Promise<ConfiguratorDetail>
  /** The draft. A stale revision is a 409 with the record as it is; problems do not stop it. */
  save(
    id: number,
    values: Partial<ConfiguratorValues>,
    revision?: string,
  ): Promise<ConfiguratorDetail>
  /** Refused with 422 and `problems` while the schema has any. */
  publish(id: number, revision?: string): Promise<ConfiguratorDetail>
  unpublish(id: number): Promise<ConfiguratorDetail>
  discard(id: number): Promise<ConfiguratorDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<ConfiguratorRow>
  versions(id: number): Promise<ConfiguratorVersion[]>
  restoreVersion(id: number, number: number): Promise<ConfiguratorDetail>
  /** The server's check of a document — the one a publication is held to. */
  validate(schema: unknown, id?: number): Promise<{ valid: boolean; errors: SchemaProblem[] }>
  /** The server's price of a combination, of the live schema or the draft. */
  quote(
    id: number,
    request: {
      values?: Record<string, unknown>
      actions?: unknown[]
      draft?: boolean
      today?: string
    },
  ): Promise<ServerQuote | { errors: SchemaProblem[] }>
}

/** The path of the section under the panel's API. */
export const CONFIGURATORS_API = 'configurators'

export function createConfiguratorsApi(admin: AdminContext): ConfiguratorsApi {
  const base = `${admin.apiPath}/${CONFIGURATORS_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = new URLSearchParams()

      if (query.search) search.set('search', query.search)
      if (query.trashed) search.set('trashed', '1')

      return admin.http
        .get<{ data: ConfiguratorRow[] }>(search.size > 0 ? `${base}?${search}` : base)
        .then(data)
    },
    get: (id) => admin.http.get<{ data: ConfiguratorDetail }>(`${base}/${id}`).then(data),
    create: (title, key) =>
      admin.http.post<{ data: ConfiguratorDetail }>(base, { title, key }).then(data),
    save: (id, values, revision) =>
      admin.http
        .put<{ data: ConfiguratorDetail }>(`${base}/${id}`, { values, revision })
        .then(data),
    publish: (id, revision) =>
      admin.http
        .post<{ data: ConfiguratorDetail }>(`${base}/${id}/publish`, { revision })
        .then(data),
    unpublish: (id) =>
      admin.http.post<{ data: ConfiguratorDetail }>(`${base}/${id}/unpublish`, {}).then(data),
    discard: (id) =>
      admin.http.post<{ data: ConfiguratorDetail }>(`${base}/${id}/discard`, {}).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    restore: (id) =>
      admin.http.post<{ data: ConfiguratorRow }>(`${base}/${id}/restore`, {}).then(data),
    versions: (id) =>
      admin.http.get<{ data: ConfiguratorVersion[] }>(`${base}/${id}/versions`).then(data),
    restoreVersion: (id, number) =>
      admin.http
        .post<{ data: ConfiguratorDetail }>(`${base}/${id}/versions/${number}/restore`, {})
        .then(data),
    validate: (schema, id) =>
      admin.http
        .post<{ data: { valid: boolean; errors: SchemaProblem[] } }>(`${base}/validate`, {
          schema: schema as ConfiguratorSchema,
          configurator: id,
        })
        .then(data),
    quote: (id, request) =>
      admin.http
        .post<{ data: ServerQuote | { errors: SchemaProblem[] } }>(`${base}/${id}/quote`, request)
        .then(data),
  }
}
