import type { AdminContext } from '@webx-ui/module-admin'
import type {
  Admin,
  AdminInput,
  AdminPage,
  AdminQuery,
  AgentCall,
  AgentCallFilters,
  AgentCallPage,
  AgentCallQuery,
  PermissionRow,
  Role,
  RoleDetails,
  RoleInput,
} from './types'

export interface AdminsApi {
  list(query?: AdminQuery): Promise<AdminPage>
  get(id: number): Promise<Admin>
  create(input: AdminInput): Promise<Admin>
  update(id: number, input: AdminInput): Promise<Admin>
  remove(id: number): Promise<void>
  roles(): Promise<Role[]>
  /** One role with the people who hold it. */
  role(id: number): Promise<RoleDetails>
  /** Every permission a role may list, by module. */
  permissions(): Promise<PermissionRow[]>
  /** Behind `roles.manage`, like the two below. */
  createRole(input: RoleInput): Promise<Role>
  updateRole(id: number, input: RoleInput): Promise<Role>
  removeRole(id: number): Promise<void>
  /** What agents did, newest first. Behind `admins.audit`. */
  calls(query?: AgentCallQuery): Promise<AgentCallPage>
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

    role: (id) => admin.http.get<{ data: RoleDetails }>(`${base}/roles/${id}`).then(data),

    permissions: () =>
      admin.http.get<{ data: PermissionRow[] }>(`${base}/roles/permissions`).then(data),

    createRole: (input) => admin.http.post<{ data: Role }>(`${base}/roles`, input).then(data),

    updateRole: (id, input) =>
      admin.http.put<{ data: Role }>(`${base}/roles/${id}`, input).then(data),

    removeRole: (id) => admin.http.delete<void>(`${base}/roles/${id}`),

    calls: (query = {}) =>
      admin.http
        .get<{
          data: AgentCall[]
          meta: Omit<AgentCallPage, 'data' | 'filters'>
          filters: AgentCallFilters
        }>(`${base}/mcp-calls`, {
          query: {
            user: query.user ?? undefined,
            tool: query.tool ?? undefined,
            outcome: query.outcome ?? undefined,
            page: query.page,
            per_page: query.per_page,
          },
        })
        .then((body) => ({ ...body.meta, data: body.data, filters: body.filters })),
  }
}
