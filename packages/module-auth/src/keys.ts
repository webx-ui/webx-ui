import type { AdminContext } from '@webx-ui/module-admin'
import type { IssuedMcpKey, McpKey, McpKeyInput, McpKeys } from './types'

export interface KeysApi {
  /**
   * Live ones first: your own, or everybody's with `admins.manage` — unless `mine` asks for
   * your own even then.
   */
  list(options?: { mine?: boolean }): Promise<McpKeys>
  /**
   * The scopes a key for this administrator may carry — those their permissions reach, and
   * `mcp:use`. Yourself when nobody is named; somebody else needs `admins.manage`.
   */
  scopesFor(admin?: number): Promise<string[]>
  /** Issue one. The answer carries the secret, once. */
  issue(input: McpKeyInput): Promise<IssuedMcpKey>
  /** Switch one off. The row stays; the key stops working at once. */
  revoke(id: number): Promise<McpKey>
}

/** The three calls behind Administrators → Keys. */
export function createKeysApi(admin: AdminContext): KeysApi {
  const base = `${admin.apiPath}/auth/keys`

  return {
    list: (options) =>
      admin.http
        .get<{ data: McpKey[]; meta: { scopes: string[]; max_days: number } }>(
          base,
          ...(options?.mine ? [{ query: { mine: 1 } }] : []),
        )
        .then((body) => ({
          data: body.data,
          scopes: body.meta.scopes,
          maxDays: body.meta.max_days,
        })),

    scopesFor: (owner) =>
      admin.http
        .get<{ data: string[] }>(`${base}/scopes`, { query: { admin: owner } })
        .then((body) => body.data),

    issue: (input) =>
      admin.http
        .post<{ data: McpKey; token: string }>(base, input)
        .then((body) => ({ key: body.data, token: body.token })),

    revoke: (id) => admin.http.delete<{ data: McpKey }>(`${base}/${id}`).then((body) => body.data),
  }
}
