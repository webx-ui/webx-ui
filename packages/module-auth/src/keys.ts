import type { AdminContext } from '@webx-ui/module-admin'
import type { IssuedMcpKey, McpKey, McpKeyInput, McpKeys } from './types'

export interface KeysApi {
  /** Every key, live ones first. Behind `admins.manage`. */
  list(): Promise<McpKeys>
  /** Issue one. The answer carries the secret, once. */
  issue(input: McpKeyInput): Promise<IssuedMcpKey>
  /** Switch one off. The row stays; the key stops working at once. */
  revoke(id: number): Promise<McpKey>
}

/** The three calls behind Administrators → Keys. */
export function createKeysApi(admin: AdminContext): KeysApi {
  const base = `${admin.apiPath}/auth/keys`

  return {
    list: () =>
      admin.http
        .get<{ data: McpKey[]; meta: { scopes: string[]; max_days: number } }>(base)
        .then((body) => ({
          data: body.data,
          scopes: body.meta.scopes,
          maxDays: body.meta.max_days,
        })),

    issue: (input) =>
      admin.http
        .post<{ data: McpKey; token: string }>(base, input)
        .then((body) => ({ key: body.data, token: body.token })),

    revoke: (id) => admin.http.delete<{ data: McpKey }>(`${base}/${id}`).then((body) => body.data),
  }
}
