/**
 * «Customers» (`module-users`, spec §16): the visitors of the demo site, their groups, and the
 * settings of the registration and the social networks — in the shapes the PHP controllers answer.
 *
 * Fake people only: addresses on `example.test`, phones in the `+800` range nobody owns. Nothing
 * here holds a password or a secret the panel could read back — the fixture keeps «set» and the
 * secret itself never leaves this file, as on a site.
 */

export interface GroupRecord {
  id: number
  code: string
  name: string
  created_at: string
}

export interface IdentityRecord {
  provider: string
  email: string | null
  created_at: string
  last_used_at: string | null
}

export interface UserRecord {
  id: number
  email: string
  first_name: string | null
  last_name: string | null
  phone: string | null
  verified_at: string | null
  blocked_at: string | null
  has_password: boolean
  locale: string | null
  extra: Record<string, unknown>
  groups: string[]
  identities: IdentityRecord[]
  created_at: string
  last_sign_in_at: string | null
}

interface ProviderRecord {
  key: string
  label: string
  enabled: boolean
  saved: boolean
  fields: { name: string; label: string; secret: boolean; required: boolean }[]
  options: Record<string, string>
  /** Only whether each is set: the playground has no secret to keep. */
  credentials: Record<string, boolean>
  /** What `.env` fills where the panel left a field empty. */
  env: Record<string, boolean>
}

const day = (offset: number, hour = 10) =>
  new Date(Date.UTC(2026, 9, 27 + offset, hour, 15)).toISOString().replace('.000Z', '+00:00')

export const userGroups: GroupRecord[] = [
  { id: 1, code: 'wholesale', name: 'Оптовые', created_at: day(-60) },
  { id: 2, code: 'club', name: 'Клуб', created_at: day(-40) },
]

function person(id: number, fields: Partial<UserRecord> & { email: string }): UserRecord {
  return {
    id,
    first_name: null,
    last_name: null,
    phone: null,
    verified_at: day(-id * 3),
    blocked_at: null,
    has_password: true,
    locale: 'ru',
    extra: {},
    groups: [],
    identities: [],
    created_at: day(-id * 3, 9),
    last_sign_in_at: day(-id),
    ...fields,
  }
}

export const users: UserRecord[] = [
  person(1, {
    email: 'anna.demo@example.test',
    first_name: 'Анна',
    last_name: 'Петрова',
    phone: '+800 1234 5678',
    groups: ['wholesale', 'club'],
    extra: { 'club-card': 'C-1024' },
  }),
  person(2, {
    email: 'ihor.demo@example.test',
    first_name: 'Игорь',
    last_name: 'Мельник',
    phone: '+800 1234 5679',
    has_password: false,
    identities: [
      {
        provider: 'google',
        email: 'ihor.demo@example.test',
        created_at: day(-6),
        last_used_at: day(-1),
      },
    ],
  }),
  person(3, {
    email: 'maria.demo@example.test',
    first_name: 'Мария',
    phone: '+800 1234 5680',
    verified_at: null,
    groups: ['club'],
    last_sign_in_at: null,
  }),
  person(4, {
    email: 'oleg.demo@example.test',
    first_name: 'Олег',
    last_name: 'Сидоренко',
    blocked_at: day(-2),
    identities: [
      { provider: 'facebook', email: null, created_at: day(-11), last_used_at: day(-9) },
    ],
  }),
  person(5, {
    email: 'buyer.with.a.very.long.address.demo@example.test',
    phone: '+800 1234 5682',
    groups: ['wholesale'],
  }),
  ...Array.from({ length: 23 }, (_, index) =>
    person(6 + index, {
      email: `customer-${6 + index}.demo@example.test`,
      first_name: ['Ольга', 'Денис', 'Ирина', 'Павел'][index % 4]!,
      verified_at: index % 3 === 0 ? null : day(-30),
      last_sign_in_at: index % 5 === 0 ? null : day(-index - 2),
      groups: index % 4 === 0 ? ['club'] : [],
    }),
  ),
]

