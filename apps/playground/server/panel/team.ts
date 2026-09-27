import { fileByPath } from './media'
import { find as findService, row as serviceRow } from './services'

/**
 * The team (`module-team`): people, their one order, and the collection source blocks show them
 * through (`wx-collection`).
 *
 * The panel half answers the way §5.7 of the module's spec fixes it — the list whole and without
 * pages or filters, the form's values by field name, one order dragged (decision 5). The site
 * half is `resolveTeam()`: what `TeamSource` answers on a site — everybody published and not in
 * the bin, in any language (decision 8), narrowed by a relation to services, the limit last — as
 * cards of §5.2.
 */

type Localized = Record<string, string>

export interface SocialRow {
  network: string
  url: string
}

export interface Member {
  id: number
  name: Localized
  job_title: Localized
  /** Plain text, paragraphs by line breaks. Empty in a language — shown there without it. */
  text: Localized
  /** The value of `wx-media`: a key in the library, never an address. */
  photo: { path: string } | null
  /** In the order of the form; a network the config dropped stays here and leaves the cards. */
  socials: SocialRow[]
  /** The value of `wx-relations` with `target: service` — ids in the order chosen. */
  services: number[]
  published: boolean
  updated_at: string
  deleted_at: string | null
  extra: Record<string, unknown>
}

const STAMP = '2026-09-27T09:00:00+00:00'
/** The language a name falls back on (decision 8), and the one it is required in (decision 9). */
const DEFAULT = 'ru'

/**
 * `webx-team.networks` of this demo site: the seven of the package plus one the site added in its
 * own config — which the block has no mark for, so it prints the name. `vk` is not here: a person
 * below links to it and loses the link on the site, but not in the database (§5.2).
 */
export const NETWORKS: Record<string, string> = {
  facebook: 'Facebook',
  instagram: 'Instagram',
  linkedin: 'LinkedIn',
  x: 'X',
  telegram: 'Telegram',
  youtube: 'YouTube',
  tiktok: 'TikTok',
  behance: 'Behance',
}

/** In the one order (`position`). */
export const team: Member[] = [
  member(1, {
    name: { ru: 'Анна Петрова', en: 'Anna Petrova' },
    job_title: { ru: 'Руководитель студии', en: 'Head of studio' },
    text: {
      ru: 'Двенадцать лет в веб-разработке. Отвечает за то, чтобы сайт был готов в срок и делал то, ради чего его заказали.',
      en: 'Twelve years in web development. Makes sure the site is ready on time and does what it was ordered for.',
    },
    photo: { path: 'reviews/anna-petrova.svg' },
    socials: [
      { network: 'linkedin', url: 'https://www.linkedin.com/in/example' },
      { network: 'telegram', url: 'https://t.me/example' },
    ],
    services: [2, 3],
  }),
  member(2, {
    name: { ru: 'Игорь Мельник', en: 'Ihor Melnyk' },
    job_title: { ru: 'Ведущий разработчик', en: 'Lead developer' },
    text: {
      ru: 'Пишет то, что не видно: панель, импорт, интеграции.\nЛюбит, когда сайт открывается быстрее секунды.',
      en: 'Writes what nobody sees: the panel, imports, integrations.\nLikes a site that opens in under a second.',
    },
    photo: { path: 'reviews/ihor-melnyk.svg' },
    socials: [
      { network: 'x', url: 'https://x.com/example' },
      { network: 'youtube', url: 'https://www.youtube.com/@example' },
      // Not in the config: dropped from the cards, kept here (§5.2).
      { network: 'vk', url: 'https://vk.com/example' },
    ],
    services: [1, 2, 3, 4],
  }),
  /* No text in English: shown on the English site without it (decision 8). */
  member(3, {
    name: { ru: 'Ольга Сидоренко', en: 'Olha Sydorenko' },
    job_title: { ru: 'Дизайнер', en: 'Designer' },
    text: { ru: 'Рисует интерфейсы и фирменные стили. До студии — пять лет в агентстве.', en: '' },
    socials: [
      { network: 'instagram', url: 'https://instagram.com/example' },
      { network: 'behance', url: 'https://www.behance.net/example' },
    ],
    services: [5, 6],
  }),
  member(4, {
    name: { ru: 'Марко Росси', en: 'Marco Rossi' },
    job_title: { ru: 'SEO-специалист', en: 'SEO specialist' },
    text: {
      ru: 'Смотрит на сайт глазами поисковика и объясняет, что с этим делать.',
      en: 'Looks at a site the way a search engine does, and explains what to do about it.',
    },
    services: [7, 4],
  }),
  /* A draft: never shown, whatever is chosen. */
  member(5, {
    name: { ru: 'Дмитрий Лысенко', en: 'Dmytro Lysenko' },
    job_title: { ru: 'Стажёр', en: 'Intern' },
    text: { ru: 'Выходит в октябре.', en: '' },
    published: false,
    services: [2],
  }),
  /* A name in Russian only: the English site prints the Russian one (decision 8). */
  member(6, {
    name: { ru: 'Татьяна Ковальчук', en: '' },
    job_title: { ru: 'Редактор', en: 'Editor' },
    text: {
      ru: 'Переписывает тексты так, чтобы их дочитывали.',
      en: 'Rewrites texts so that people read them to the end.',
    },
    services: [8],
  }),
]

