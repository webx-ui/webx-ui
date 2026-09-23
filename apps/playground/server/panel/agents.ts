import type { Admin, AgentCall, Connection, Role } from '../../../../packages/module-auth/src/types'
import { admins } from './inbox'

/**
 * The administrators section and the trail of what their agents did.
 *
 * The people are the same three the inbox assigns work to, so a name in the log is a name
 * elsewhere in the panel. The calls are what a fortnight of an agent helping with a site
 * looks like: mostly reads, a few writes, a dry run before the ones that matter, and the
 * refusals a person wants to see — a read-only connection asked to write, an editor's agent
 * reaching for the settings.
 */

export const roles: Role[] = [
  {
    id: 1,
    slug: 'editors',
    name: 'Редакторы',
    permissions: [
      'pages.view',
      'pages.manage',
      'blog.articles.view',
      'blog.articles.manage',
      'media.view',
      'media.upload',
    ],
    users: 1,
  },
  {
    id: 2,
    slug: 'auditors',
    name: 'Аудиторы',
    permissions: ['admins.view', 'admins.audit'],
    users: 1,
  },
]

export const adminRows: Admin[] = admins.map((person, index) => ({
  id: person.id,
  name: person.name,
  email: person.email,
  avatar: null,
  is_super: index === 0,
  is_active: true,
  locale: null,
  last_login_at: at(index * 7 + 1, 9, 12),
  created_at: '2026-06-01T08:00:00+03:00',
  roles: index === 1 ? [roles[0]] : index === 2 ? [roles[1]] : [],
}))

/** `days` days ago at the given hour, as the server would send it. */
function at(days: number, hour: number, minute: number): string {
  const when = new Date()
  when.setDate(when.getDate() - days)
  when.setHours(hour, minute, 0, 0)

  return when.toISOString()
}

function json(value: Record<string, unknown>): string {
  return JSON.stringify(value, null, 4)
}

interface Seed {
  user: number | null
  client: string | null
  tool: string
  arguments?: Record<string, unknown>
  dry_run?: boolean
  error?: string
  days: number
  hour: number
  minute: number
  duration: number
}

const anna = 1
const dmytro = 2
const olha = 3