const providers: ProviderRecord[] = [
  {
    key: 'google',
    label: 'Google',
    enabled: true,
    saved: true,
    fields: [
      { name: 'client_id', label: 'Client ID', secret: false, required: true },
      { name: 'client_secret', label: 'Client secret', secret: true, required: true },
    ],
    options: { client_id: '1234567890-demo.apps.googleusercontent.com' },
    credentials: { client_secret: true },
    env: {},
  },
  {
    key: 'facebook',
    label: 'Facebook',
    enabled: false,
    saved: false,
    fields: [
      { name: 'app_id', label: 'App ID', secret: false, required: true },
      { name: 'app_secret', label: 'App secret', secret: true, required: true },
    ],
    options: {},
    credentials: {},
    env: { app_id: true },
  },
]

/** `users.registration` and `users.fields.*` as the settings store holds them. */
const settingsValues: Record<string, unknown> = { 'users.registration': 'open' }

const missing = (provider: ProviderRecord) =>
  provider.fields
    .filter(
      (field) =>
        field.required &&
        !(field.secret ? provider.credentials[field.name] : provider.options[field.name]) &&
        !provider.env[field.name],
    )
    .map((field) => field.name)

const shown = (provider: ProviderRecord) => provider.enabled && missing(provider).length === 0
const shownKeys = () => providers.filter(shown).map((provider) => provider.key)

const labelOf = (key: string) =>
  key === 'password' ? 'Пароль' : (providers.find((one) => one.key === key)?.label ?? key)

export function findUser(id: number): UserRecord | null {
  return users.find((user) => user.id === id) ?? null
}

function nameOf(user: UserRecord): string | null {
  const name = [user.first_name, user.last_name].filter(Boolean).join(' ')

  return name === '' ? null : name
}

function waysOf(user: UserRecord): string[] {
  return [
    ...(user.has_password ? ['password'] : []),
    ...user.identities.map((identity) => identity.provider),
  ]
}

export function userRow(user: UserRecord) {
  return {
    id: user.id,
    name: nameOf(user),
    email: user.email,
    phone: user.phone,
    verified: user.verified_at !== null,
    blocked: user.blocked_at !== null,
    groups: userGroups
      .filter((group) => user.groups.includes(group.code))
      .sort((a, b) => a.name.localeCompare(b.name))
      .map((group) => ({ code: group.code, name: group.name })),
    ways: waysOf(user),
    ways_off: user.identities
      .map((identity) => identity.provider)
      .filter((key) => !shownKeys().includes(key)),
    created_at: user.created_at,
    last_sign_in_at: user.last_sign_in_at,
  }
}

/** `GET /users` — searched, filtered, sorted and cut into pages like `UserList`. */
export function listUsers(query: URLSearchParams) {
  const term = (query.get('search') ?? '').trim().toLowerCase()
  const digits = term.replace(/\D+/g, '')
  const flag = (key: string) => (query.has(key) ? query.get(key) === '1' : null)

  let found = users.filter((user) => {
    if (term !== '') {
      const name = `${user.first_name ?? ''} ${user.last_name ?? ''}`.toLowerCase()
      const words = term.split(/\s+/).filter(Boolean)
      const byName = words.every((word) => name.includes(word))
      const byPhone =
        (user.phone ?? '').toLowerCase().includes(term) ||
        (digits.length >= 3 && (user.phone ?? '').replace(/\D+/g, '').includes(digits))

      if (!user.email.includes(term) && !byName && !byPhone) return false
    }

    const group = query.get('group')
    if (group && !user.groups.includes(group)) return false

    const verified = flag('verified')
    if (verified !== null && (user.verified_at !== null) !== verified) return false

    const blocked = flag('blocked')
    if (blocked !== null && (user.blocked_at !== null) !== blocked) return false

    const provider = query.get('provider')
    if (provider && !waysOf(user).includes(provider)) return false

    const from = query.get('from')
    if (from && user.created_at.slice(0, 10) < from) return false

    const to = query.get('to')
    if (to && user.created_at.slice(0, 10) > to) return false

    return true
  })

  const sort = query.get('sort') ?? '-created_at'

  found = [...found].sort((a, b) => {
    if (sort === 'email') return a.email.localeCompare(b.email)
    if (sort === '-last_sign_in_at') {
      if (a.last_sign_in_at === b.last_sign_in_at) return b.id - a.id
      if (a.last_sign_in_at === null) return 1
      if (b.last_sign_in_at === null) return -1

      return b.last_sign_in_at.localeCompare(a.last_sign_in_at)
    }

    return b.created_at.localeCompare(a.created_at) || b.id - a.id
  })

  const perPage = Math.max(1, Number(query.get('per_page') ?? 25) || 25)
  const page = Math.max(1, Number(query.get('page') ?? 1) || 1)
  const total = found.length
  const rows = found.slice((page - 1) * perPage, page * perPage)

  return {
    data: rows.map(userRow),
    current_page: page,
    last_page: Math.max(1, Math.ceil(total / perPage)),
    per_page: perPage,
    total,
    from: rows.length > 0 ? (page - 1) * perPage + 1 : null,
    to: rows.length > 0 ? (page - 1) * perPage + rows.length : null,
  }
}