function member(
  id: number,
  seed: Partial<Omit<Member, 'id' | 'name' | 'job_title' | 'text'>> &
    Pick<Member, 'name' | 'job_title' | 'text'>,
): Member {
  return {
    photo: null,
    socials: [],
    services: [],
    published: true,
    updated_at: STAMP,
    deleted_at: null,
    extra: {},
    ...seed,
    id,
  }
}

/* ------------------------------------------------------------------------------ panel ----- */

const live = (one: { deleted_at: string | null }): boolean => one.deleted_at === null

/** The options of the network select, patched onto `team.form` from the config (§5.4). */
export function networkOptions(): { value: string; label: string }[] {
  return Object.entries(NETWORKS).map(([value, label]) => ({ value, label }))
}

/** The photo as the list draws it: the thumbnail, found in the library by its key. */
function photoOf(record: Member): { thumb: string | null } | null {
  const file = record.photo === null ? null : fileByPath(record.photo.path)

  return file === null ? null : { thumb: file.thumb }
}

/** One person as the list line draws them (§5.7). */
export function memberRow(record: Member, locale: string): Record<string, unknown> {
  const name = pick(record.name, locale) || `#${record.id}`

  return {
    id: record.id,
    name,
    job_title: pick(record.job_title, locale) || null,
    initials: initials(pick(record.name, locale)),
    photo: photoOf(record),
    published: record.published,
    position: team.indexOf(record) + 1,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
  }
}

/** `GET /team`: the whole list, or the bin; a search narrows either. */
export function listTeam(query: Record<string, string>, locale: string): Record<string, unknown> {
  let found = query.trashed === '1' ? team.filter((one) => !live(one)) : team.filter(live)

  if (query.trashed === '1') {
    found = found.sort((a, b) => String(b.deleted_at).localeCompare(String(a.deleted_at)))
  }

  const term = (query.search ?? '').trim().toLowerCase()

  if (term !== '') {
    found = found.filter((one) =>
      [one.name, one.job_title, one.text].some((map) =>
        Object.values(map).some((words) => words.toLowerCase().includes(term)),
      ),
    )
  }

  return { data: found.map((one) => memberRow(one, locale)) }
}

/** One person as their form opens them (`MemberForm::describe()`). */
export function memberDetail(record: Member, locale: string): Record<string, unknown> {
  return {
    member: {
      id: record.id,
      name: pick(record.name, locale) || `#${record.id}`,
      published: record.published,
      deleted_at: record.deleted_at,
    },
    values: {
      ...record.extra,
      name: { ...record.name },
      job_title: { ...record.job_title },
      text: { ...record.text },
      photo: record.photo === null ? null : { ...record.photo },
      socials: record.socials.map((one) => ({ ...one })),
      services: [...record.services],
      published: record.published,
    },
  }
}

