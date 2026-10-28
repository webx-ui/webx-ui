import type {
  Admin,
  PermissionRow,
  Role,
  RoleDetails,
} from '../../../../packages/module-auth/src/types'
import type { ManifestModule } from '../../../../packages/module-admin/src/types'
import { adminRows, roles } from './agents'

/*
 * Who is signed in, and the roles (WEBX_UI_PERMISSIONS.md §11) — enough of `module-auth` to look
 * at the panel as somebody with less than everything.
 *
 * Signing in is pretend: the corner menu of the playground switches between the three fixture
 * administrators, and everything the server answers — the manifest's `allowed`, the matrix, the
 * rules on saving a role — is worked out for whoever is current. Anna is the super
 * administrator; Dmytro edits content; Olha deals with people and roles but not with content.
 */

type On = (
  method: string,
  pattern: string,
  handler: (context: {
    params: string[]
    query: URLSearchParams
    body: Record<string, unknown>
    locale: string
  }) => unknown,
) => void
type Fail = (
  status: number,
  message: string,
  errors?: Record<string, string[]>,
  extra?: Record<string, unknown>,
) => Error
type Line = (locale: string, namespace: string, path: string) => string

/** Sections without permissions of their own, and the permissions that open them (§11). */
const VISIBLE_WITH: Record<string, string[]> = {
  'catalog-categories': ['catalog.view', 'catalog.manage'],
  'catalog-brands': ['catalog.view', 'catalog.manage'],
  'catalog-labels': ['catalog.view', 'catalog.manage'],
  'catalog-stock': ['catalog.view', 'catalog.manage'],
  'catalog-properties': ['catalog.view', 'catalog.manage'],
  'catalog-landings': ['catalog.view', 'catalog.manage'],
  regions: ['blocks.regions'],
  users: ['users.view', 'users.manage'],
  'users-groups': ['users.view', 'users.manage'],
  'users-settings': ['users.settings'],
}

let current = 1

/** What the profile may send, and nothing else (WEBX_UI_PERMISSIONS.md §8). */
const PROFILE = ['name', 'avatar', 'locale', 'password', 'current_password']

/** Every fixture administrator's current password, for the profile to ask for. */
const PASSWORD = 'password'

/** The theme and the panel on the site, per administrator — not columns of the fixture rows. */
const preferences = new Map<number, { theme?: 'light' | 'dark' | null; siteBar?: boolean }>()

export function currentAdmin(): Admin {
  return adminRows.find((row) => row.id === current) ?? adminRows[0]
}

/** What an administrator holds: every permission of every role, joined. Nothing for a super one. */
export function permissionsOf(admin: Admin): string[] {
  if (admin.is_super) return []

  const held = new Set<string>()

  for (const mine of admin.roles) {
    for (const permission of roles.find((one) => one.id === mine.id)?.permissions ?? []) {
      held.add(permission)
    }
  }

  return [...held].sort()
}

export function holds(admin: Admin, permission: string): boolean {
  return admin.is_super || permissionsOf(admin).includes(permission)
}

/**
 * `GET /auth/keys/scopes`: what a key for this administrator may carry — a scope whose tools
 * their permissions reach, read the way the server derives a tool's permission from its scope.
 */
export function usableScopes(adminId: number | undefined, all: string[]): string[] {
  const owner = adminRows.find((row) => row.id === adminId) ?? currentAdmin()
  const special: Record<string, string[]> = {
    'admins:read': ['admins.view', 'admins.manage', 'roles.manage', 'roles.assign'],
    'admins:write': ['roles.manage', 'roles.assign'],
    'admins:audit': ['admins.audit'],
  }

  return all.filter((scope) => {
    if (scope === 'mcp:use') return true

    const [module = '', access = ''] = scope.split(':')
    const needs =
      special[scope] ??
      (access === 'read' ? [`${module}.view`, `${module}.manage`] : [`${module}.manage`])

    return needs.some((permission) => holds(owner, permission))
  })
}

/** The manifest's modules, each saying whether the current administrator may open it. */
export function withAccess(modules: ManifestModule[]): ManifestModule[] {
  const admin = currentAdmin()

  return modules.map((module) => {
    const wanted = VISIBLE_WITH[module.id] ?? module.permissions

    return {
      ...module,
      allowed: wanted.length === 0 || wanted.some((permission) => holds(admin, permission)),
    }
  })
}