export function usersMeta() {
  return {
    groups: [...userGroups]
      .sort((a, b) => a.name.localeCompare(b.name))
      .map((group) => ({ code: group.code, name: group.name })),
    providers: providers.map((provider) => ({
      key: provider.key,
      label: provider.label,
      shown: shown(provider),
    })),
  }
}

/** The card: the record and the values of `users.form`. */
export function userDetail(user: UserRecord) {
  const working = shownKeys()
  const ways = [
    ...(user.has_password
      ? [
          {
            key: 'password',
            label: labelOf('password'),
            email: null,
            linked_at: null,
            used_at: null,
            shown: true,
          },
        ]
      : []),
    ...user.identities.map((identity) => ({
      key: identity.provider,
      label: labelOf(identity.provider),
      email: identity.email,
      linked_at: identity.created_at,
      used_at: identity.last_used_at,
      shown: working.includes(identity.provider),
    })),
  ]
  const live = ways.filter((way) => way.shown).length

  return {
    user: {
      id: user.id,
      name: nameOf(user) ?? user.email.split('@')[0]!,
      email: user.email,
      verified: user.verified_at !== null,
      verified_at: user.verified_at,
      blocked: user.blocked_at !== null,
      blocked_at: user.blocked_at,
      has_password: user.has_password,
      locale: user.locale,
      created_at: user.created_at,
      last_sign_in_at: user.last_sign_in_at,
      ways: ways.map((way) => ({
        ...way,
        can_unlink: way.key !== 'password' && (!way.shown || live > 1),
      })),
    },
    values: {
      ...user.extra,
      first_name: user.first_name,
      last_name: user.last_name,
      phone: user.phone,
      email: user.email,
      groups: [...user.groups],
    },
  }
}

const now = () => new Date().toISOString().replace(/\.\d+Z$/, '+00:00')

/** A save of the card: the columns, the site's fields, the groups — 422 like the server. */
export function writeUser(
  record: UserRecord | null,
  values: Record<string, unknown>,
): { record: UserRecord } | { errors: Record<string, string[]> } {
  const errors: Record<string, string[]> = {}
  const text = (key: string) => {
    const value = values[key]

    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null
  }

  if (record === null || 'email' in values) {
    const email = (text('email') ?? '').toLowerCase()

    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
      errors.email = ['The E-mail field must be a valid email address.']
    } else if (users.some((user) => user.email === email && user.id !== record?.id)) {
      errors.email = ['Another customer signs in with this address.']
    }
  }

  const groups = Array.isArray(values.groups) ? values.groups.map(String) : null
  const unknown = (groups ?? []).filter((code) => !userGroups.some((group) => group.code === code))

  if (unknown.length > 0) errors.groups = [`There is no group ${unknown.join(', ')}.`]

  const card = text('club-card')

  if (card && users.some((user) => user.extra['club-card'] === card && user.id !== record?.id)) {
    errors['club-card'] = ['This value is already taken.']
  }

  if (Object.keys(errors).length > 0) return { errors }

  const target =
    record ??
    person(Math.max(...users.map((user) => user.id)) + 1, {
      email: '',
      verified_at: null,
      has_password: false,
      last_sign_in_at: null,
      created_at: now(),
    })

  for (const key of ['first_name', 'last_name', 'phone'] as const) {
    if (key in values) target[key] = text(key)
  }

  if ('email' in values) {
    const email = (text('email') ?? '').toLowerCase()

    // A new address is set at once and unconfirmed (§16.2).
    if (record !== null && email !== record.email) target.verified_at = null
    target.email = email
  }

  if ('club-card' in values) target.extra = { ...target.extra, 'club-card': card }
  if (groups !== null) target.groups = groups
  if (record === null) users.push(target)

  return { record: target }
}

