import type { AdminContext } from '@webx-ui/module-admin'
import type { Admin, AdminInput, AdminPage, AdminQuery, Role } from './types'

export interface AdminsApi {
  list(query?: AdminQuery): Promise<AdminPage>
  get(id: number): Promise<Admin>
  create(input: AdminInput): Promise<Admin>
  update(id: number, input: AdminInput): Promise<Admin>
  remove(id: number): Promise<void>
  roles(): Promise<Role[]>
}

/**
 * The calls behind the administrators screen, and behind the picker that opens from code.
 *
 * Reading is separate from managing on the server, so a module that only wants to say who
 * wrote something — or offer a list of people to assign work to — can use `list` and `roles`
 * without being allowed anywhere near the rest.
 */
export function createAdminsApi(admin: AdminContext): AdminsApi {
  const base = `${admin.apiPath}/auth`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: (query = {}) =>
      admin.http
        .get<{ data: Admin[]; meta: Omit<AdminPage, 'data'> }>(`${base}/admins`, {
          query: {
            q: query.q || undefined,
            role: query.role ?? undefined,
            active: query.active ?? undefined,
            sort: query.sort,
            page: query.page,
            per_page: query.per_page,
          },
        })
        .then((body) => ({ ...body.meta, data: body.data })),

    get: (id) => admin.http.get<{ data: Admin }>(`${base}/admins/${id}`).then(data),

    create: (input) => admin.http.post<{ data: Admin }>(`${base}/admins`, input).then(data),

    update: (id, input) =>
      admin.http.patch<{ data: Admin }>(`${base}/admins/${id}`, input).then(data),

    remove: (id) => admin.http.delete<void>(`${base}/admins/${id}`),

    roles: () => admin.http.get<{ data: Role[] }>(`${base}/roles`).then(data),
  }
}
