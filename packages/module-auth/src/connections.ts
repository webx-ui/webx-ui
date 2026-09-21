import type { AdminContext } from '@webx-ui/module-admin'
import type { Connection, ConnectionScope, Connections } from './types'

export interface ConnectionsApi {
  /** The agents connected — this person's, or everybody's for whoever may see them. */
  list(scope?: ConnectionScope): Promise<Connections>
  /** End one. The row survives; the tokens behind it do not. */
  disconnect(id: number): Promise<Connection>
}

/**
 * The two calls behind the connections list.
 *
 * Separate from the administrators API because the audiences are: everybody signed in reads
 * their own connections and ends them, and nothing about that is managing other people's
 * accounts. The server decides which list it hands back — the scope here is a request, not a
 * claim.
 */
export function createConnectionsApi(admin: AdminContext): ConnectionsApi {
  const base = `${admin.apiPath}/auth/connections`

  return {
    list: (scope = 'mine') =>
      admin.http
        .get<{
          data: Connection[]
          meta: { scope: ConnectionScope; can_see_everybody: boolean }
        }>(base, { query: { all: scope === 'all' ? 1 : undefined } })
        .then((body) => ({
          data: body.data,
          scope: body.meta.scope,
          canSeeEverybody: body.meta.can_see_everybody,
        })),

    disconnect: (id) =>
      admin.http.delete<{ data: Connection }>(`${base}/${id}`).then((body) => body.data),
  }
}