export function removeUser(user: UserRecord): void {
  users.splice(users.indexOf(user), 1)
}

export function unlinkWay(user: UserRecord, provider: string): string | null {
  const detail = userDetail(user)
  const way = detail.user.ways.find((one) => one.key === provider)

  if (!way || way.key === 'password') return 'The network is not linked.'
  if (!way.can_unlink) return 'Cannot unlink the last way to sign in.'

  user.identities = user.identities.filter((identity) => identity.provider !== provider)

  return null
}

export function groupRows() {
  return [...userGroups]
    .sort((a, b) => a.name.localeCompare(b.name) || a.id - b.id)
    .map((group) => ({
      ...group,
      users_count: users.filter((user) => user.groups.includes(group.code)).length,
    }))
}

export function writeGroup(
  record: GroupRecord | null,
  input: Record<string, unknown>,
): { record: GroupRecord } | { errors: Record<string, string[]> } {
  const code = String(input.code ?? '')
    .trim()
    .toLowerCase()
  const name = String(input.name ?? '').trim()
  const errors: Record<string, string[]> = {}

  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(code)) {
    errors.code = [
      'The code is lowercase Latin letters and digits, words joined by «-»: wholesale, club-gold.',
    ]
  } else if (userGroups.some((group) => group.code === code && group.id !== record?.id)) {
    errors.code = ['There is a group with this code already.']
  }

  if (name === '') errors.name = ['The Name field is required.']
  if (Object.keys(errors).length > 0) return { errors }

  if (record !== null) {
    for (const user of users)
      user.groups = user.groups.map((one) => (one === record.code ? code : one))
    record.code = code
    record.name = name

    return { record }
  }

  const created = {
    id: Math.max(0, ...userGroups.map((group) => group.id)) + 1,
    code,
    name,
    created_at: now(),
  }
  userGroups.push(created)

  return { record: created }
}

export function removeGroup(group: GroupRecord): void {
  userGroups.splice(userGroups.indexOf(group), 1)
  for (const user of users) user.groups = user.groups.filter((code) => code !== group.code)
}

export function usersSettings() {
  return {
    stored: true,
    values: { ...settingsValues },
    providers: providers.map((provider) => ({
      key: provider.key,
      label: provider.label,
      enabled: provider.enabled,
      saved: provider.saved,
      shown: shown(provider),
      missing: missing(provider),
      callback: `https://example.test/account/auth/${provider.key}/callback`,
      fields: provider.fields.map((field) => {
        const set = field.secret
          ? Boolean(provider.credentials[field.name])
          : Boolean(provider.options[field.name])

        return {
          ...field,
          kind: 'text',
          choices: [],
          default: null,
          value: field.secret ? null : (provider.options[field.name] ?? null),
          set,
          env: !set && Boolean(provider.env[field.name]),
        }
      }),
      only: users.filter(
        (user) =>
          !user.has_password &&
          user.identities.length === 1 &&
          user.identities[0]!.provider === provider.key,
      ).length,
    })),
    mail: { transport: 'log', reaches: true },
  }
}

export function saveUsersSettings(values: Record<string, unknown>) {
  for (const [key, value] of Object.entries(values)) {
    if (key === 'users.registration' || key.startsWith('users.fields.')) settingsValues[key] = value
  }

  return usersSettings()
}

export function saveProvider(key: string, body: Record<string, unknown>) {
  const provider = providers.find((one) => one.key === key)

  if (!provider) return null

  const options = (body.options ?? {}) as Record<string, unknown>
  const credentials = (body.credentials ?? {}) as Record<string, unknown>
  const forget = Array.isArray(body.forget) ? body.forget.map(String) : []

  for (const field of provider.fields) {
    if (field.secret) {
      if (forget.includes(field.name)) delete provider.credentials[field.name]
      else if (
        typeof credentials[field.name] === 'string' &&
        String(credentials[field.name]).trim() !== ''
      )
        provider.credentials[field.name] = true
    } else if (field.name in options) {
      const value = String(options[field.name] ?? '').trim()

      if (value === '') delete provider.options[field.name]
      else provider.options[field.name] = value
    }
  }

  if (typeof body.enabled === 'boolean') provider.enabled = body.enabled
  provider.saved = true

  return usersSettings()
}