function describe(role: Role, admin: Admin): Role {
  return {
    ...role,
    users: adminRows.filter((row) => row.roles.some((one) => one.id === role.id)).length,
    held: admin.roles.some((one) => one.id === role.id),
  }
}

function strings(value: unknown): string[] {
  return Array.isArray(value) ? value.map(String) : []
}

function slugOf(name: string): string {
  const base =
    name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '') || 'role'
  let slug = base

  for (let n = 2; roles.some((one) => one.slug === slug); n++) slug = `${base}-${n}`

  return slug
}

export function registerRoles(
  on: On,
  fail: Fail,
  line: Line,
  modules: (locale: string) => ManifestModule[],
): void {
  const refuse = (locale: string, rule: string, missing: string[] = []) =>
    fail(403, line(locale, 'webx-auth', `errors.${rule}`), undefined, {
      rule,
      ...(missing.length > 0 ? { missing } : {}),
    })

  const may = (locale: string, ...permissions: string[]) => {
    const admin = currentAdmin()

    if (!permissions.some((permission) => holds(admin, permission))) {
      throw refuse(locale, 'forbidden', permissions)
    }
  }

  /* Rule 2 when saving a role, rule 5 always: what a role gains, its editor must hold. */
  const mayEdit = (locale: string, role: Role | null, permissions: string[]) => {
    const admin = currentAdmin()

    if (admin.is_super) return
    if (role && admin.roles.some((one) => one.id === role.id)) throw refuse(locale, 'role-you-hold')

    const gained = permissions.filter(
      (permission) => !(role?.permissions ?? []).includes(permission),
    )
    const missing = gained.filter((permission) => !holds(admin, permission))

    if (missing.length > 0) throw refuse(locale, 'beyond-yours', missing)
  }

  const label = (locale: string, permission: string): string => {
    const own = `permissions.${permission.replaceAll('.', '-')}`
    const found = line(locale, 'webx-auth', own)

    if (found !== own) return found

    const action = permission.split('.').at(-1) ?? permission
    const word = line(locale, 'webx-auth', `permissions.${action}`)

    return word === `permissions.${action}` ? action : word
  }

  const me = () => {
    const admin = currentAdmin()
    const own = preferences.get(admin.id) ?? {}

    return {
      data: {
        id: admin.id,
        name: admin.name,
        email: admin.email,
        isSuper: admin.is_super,
        locale: admin.locale,
        theme: own.theme ?? null,
        siteBar: own.siteBar ?? true,
        // A playground «sends» the letter into nowhere, the way a local machine logs it.
        emailChange: true,
        avatar: admin.avatar,
        roles: admin.roles,
        permissions: permissionsOf(admin),
      },
    }
  }

  on('GET', '/auth/me', me)

  /* What a browser asks before its first write on a real site; nothing to hand out here. */
  on('GET', '/auth/csrf-cookie', () => ({}))

  /*
   * Yourself (WEBX_UI_PERMISSIONS.md §8): the profile takes its own fields and refuses any other
   * by name, the password only with the current one — which in the playground is `password`.
   */
  on('PUT', '/auth/me', ({ body, locale }) => {
    const admin = currentAdmin()
    const errors: Record<string, string[]> = {}

    for (const field of Object.keys(body)) {
      if (!PROFILE.includes(field)) {
        errors[field] = [line(locale, 'webx-auth', 'profile.not-yours').replace(':field', field)]
      }
    }

    if (String(body.name ?? '').trim() === '') errors.name = ['The name is required.']

    if (body.password && body.current_password !== PASSWORD) {
      errors.current_password = [line(locale, 'webx-auth', 'profile.wrong-password')]
    }

    if (Object.keys(errors).length > 0) throw fail(422, 'The given data was invalid.', errors)

    admin.name = String(body.name)
    admin.avatar = typeof body.avatar === 'string' ? body.avatar : null

    return me()
  })

  on('POST', '/auth/me/email', ({ body, locale }) => {
    const admin = currentAdmin()
    const email = String(body.email ?? '').trim()
    const errors: Record<string, string[]> = {}

    if (body.current_password !== PASSWORD) {
      errors.current_password = [line(locale, 'webx-auth', 'profile.wrong-password')]
    }

    if (!/^[^@\s]+@[^@\s]+$/.test(email)) errors.email = ['Enter an email address.']
    else if (email === admin.email) errors.email = [line(locale, 'webx-auth', 'profile.email-same')]
    else if (adminRows.some((row) => row.email === email)) errors.email = ['That address is taken.']

    if (Object.keys(errors).length > 0) throw fail(422, 'The given data was invalid.', errors)

    return { data: { email, minutes: 60 } }
  })

  on('PUT', '/auth/locale', ({ body }) => {
    currentAdmin().locale = typeof body.locale === 'string' ? body.locale : null

    return me()
  })

  on('PUT', '/auth/theme', ({ body }) => {
    const id = currentAdmin().id
    const theme = body.theme === 'light' || body.theme === 'dark' ? body.theme : null

    preferences.set(id, { ...preferences.get(id), theme })

    return me()
  })

  on('PUT', '/auth/site-bar', ({ body }) => {
    const id = currentAdmin().id

    preferences.set(id, { ...preferences.get(id), siteBar: body.enabled === true })

    return me()
  })

  /* Not a real endpoint: how the playground's corner menu «signs in» as somebody else. */
  on('PUT', '/auth/as', ({ body }) => {
    const id = Number(body.id)

    if (!adminRows.some((row) => row.id === id)) throw fail(404, 'No such administrator.')

    current = id

    return { data: { id } }
  })

  /*
   * The form of an administrator, as far as roles go: several at once, never your own, never one
   * that holds what you do not (rules 1 and 2). The rest of the account is kept as sent.
   */
  const assign = (
    locale: string,
    target: Admin | null,
    body: Record<string, unknown>,
  ): Admin['roles'] | null => {
    if (body.roles === undefined) return null

    const wanted = strings(body.roles).map(Number)
    const held = target?.roles.map((one) => one.id) ?? []
    const adding = wanted.filter((id) => !held.includes(id))
    const removing = held.filter((id) => !wanted.includes(id))
    const admin = currentAdmin()

    if ((adding.length > 0 || removing.length > 0) && !admin.is_super) {
      may(locale, 'roles.assign')

      if (target?.id === admin.id) throw refuse(locale, 'own-roles')

      const missing = adding
        .flatMap((id) => roles.find((one) => one.id === id)?.permissions ?? [])
        .filter((permission) => !holds(admin, permission))

      if (missing.length > 0) throw refuse(locale, 'beyond-yours', [...new Set(missing)])
    }

    return roles
      .filter((one) => wanted.includes(one.id))
      .map((one) => ({ id: one.id, slug: one.slug, name: one.name }))
  }

  const fill = (row: Admin, body: Record<string, unknown>) => {
    if (typeof body.name === 'string') row.name = body.name
    if (typeof body.email === 'string') row.email = body.email
    if (typeof body.is_active === 'boolean') row.is_active = body.is_active
    if (typeof body.is_super === 'boolean' && currentAdmin().is_super) row.is_super = body.is_super
    if (body.locale !== undefined) row.locale = (body.locale as string | null) ?? null
    if (body.avatar !== undefined) row.avatar = (body.avatar as string | null) ?? null
  }

  on('POST', '/auth/admins', ({ body, locale }) => {
    may(locale, 'admins.manage')

    const row: Admin = {
      id: Math.max(0, ...adminRows.map((one) => one.id)) + 1,
      name: '',
      email: '',
      avatar: null,
      is_super: false,
      is_active: true,
      locale: null,
      last_login_at: null,
      created_at: new Date().toISOString(),
      roles: [],
    }

    row.roles = assign(locale, null, body) ?? []
    fill(row, body)
    adminRows.push(row)

    return { data: row }
  })

  on('PATCH', '/auth/admins/(\\d+)', ({ params, body, locale }) => {
    may(locale, 'admins.manage')

    const row = adminRows.find((one) => one.id === Number(params[0]))

    if (row === undefined) throw fail(404, 'No such administrator.')
    if (row.is_super && !currentAdmin().is_super) throw refuse(locale, 'only-super')

    const next = assign(locale, row, body)

    fill(row, body)
    if (next !== null) row.roles = next

    return { data: row }
  })

  on('DELETE', '/auth/admins/(\\d+)', ({ params, locale }) => {
    may(locale, 'admins.manage')

    const index = adminRows.findIndex((one) => one.id === Number(params[0]))

    if (index < 0) throw fail(404, 'No such administrator.')
    if (adminRows[index].id === current) throw refuse(locale, 'not-yourself')
    if (adminRows[index].is_super && !currentAdmin().is_super) throw refuse(locale, 'only-super')

    adminRows.splice(index, 1)

    return { data: { deleted: true } }
  })

  on('GET', '/auth/roles', ({ locale }) => {
    may(locale, 'admins.view', 'admins.manage', 'roles.manage', 'roles.assign')

    return {
      data: [...roles]
        .sort((one, two) => one.name.localeCompare(two.name))
        .map((role) => describe(role, currentAdmin())),
    }
  })

  on('GET', '/auth/roles/permissions', ({ locale }) => {
    may(locale, 'admins.view', 'admins.manage', 'roles.manage', 'roles.assign')

    const admin = currentAdmin()
    const seen = new Set<string>()
    const rows: PermissionRow[] = []

    for (const module of [...modules(locale)].sort((one, two) => one.order - two.order)) {
      const cells = module.permissions
        .filter((permission) => !seen.has(permission))
        .map((permission) => {
          seen.add(permission)

          return {
            name: permission,
            label: label(locale, permission),
            held: holds(admin, permission),
          }
        })

      if (cells.length > 0)
        rows.push({ module: module.id, title: module.title, permissions: cells })
    }

    return { data: rows }
  })

  on('GET', '/auth/roles/(\\d+)', ({ params, locale }) => {
    may(locale, 'admins.view', 'admins.manage', 'roles.manage', 'roles.assign')

    const role = roles.find((one) => one.id === Number(params[0]))

    if (role === undefined) throw fail(404, 'No such role.')

    const details: RoleDetails = {
      ...describe(role, currentAdmin()),
      holders: adminRows
        .filter((row) => row.roles.some((one) => one.id === role.id))
        .map((row) => ({ id: row.id, name: row.name, email: row.email })),
    }

    return { data: details }
  })

  on('POST', '/auth/roles', ({ body, locale }) => {
    may(locale, 'roles.manage')

    const name = String(body.name ?? '').trim()
    const permissions = strings(body.permissions)

    if (name === '')
      throw fail(422, 'The name field is required.', { name: ['The name field is required.'] })

    mayEdit(locale, null, permissions)

    const slug = typeof body.slug === 'string' && body.slug !== '' ? body.slug : slugOf(name)

    if (roles.some((one) => one.slug === slug)) {
      throw fail(422, 'The code has already been taken.', {
        slug: ['The code has already been taken.'],
      })
    }

    const role: Role = {
      id: Math.max(0, ...roles.map((one) => one.id)) + 1,
      slug,
      name,
      permissions,
      users: 0,
    }

    roles.push(role)

    return { data: describe(role, currentAdmin()) }
  })

  on('PUT', '/auth/roles/(\\d+)', ({ params, body, locale }) => {
    may(locale, 'roles.manage')

    const role = roles.find((one) => one.id === Number(params[0]))

    if (role === undefined) throw fail(404, 'No such role.')

    const permissions =
      body.permissions === undefined ? role.permissions : strings(body.permissions)

    mayEdit(locale, role, permissions)

    if (typeof body.name === 'string' && body.name.trim() !== '') role.name = body.name.trim()
    if (typeof body.slug === 'string' && body.slug !== '') role.slug = body.slug
    role.permissions = permissions

    return { data: describe(role, currentAdmin()) }
  })

  on('DELETE', '/auth/roles/(\\d+)', ({ params, locale }) => {
    may(locale, 'roles.manage')

    const index = roles.findIndex((one) => one.id === Number(params[0]))

    if (index < 0) throw fail(404, 'No such role.')

    mayEdit(locale, roles[index], roles[index].permissions)

    const [gone] = roles.splice(index, 1)

    for (const row of adminRows) row.roles = row.roles.filter((one) => one.id !== gone.id)

    return { data: { deleted: true } }
  })
}
