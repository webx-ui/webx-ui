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