export function findMember(id: number): Member | null {
  return team.find((one) => one.id === id) ?? null
}

/**
 * The values of `team.form`, checked and written — a new person when `record` is `null`. What is
 * refused comes back under the name of the field, as a 422 would carry it — a link under
 * `socials.<n>.<field>` — and nothing is written then: not even the new row (the server does it
 * in a transaction).
 */
export function writeMember(
  record: Member | null,
  values: Record<string, unknown>,
): { record: Member } | { errors: Record<string, string[]> } {
  const errors: Record<string, string[]> = {}

  // The one required field (decision 9): a card with no name is a card nobody can be told from.
  if (record === null || values.name !== undefined) {
    const name = asMap(values.name ?? record?.name)

    if (said(name[DEFAULT]) === '') {
      errors[`name.${DEFAULT}`] = ['Имя нужно на языке по умолчанию.']
    }

    for (const [code, words] of Object.entries(name)) {
      if (words.length > 255) errors[`name.${code}`] = ['Не длиннее 255 символов.']
    }
  }

  let socials: SocialRow[] | undefined

  if (values.socials !== undefined) {
    socials = []

    asRows(values.socials).forEach((row, index) => {
      const network = typeof row.network === 'string' ? row.network.trim() : ''
      const url = typeof row.url === 'string' ? row.url.trim() : ''
      const at = `socials.${index}`

      // A row with neither is a row somebody added and left: dropped, not refused (§5.4).
      if (network === '' && url === '') return

      // A link the person already has to a network the config dropped goes back as it came, as
      // on the server (`MemberForm::socials()`): the form sends back what it opened with.
      const kept = record?.socials.some((one) => one.network === network && one.url === url)

      if (!(network in NETWORKS) && kept) {
        socials!.push({ network, url })
        return
      }

      if (network === '') errors[`${at}.network`] = ['Выберите сеть.']
      else if (!(network in NETWORKS)) errors[`${at}.network`] = ['Такой сети нет в списке сайта.']

      if (url === '') errors[`${at}.url`] = ['Нужен адрес.']
      else if (!/^https?:\/\/\S+$/i.test(url)) {
        errors[`${at}.url`] = ['Адрес должен начинаться с http:// или https://.']
      }

      socials!.push({ network, url })
    })
  }

  let chosen: number[] | undefined

  if (values.services !== undefined) {
    chosen = [...new Set(asIds(values.services))]

    if (chosen.some((id) => findService(id) === null)) {
      errors.services = ['Одной из этих услуг больше нет.']
    }
  }

  if (Object.keys(errors).length > 0) return { errors }

  const target =
    record ??
    member(Math.max(0, ...team.map((one) => one.id)) + 1, {
      name: {},
      job_title: {},
      text: {},
      published: false,
    })

  for (const [name, value] of Object.entries(values)) {
    if (name === 'name') target.name = asMap(value)
    else if (name === 'job_title') target.job_title = asMap(value)
    else if (name === 'text') target.text = asMap(value)
    else if (name === 'photo') target.photo = media(value)
    else if (name === 'published') target.published = value === true
    else if (name !== 'socials' && name !== 'services') target.extra[name] = value
  }

  if (socials !== undefined) target.socials = socials
  if (chosen !== undefined) target.services = chosen

  // A new person goes to the end of the order (`max + 1`, §5.1).
  if (record === null) team.push(target)

  target.updated_at = new Date().toISOString()

  return { record: target }
}

/** The order on screen, written whole — the only order there is (decision 5). */
export function reorderTeam(ids: number[]): void {
  const moved = ids.map((id) => findMember(id)).filter((one): one is Member => !!one)
  const rest = team.filter((one) => !ids.includes(one.id))

  team.splice(0, team.length, ...moved, ...rest)
}

/* ------------------------------------------------------------------------------- site ----- */

