/** The shapes `webx-ui/module-auth` answers with on its administrators endpoints. */

export interface AdminRole {
  id: number
  slug: string
  name: string
}

export interface Role extends AdminRole {
  permissions: string[]
  /** How many administrators hold it — the number that makes a role safe or risky to change. */
  users: number
}

export interface Admin {
  id: number
  name: string
  email: string
  /**
   * The media library's key, or `null`.
   *
   * A key and not an address, like everything else stored about a picture: where it lives is
   * worked out where it is drawn, so moving the library changes nothing here.
   */
  avatar: string | null
  is_super: boolean
  is_active: boolean
  locale: string | null
  last_login_at: string | null
  created_at: string | null
  roles: AdminRole[]
  /**
   * What a table row is, as far as `WxTable` is concerned.
   *
   * Without it this type does not extend `TableRow`, the table's generic falls back to its
   * default, and every cell slot hands back `unknown`.
   */
  [key: string]: unknown
}

export interface AdminQuery {
  q?: string
  role?: string | null
  active?: 'yes' | 'no' | null
  sort?: string
  page?: number
  per_page?: number
}

/**
 * A page, flat — the shape `->paginate()` serialises and `WxTable` reads as it arrives.
 *
 * A resource collection nests the same numbers under `meta`; the client here flattens them so
 * that no screen has to translate between the two.
 */
export interface AdminPage {
  data: Admin[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

/**
 * One tool call an agent made, as `GET /api/cms/auth/mcp-calls` answers it.
 *
 * `user` is null for a call on the stdio server, where there was nobody to act as; a user with
 * no `name` was deleted since. `arguments` is the JSON as the server kept it — secrets blanked,
 * cut to a length — and so is text to show, not an object to read.
 */
export interface AgentCall {
  id: number
  at: string | null
  user: { id: number; name: string | null } | null
  /** What the client called itself on the consent screen; null off a connection. */
  client: string | null
  tool: string
  arguments: string | null
  dry_run: boolean
  ok: boolean
  error: string | null
  duration_ms: number
  [key: string]: unknown
}

export type AgentCallOutcome = 'ok' | 'failed' | 'dry'

export interface AgentCallQuery {
  /** An administrator's id, or `none` for the stdio server. */
  user?: string | null
  tool?: string | null
  outcome?: AgentCallOutcome | null
  page?: number
  per_page?: number
}

/** What the log can be narrowed by: the people and the tools that actually appear in it. */
export interface AgentCallFilters {
  users: { id: number | null; name: string | null }[]
  tools: string[]
}

export interface AgentCallPage {
  data: AgentCall[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  filters: AgentCallFilters
}

/** What a form sends. `password` left out is a password left alone. */
export interface AdminInput {
  name: string
  email: string
  password?: string
  avatar?: string | null
  is_active?: boolean
  is_super?: boolean
  locale?: string | null
  roles?: number[]
}