const seeds: Seed[] = [
  // Today: Anna's Claude walks through the blog and drafts a post.
  {
    user: anna,
    client: 'Claude',
    tool: 'blog_list_articles',
    arguments: { limit: 20 },
    days: 0,
    hour: 9,
    minute: 2,
    duration: 38,
  },
  {
    user: anna,
    client: 'Claude',
    tool: 'blog_list_rubrics',
    days: 0,
    hour: 9,
    minute: 2,
    duration: 12,
  },
  {
    user: anna,
    client: 'Claude',
    tool: 'blog_create_article',
    arguments: { title: 'Что изменилось в осеннем релизе', rubric: 'news', dry_run: true },
    dry_run: true,
    days: 0,
    hour: 9,
    minute: 4,
    duration: 21,
  },
  {
    user: anna,
    client: 'Claude',
    tool: 'blog_create_article',
    arguments: { title: 'Что изменилось в осеннем релизе', rubric: 'news' },
    days: 0,
    hour: 9,
    minute: 5,
    duration: 143,
  },
  {
    user: anna,
    client: 'Claude',
    tool: 'blocks_edit_content',
    arguments: {
      entity: 'article',
      id: 41,
      revision: 3,
      operations: [{ op: 'set', key: 'intro', values: { text: 'Коротко о главном.' } }],
    },
    days: 0,
    hour: 9,
    minute: 7,
    duration: 96,
  },
  {
    user: anna,
    client: 'Claude',
    tool: 'seo_get_seo',
    arguments: { path: '/blog/autumn-release' },
    days: 0,
    hour: 9,
    minute: 8,
    duration: 17,
  },
  {
    user: anna,
    client: 'Claude',
    tool: 'seo_set_seo',
    arguments: {
      path: '/blog/autumn-release',
      title: 'Осенний релиз — что нового',
      description: 'Коротко о главном: ' + 'н'.repeat(60),
    },
    days: 0,
    hour: 9,
    minute: 9,
    duration: 54,
  },
  {
    user: anna,
    client: 'Claude',
    tool: 'pages_delete',
    arguments: { id: 12 },
    error: 'No such page.',
    days: 0,
    hour: 9,
    minute: 15,
    duration: 9,
  },
  {
    user: anna,
    client: 'Claude',
    tool: 'pages_delete',
    arguments: { id: 21, dry_run: true },
    dry_run: true,
    days: 0,
    hour: 9,
    minute: 16,
    duration: 11,
  },

  // Yesterday: Dmytro's Cursor, connected read-only, keeps asking to write.
  {
    user: dmytro,
    client: 'Cursor',
    tool: 'pages_list',
    days: 1,
    hour: 14,
    minute: 20,
    duration: 44,
  },
  {
    user: dmytro,
    client: 'Cursor',
    tool: 'pages_get',
    arguments: { id: 3 },
    days: 1,
    hour: 14,
    minute: 21,
    duration: 23,
  },
  {
    user: dmytro,
    client: 'Cursor',
    tool: 'pages_update',
    arguments: { id: 3, title: 'О компании' },
    error:
      '[pages_update] is not allowed on this connection. This connection was granted read-only access, and this tool changes something. Ask the person to connect the agent again without "read only" if they want it to write.',
    days: 1,
    hour: 14,
    minute: 22,
    duration: 3,
  },
  {
    user: dmytro,
    client: 'Cursor',
    tool: 'pages_update',
    arguments: { id: 3, title: 'О компании', dry_run: true },
    dry_run: true,
    error:
      '[pages_update] is not allowed on this connection. This connection was granted read-only access, and this tool changes something. Ask the person to connect the agent again without "read only" if they want it to write.',
    days: 1,
    hour: 14,
    minute: 23,
    duration: 2,
  },
  {
    user: dmytro,
    client: 'Cursor',
    tool: 'media_list_files',
    arguments: { directory: 'logos' },
    days: 1,
    hour: 14,
    minute: 30,
    duration: 61,
  },
  {
    user: dmytro,
    client: 'Cursor',
    tool: 'settings_get',
    arguments: { key: 'general.project-name' },
    error:
      'The administrator this call acts as does not hold the [settings.view] or [settings.manage] permission, which [settings_get] needs.',
    days: 1,
    hour: 14,
    minute: 31,
    duration: 2,
  },

  // Earlier: Olha's Claude reads the sign-in trail; a nightly script on the machine itself.
  {
    user: olha,
    client: 'Claude',
    tool: 'admins_recent_sign_ins',
    arguments: { limit: 50 },
    days: 2,
    hour: 11,
    minute: 5,
    duration: 29,
  },
  {
    user: olha,
    client: 'Claude',
    tool: 'admins_failed_sign_in_bursts',
    arguments: { hours: 24, threshold: 5 },
    days: 2,
    hour: 11,
    minute: 6,
    duration: 34,
  },
  {
    user: olha,
    client: 'Claude',
    tool: 'admins_grant_role',
    arguments: { email: 'dmytro@webx-demo.test', role: 'editors' },
    error:
      'The administrator this call acts as does not hold the [admins.manage] permission, which [admins_grant_role] needs.',
    days: 2,
    hour: 11,
    minute: 9,
    duration: 2,
  },
  {
    user: null,
    client: null,
    tool: 'media_find_unused',
    arguments: { older_than_days: 90 },
    days: 3,
    hour: 3,
    minute: 0,
    duration: 812,
  },
  { user: null, client: null, tool: 'pages_list', days: 3, hour: 3, minute: 0, duration: 51 },
  {
    user: anna,
    client: 'Claude',
    tool: 'inbox_list_submissions',
    arguments: { status: 'new' },
    days: 4,
    hour: 17,
    minute: 40,
    duration: 47,
  },
  {
    user: anna,
    client: 'Claude',
    tool: 'inbox_update_submission',
    arguments: { id: 118, status: 'in-progress', comment: 'Перезвонить во вторник' },
    days: 4,
    hour: 17,
    minute: 42,
    duration: 58,
  },
  {
    user: anna,
    client: 'Claude',
    tool: 'media_upload_from_url',
    arguments: { url: 'https://example.test/hero.jpg', directory: 'blog', api_key: '[redacted]' },
    error: 'The file at that address is not an image.',
    days: 5,
    hour: 12,
    minute: 12,
    duration: 1470,
  },
]

let nextId = 1

export const agentCalls: AgentCall[] = seeds.map((seed) => ({
  id: nextId++,
  at: at(seed.days, seed.hour, seed.minute),
  user:
    seed.user === null
      ? null
      : { id: seed.user, name: admins.find((one) => one.id === seed.user)?.name ?? null },
  client: seed.client,
  tool: seed.tool,
  arguments: seed.arguments ? json(seed.arguments) : null,
  dry_run: seed.dry_run ?? false,
  ok: seed.error === undefined,
  error: seed.error ?? null,
  duration_ms: seed.duration,
}))