/**
 * A stored choice, read the way the site reads it (`TeamSource::items()`), as cards of §5.2.
 * `entity` is the record whose page the block is on: "related to the current one" is answered
 * against it, and without one (a block drawn on its sample) that filter narrows nothing.
 */
export function resolveTeam(
  stored: unknown,
  locale = 'ru',
  entity: { type: string; id: number } | null = null,
): Record<string, unknown> {
  const value = (typeof stored === 'object' && stored !== null ? stored : {}) as Record<
    string,
    unknown
  >
  const limit = typeof value.limit === 'number' && value.limit >= 1 ? value.limit : null
  const related = value.related as
    { type?: string; ids?: unknown; current?: unknown } | null | undefined

  let wanted: number[] | null = null

  if (related?.type === 'service') {
    if (related.current === true) {
      wanted = entity?.type === 'service' ? [entity.id] : null
    } else {
      // An empty list with a type is "related to nothing": nobody (§4.2 of the spec).
      wanted = asIds(related.ids)
    }
  }

  const items = team
    .filter((record) => live(record) && record.published)
    .filter((record) => wanted === null || record.services.some((id) => wanted!.includes(id)))
    .slice(0, limit ?? undefined)
    .map((record) => card(record, locale))

  return { items, groups: [], filter: false }
}

/** A person as a template reads them — `Rendering\Cards`, §5.2. */
function card(record: Member, locale: string) {
  const file = record.photo === null ? null : fileByPath(record.photo.path)
  const name = said(record.name[locale]) || said(record.name[DEFAULT])

  return {
    id: record.id,
    anchor: `member-${record.id}`,
    categories: [],
    name,
    initials: initials(name),
    job_title: said(record.job_title[locale]) || said(record.job_title[DEFAULT]),
    // Only in the language of the page (decision 8): a Russian paragraph on the English site
    // would be worse than none.
    text: said(record.text[locale]),
    photo:
      file === null
        ? null
        : { url: file.url, thumb: file.thumb, width: file.width, height: file.height, alt: name },
    socials: record.socials
      .filter((one) => one.network in NETWORKS)
      .map((one) => ({ network: one.network, label: NETWORKS[one.network]!, url: one.url })),
    service_links: record.services.flatMap((id) => {
      const found = findService(id)

      // What the site shows of a service is its published page; a draft or the bin is no link.
      if (found === null || found.deleted_at !== null || found.live === null) return []

      const drawn = serviceRow(found, locale)

      return drawn.path === null ? [] : [{ id, title: drawn.title, url: `/${drawn.path}` }]
    }),
    fields: { ...record.extra },
  }
}

/* ---------------------------------------------------------------------------- helpers ----- */

/** The language asked for, or the default one where it is empty — the panel names what it can. */
function pick(value: Localized, locale: string): string {
  return said(value[locale]) || said(value[DEFAULT]) || said(value.en)
}

function said(value: string | undefined): string {
  return (value ?? '').trim()
}

/** The first letters of the first two words, by letters and not by bytes (as a review's). */
function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter((word) => word !== '')
    .slice(0, 2)
    .map((word) => Array.from(word)[0]!.toUpperCase())
    .join('')
}

function asMap(value: unknown): Localized {
  if (typeof value === 'string') return { [DEFAULT]: value }
  if (value === null || typeof value !== 'object') return {}

  const out: Localized = {}

  for (const [code, words] of Object.entries(value as Record<string, unknown>)) {
    out[code] = typeof words === 'string' ? words : ''
  }

  return out
}

function asRows(value: unknown): Record<string, unknown>[] {
  return Array.isArray(value)
    ? value.filter((row): row is Record<string, unknown> => typeof row === 'object' && row !== null)
    : []
}

function asIds(value: unknown): number[] {
  return Array.isArray(value)
    ? value.map(Number).filter((id) => Number.isInteger(id) && id > 0)
    : []
}

function media(value: unknown): { path: string } | null {
  const path = (value as { path?: unknown } | null)?.path

  return typeof path === 'string' && path !== '' ? { path } : null
}
