import type { AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type {
  GroupRow,
  ProviderSave,
  UserDetail,
  UserQuery,
  UsersMeta,
  UsersPage,
  UsersSettings,
} from './types'

/** Where «Customers» answer under the panel's API (§16). */
export const USERS_API = 'users'

/** The list's question as a query string; a filter left unset is not sent at all. */
export function userSearch(query: UserQuery): URLSearchParams {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') continue

    search.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }

  return search
}

export interface UsersApi {
  list(query?: UserQuery): Promise<UsersPage>
  meta(): Promise<UsersMeta>
  get(id: number): Promise<UserDetail>
  /** A new customer without a password; `invite` sends the letter «Set a password». */
  create(values: ScreenModel, invite: boolean): Promise<UserDetail>
  save(id: number, values: ScreenModel): Promise<UserDetail>
  /** What the modules say before the customer is deleted (§14.1). */
  warnings(id: number): Promise<string[]>
  remove(id: number): Promise<void>
  block(id: number, on: boolean): Promise<UserDetail>
  verify(id: number): Promise<UserDetail>
  sendReset(id: number): Promise<void>
  sendVerification(id: number): Promise<void>
  unlink(id: number, provider: string): Promise<UserDetail>
  groups(): Promise<GroupRow[]>
  createGroup(group: { code: string; name: string }): Promise<GroupRow>
  saveGroup(id: number, group: { code: string; name: string }): Promise<GroupRow>
  removeGroup(id: number): Promise<void>
  settings(): Promise<UsersSettings>
  saveSettings(values: ScreenModel): Promise<UsersSettings>
  saveProvider(key: string, changes: ProviderSave): Promise<UsersSettings>
}

export function createUsersApi(admin: AdminContext): UsersApi {
  const base = `${admin.apiPath}/${USERS_API}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) => {
      const search = userSearch(query)

      return admin.http.get<UsersPage>(search.size > 0 ? `${base}?${search}` : base)
    },
    meta: () => admin.http.get<{ data: UsersMeta }>(`${base}/meta`).then(data),
    get: (id) => admin.http.get<{ data: UserDetail }>(`${base}/${id}`).then(data),
    create: (values, invite) =>
      admin.http.post<{ data: UserDetail }>(base, { values, invite }).then(data),
    save: (id, values) =>
      admin.http.put<{ data: UserDetail }>(`${base}/${id}`, { values }).then(data),
    warnings: (id) =>
      admin.http
        .get<{ data: { warnings: string[] } }>(`${base}/${id}/warnings`)
        .then((body) => body.data.warnings),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    block: (id, on) =>
      admin.http
        .post<{ data: UserDetail }>(`${base}/${id}/${on ? 'block' : 'unblock'}`, {})
        .then(data),
    verify: (id) => admin.http.post<{ data: UserDetail }>(`${base}/${id}/verify`, {}).then(data),
    sendReset: (id) => admin.http.post<void>(`${base}/${id}/reset-link`, {}).then(() => undefined),
    sendVerification: (id) =>
      admin.http.post<void>(`${base}/${id}/verification`, {}).then(() => undefined),
    unlink: (id, provider) =>
      admin.http
        .delete<{ data: UserDetail }>(`${base}/${id}/ways/${encodeURIComponent(provider)}`)
        .then(data),
    groups: () => admin.http.get<{ data: GroupRow[] }>(`${base}/groups`).then(data),
    createGroup: (group) => admin.http.post<{ data: GroupRow }>(`${base}/groups`, group).then(data),
    saveGroup: (id, group) =>
      admin.http.put<{ data: GroupRow }>(`${base}/groups/${id}`, group).then(data),
    removeGroup: (id) => admin.http.delete<void>(`${base}/groups/${id}`).then(() => undefined),
    settings: () => admin.http.get<{ data: UsersSettings }>(`${base}/settings`).then(data),
    saveSettings: (values) =>
      admin.http.put<{ data: UsersSettings }>(`${base}/settings`, { values }).then(data),
    saveProvider: (key, changes) =>
      admin.http
        .put<{
          data: UsersSettings
        }>(`${base}/settings/providers/${encodeURIComponent(key)}`, changes)
        .then(data),
  }
}