/** One row a deleted administrator left behind — the id outlives the person. */
agentCalls.push({
  id: nextId++,
  at: at(9, 16, 45),
  user: { id: 7, name: null },
  client: 'Claude',
  tool: 'pages_list',
  arguments: null,
  dry_run: false,
  ok: true,
  error: null,
  duration_ms: 40,
})

/** `GET /auth/mcp-calls`: newest first, narrowed the way the server narrows. */
export function listCalls(query: URLSearchParams): {
  data: AgentCall[]
  meta: Record<string, number | null>
  filters: { users: { id: number | null; name: string | null }[]; tools: string[] }
} {
  const user = query.get('user')
  const tool = query.get('tool')
  const outcome = query.get('outcome')
  const page = Math.max(1, Number(query.get('page') ?? 1))
  const perPage = Math.min(100, Math.max(1, Number(query.get('per_page') ?? 30)))

  let found = [...agentCalls].sort((one, two) => String(two.at).localeCompare(String(one.at)))

  if (user === 'none') found = found.filter((call) => call.user === null)
  else if (user) found = found.filter((call) => call.user?.id === Number(user))

  if (tool) found = found.filter((call) => call.tool === tool)

  if (outcome === 'ok') found = found.filter((call) => call.ok && !call.dry_run)
  if (outcome === 'failed') found = found.filter((call) => !call.ok)
  if (outcome === 'dry') found = found.filter((call) => call.dry_run)

  const total = found.length
  const from = (page - 1) * perPage
  const rows = found.slice(from, from + perPage)

  const seen = new Map<string, { id: number | null; name: string | null }>()
  for (const call of agentCalls) {
    seen.set(
      call.user === null ? 'none' : String(call.user.id),
      call.user ?? { id: null, name: null },
    )
  }

  const users = [...seen.values()].sort((one, two) => {
    if (one.id === null || two.id === null) return one.id === null ? 1 : -1
    if (one.name === null || two.name === null) return one.name === null ? 1 : -1

    return one.name.localeCompare(two.name)
  })

  return {
    data: rows,
    meta: {
      current_page: page,
      last_page: Math.max(1, Math.ceil(total / perPage)),
      per_page: perPage,
      total,
      from: total === 0 ? null : from + 1,
      to: total === 0 ? null : from + rows.length,
    },
    filters: { users, tools: [...new Set(agentCalls.map((call) => call.tool))].sort() },
  }
}

/**
 * The agents these three let in.
 *
 * One each, and one that was ended, because that is what the list is read for: Anna's Claude
 * with everything she can do, Dmytro's Cursor kept to reading — which is why half his calls
 * above are refusals — and Olha's Codex, disconnected a week ago and still in the log.
 */
export const connections: Connection[] = [
  {
    id: 1,
    client: 'Claude',
    host: 'claude.ai',
    read_only: false,
    user: { id: anna, name: admins.find((one) => one.id === anna)?.name ?? null },
    connected_at: at(14, 11, 30),
    last_used_at: at(0, 9, 16),
    revoked_at: null,
  },
  {
    id: 2,
    client: 'Cursor',
    host: 'cursor.sh',
    read_only: true,
    user: { id: dmytro, name: admins.find((one) => one.id === dmytro)?.name ?? null },
    connected_at: at(6, 18, 5),
    last_used_at: at(1, 14, 31),
    revoked_at: null,
  },
  {
    id: 3,
    client: 'Codex',
    host: 'chatgpt.com',
    read_only: false,
    user: { id: olha, name: admins.find((one) => one.id === olha)?.name ?? null },
    connected_at: at(30, 10, 0),
    last_used_at: at(9, 16, 45),
    revoked_at: at(7, 12, 0),
  },
]

/** `GET /auth/connections`: this person's, or everybody's for whoever may see them. */
export function listConnections(query: URLSearchParams): {
  data: Connection[]
  meta: { scope: 'mine' | 'all'; can_see_everybody: boolean }
} {
  const all = query.get('all') === '1'

  return {
    // The playground signs everybody in as Anna, and she is a super administrator — so both
    // lists are reachable here, and `mine` is hers.
    data: all ? connections : connections.filter((one) => one.user.id === anna),
    meta: { scope: all ? 'all' : 'mine', can_see_everybody: true },
  }
}

/** `DELETE /auth/connections/{id}`: the row stays, the connection ends. */
export function endConnection(id: number): Connection {
  const found = connections.find((one) => one.id === id)

  if (found === undefined) return connections[0]

  found.revoked_at = new Date().toISOString()

  return found
}
