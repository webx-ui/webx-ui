import type { LocalizedValue } from '../../../../packages/core/src/composables/useLocalized'
import type {
  EventRow,
  EventStatus,
  EventTermRef,
  EventVersion,
} from '../../../../packages/module-events/src/types'
import type { ScreenModel } from '../../../../packages/schema/src/types'
import { fileByPath } from './media'
import { find as findService, row as serviceRow, services } from './services'

/**
 * The events the playground edits, answering in the shapes of §4.10 of the events spec: a page
 * of events split into the ones ahead and the ones behind, their drafts, and the categories they
 * are filed under, with pages of their own.
 *
 * The dates are the point of this fixture. The "server" keeps every moment in its own zone —
 * Hong Kong, +08:00, as omnivitality does — and answers with the offset (`toAtomString()`), so a
 * browser in any other zone has to show the hour it picked and get the same hour back after a
 * save and a reload. Every seeded date is counted from the moment the server started, or the demo
 * would drift into the past by itself.
 *
 * Like the recipes, everything an event is filed under waits in the draft and reaches the site on
 * "Publish"; only the SEO card is written as it is saved.
 */

/** The first segment of every event address, the categories' included (§4.3). */
export const PREFIX = 'events'

/** The zone of the application — what `config('app.timezone')` would be. Not UTC on purpose. */
const ZONE = 'Asia/Hong_Kong'
const OFFSET_MINUTES = 8 * 60

export interface EventRecord {
  id: number
  /** What the editor opens: the draft laid over what was last published. */
  values: ScreenModel
  /** What the site is showing, or `null` for an event that has never been on it. */
  live: ScreenModel | null
  status: EventStatus
  published_at: string | null
  updated_at: string
  deleted_at: string | null
  versions: EventVersion[]
  snapshots: Record<number, ScreenModel>
}

export interface TermRecord {
  id: number
  name: string
  title: LocalizedValue
  slug: LocalizedValue
  path: string
  url: string
  is_visible: boolean
  position: number
  deleted_at: string | null
  lead: LocalizedValue
  cover: Record<string, unknown> | null
  seo: Record<string, unknown>
  extra: Record<string, unknown>
}

export const events: EventRecord[] = []
export const eventCategories: TermRecord[] = []

/** Written as it is saved rather than kept for the publication. */
const UNDRAFTED = ['seo']

/* ------------------------------------------------------------------------------ moments ----- */

/**
 * A moment as the server answers it: in its own zone, with the offset, to the second —
 * `2026-10-12T10:00:00+08:00`, never `Z` and never without an offset.
 */
export function atom(at: Date): string {
  const shifted = new Date(at.getTime() + OFFSET_MINUTES * 60_000)
  const pad = (value: number) => String(value).padStart(2, '0')

  return (
    `${shifted.getUTCFullYear()}-${pad(shifted.getUTCMonth() + 1)}-${pad(shifted.getUTCDate())}` +
    `T${pad(shifted.getUTCHours())}:${pad(shifted.getUTCMinutes())}:${pad(shifted.getUTCSeconds())}` +
    '+08:00'
  )
}

/**
 * What arrived, as a moment in the server's zone — with any offset, or none, which is read in the
 * server's zone the way Carbon reads it. `undefined` for something that is not a date at all.
 */
function moment(value: unknown): string | null | undefined {
  if (value === null || value === '' || value === undefined) return null
  if (typeof value !== 'string') return undefined

  const zoned = /(Z|[+-]\d{2}:?\d{2})$/.test(value) ? value : `${value.replace(' ', 'T')}+08:00`
  const at = new Date(zoned)

  return Number.isNaN(at.getTime()) ? undefined : atom(at)
}

/** Midnight-based day arithmetic in the server's zone, for the seeds. */
function daysFromNow(days: number, hour: number, minute = 0): string {
  const now = new Date()
  const local = new Date(now.getTime() + OFFSET_MINUTES * 60_000)

  local.setUTCDate(local.getUTCDate() + days)
  local.setUTCHours(hour, minute, 0, 0)

  return atom(new Date(local.getTime() - OFFSET_MINUTES * 60_000))
}

const time = (value: unknown): number | null =>
  typeof value === 'string' && value !== '' ? Date.parse(value) : null

/** Decision 7: the end, or the start when there is no end, is behind us. No date — never past. */
export function isPast(values: ScreenModel, now = Date.now()): boolean {
  const end = time(values.ends_at) ?? time(values.starts_at)

  return end !== null && end < now
}

/**
 * The date as the site prints it (§4.6), in the server's zone: `date_note` over everything,
 * nothing without a date, one day with its hours, several days as a range.
 */
export function when(values: ScreenModel, locale: string): string {
  const note = text(values.date_note, locale)

  if (note !== '') return note

  const start = time(values.starts_at)

  if (start === null) return ''

  const end = time(values.ends_at)
  const allDay = values.all_day === true
  const lang = locale === 'ru' ? 'ru-RU' : 'en-GB'
  const day = (at: number, withYear = true) =>
    new Intl.DateTimeFormat(lang, {
      day: 'numeric',
      month: 'long',
      ...(withYear ? { year: 'numeric' } : {}),
      timeZone: ZONE,
    })
      .format(at)
      .replace(/\s?г\.$/, '')
  const clock = (at: number) =>
    new Intl.DateTimeFormat('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
      timeZone: ZONE,
    }).format(at)
  const ymd = (at: number) => new Intl.DateTimeFormat('en-CA', { timeZone: ZONE }).format(at)

  if (end === null || ymd(end) === ymd(start)) {
    if (allDay) return day(start)

    return end === null
      ? `${day(start)}, ${clock(start)}`
      : `${day(start)}, ${clock(start)}–${clock(end)}`
  }

  const sameMonth = ymd(start).slice(0, 7) === ymd(end).slice(0, 7)

  if (sameMonth) {
    const first = new Intl.DateTimeFormat(lang, { day: 'numeric', timeZone: ZONE }).format(start)

    return `${first}–${day(end)}`
  }

  return `${day(start, ymd(start).slice(0, 4) !== ymd(end).slice(0, 4))} – ${day(end)}`
}

/* ------------------------------------------------------------------------------ terms ----- */

function term(title: [string, string], slug: string, lead = ''): void {
  const id = eventCategories.length + 1

  eventCategories.push({
    id,
    name: title[0],
    title: { ru: title[0], en: title[1] },
    slug: { ru: slug, en: slug },
    path: `${PREFIX}/${slug}`,
    url: `https://webx-demo.test/${PREFIX}/${slug}`,
    is_visible: true,
    position: id,
    deleted_at: null,
    lead: { ru: lead, en: '' },
    cover: null,
    seo: {},
    extra: {},
  })
}

/* Omnivitality's formats (§4.12): each one a category with a page of its own. */
term(
  ['Завтраки-встречи', 'Breakfast meetings'],
  'breakfast-meetings',
  '<p>Утро за общим столом.</p>',
)
term(['Кулинарные мастер-классы', 'Cooking classes'], 'cooking-classes', '<p>Готовим вместе.</p>')
term(['Частные события', 'Private events'], 'private-events')

/* ---------------------------------------------------------------------------- events ----- */

interface Seed {
  title: [string, string]
  slug: string
  lead: [string, string]
  starts_at: string | null
  ends_at?: string | null
  all_day?: boolean
  date_note?: [string, string]
  attendance?: 'offline' | 'online' | 'mixed'
  venue?: [string, string]
  address?: [string, string]
  map_url?: string
  gallery?: string[]
  description?: string
  highlights?: { title: [string, string]; text: [string, string] }[]
  price?: [string, string]
  price_amount?: number | null
  booking_url?: string
  categories: number[]
  services?: number[]
  status?: EventStatus
}

const pair = (value: [string, string] | undefined): LocalizedValue =>
  value === undefined ? {} : { ru: value[0], en: value[1] }

function event(seed: Seed): EventRecord {
  const id = events.length + 1
  const status = seed.status ?? 'published'

  const values: ScreenModel = {
    title: pair(seed.title),
    slug: { ru: seed.slug, en: seed.slug },
    lead: pair(seed.lead),
    gallery: (seed.gallery ?? []).map((path) => ({ path, alt: pair(seed.title) })),
    starts_at: seed.starts_at,
    ends_at: seed.ends_at ?? null,
    all_day: seed.all_day ?? false,
    date_note: pair(seed.date_note),
    attendance: seed.attendance ?? 'offline',
    venue: pair(seed.venue),
    address: pair(seed.address),
    map_url: seed.map_url ?? null,
    description: { ru: seed.description ?? '', en: '' },
    highlights: (seed.highlights ?? []).map((one) => ({
      title: pair(one.title),
      text: pair(one.text),
    })),
    price: pair(seed.price),
    price_amount: seed.price_amount ?? null,
    booking_url: seed.booking_url ?? null,
    categories: seed.categories,
    services: seed.services ?? [],
    seo: {},
  }

  const published = status === 'draft' ? null : daysFromNow(-14, 9)
  const record: EventRecord = {
    id,
    values,
    live: status === 'draft' ? null : clone(values),
    status,
    published_at: published,
    updated_at: daysFromNow(-3, 10),
    deleted_at: null,
    versions: [],
    snapshots: {},
  }

  if (record.live !== null) {
    record.versions.push({
      number: 1,
      created_at: published,
      author: 'Анна Ковальчук',
      source: 'panel',
      comment: 'Первая публикация',
      is_pinned: false,
    })
    record.snapshots[1] = clone(values)
  }

  events.push(record)

  return record
}

const STUDIO: Pick<Seed, 'venue' | 'address' | 'map_url'> = {
  venue: ['Студийная кухня', 'Studio Kitchen'],
  address: ['Гонконг, Веллингтон-стрит, 88', '88 Wellington Street, Hong Kong'],
  map_url: 'https://maps.example.test/studio-kitchen',
}

/* A week from now, with its hours, a price as a number and everything filled: the one to open. */
event({
  title: ['Весенний кулинарный мастер-класс', 'Spring cooking class'],
  slug: 'spring-cooking-class',
  lead: [
    'Три сезонных блюда за один вечер — готовим, пробуем, уносим рецепты.',
    'Three seasonal dishes in one evening: cook, taste, take the recipes home.',
  ],
  starts_at: daysFromNow(7, 18),
  ends_at: daysFromNow(7, 20, 30),
  ...STUDIO,
  gallery: ['events/cooking-class.svg', 'events/report-1.svg'],
  description:
    '<p>Мы начнём с ножей и закончим десертом. Всё, что понадобится, будет на месте.</p>',
  highlights: [
    {
      title: ['Три блюда', 'Three dishes'],
      text: ['От закуски до десерта.', 'From a starter to a dessert.'],
    },
    {
      title: ['Рецепты с собой', 'Recipes to take home'],
      text: ['Каждый уходит с карточками рецептов.', 'Everyone leaves with the recipe cards.'],
    },
  ],
  price: ['HK$480 с человека', 'HK$480 per person'],
  price_amount: 480,
  booking_url: 'https://booking.example.test/spring-class',
  categories: [2],
  services: [1],
})

/* Three days, all day, in two categories. */
event({
  title: ['Интенсив по ферментации', 'Fermentation intensive'],
  slug: 'fermentation-intensive',
  lead: ['Три дня квашения, заквасок и терпения.', 'Three days of pickles, starters and patience.'],
  starts_at: daysFromNow(20, 0),
  ends_at: daysFromNow(22, 0),
  all_day: true,
  ...STUDIO,
  price: ['HK$2 400 за три дня', 'HK$2,400 for three days'],
  price_amount: 2400,
  categories: [2, 3],
})

/* Online: no place, and free. */
event({
  title: ['Вебинар: завтраки без сахара', 'Webinar: breakfasts without sugar'],
  slug: 'sugar-free-breakfasts-webinar',
  lead: ['Час разговора и ответы на вопросы.', 'An hour of talk and questions answered.'],
  starts_at: daysFromNow(3, 12),
  ends_at: daysFromNow(3, 13),
  attendance: 'online',
  gallery: ['events/webinar.svg'],
  price: ['Бесплатно', 'Free'],
  price_amount: 0,
  booking_url: 'https://booking.example.test/webinar',
  categories: [1],
})

/* No date — "every Saturday" in words: first among the upcoming, never past (decision 3). */
event({
  title: ['Субботний завтрак', 'Saturday breakfast'],
  slug: 'saturday-breakfast',
  lead: ['Каждую субботу за длинным столом.', 'Every Saturday at the long table.'],
  starts_at: null,
  date_note: ['Каждую субботу, 9:00', 'Every Saturday, 9:00'],
  ...STUDIO,
  gallery: ['events/breakfast.svg'],
  price: ['HK$180', 'HK$180'],
  categories: [1],
})

/* Over, with its photo report: a page of the site still, with no booking button. */
event({
  title: ['Летний ужин на крыше', 'Summer rooftop dinner'],
  slug: 'summer-rooftop-dinner',
  lead: [
    'Как это было: гости, стол и закат.',
    'How it went: the guests, the table and the sunset.',
  ],
  starts_at: daysFromNow(-30, 19),
  ends_at: daysFromNow(-30, 23),
  attendance: 'mixed',
  ...STUDIO,
  gallery: ['events/report-1.svg', 'events/report-2.svg', 'events/cooking-class.svg'],
  price: ['HK$900', 'HK$900'],
  price_amount: 900,
  categories: [3],
  services: [2],
})

/* Never published. */
event({
  title: ['Осенний мастер-класс по выпечке', 'Autumn baking class'],
  slug: 'autumn-baking-class',
  lead: ['Хлеб на закваске и пироги.', 'Sourdough bread and pies.'],
  starts_at: daysFromNow(40, 11),
  ends_at: daysFromNow(40, 15),
  ...STUDIO,
  categories: [2],
  status: 'draft',
})

/*
 * Enough past breakfasts to fill more than one page of the "Past" tab — the list pages by twenty
 * — one a week going back in time. Plain, because nobody opens these to look at them.
 */
for (let week = 1; week <= 22; week += 1) {
  event({
    title: [`Завтрак-встреча №${40 - week}`, `Breakfast meeting #${40 - week}`],
    slug: `breakfast-meeting-${40 - week}`,
    lead: ['Утро за общим столом.', 'A morning at the shared table.'],
    starts_at: daysFromNow(-7 * week - 2, 9),
    ends_at: daysFromNow(-7 * week - 2, 11),
    ...STUDIO,
    categories: [1],
  })
}

/* In the bin: the list can bring it back. */
const cancelled = event({
  title: ['Отменённая дегустация', 'Cancelled tasting'],
  slug: 'cancelled-tasting',
  lead: ['', ''],
  starts_at: daysFromNow(12, 18),
  categories: [3],
})

cancelled.deleted_at = daysFromNow(-1, 15)

/* ---------------------------------------------------------------------------- reading ----- */

export function find(id: number): EventRecord | null {
  return events.find((record) => record.id === id) ?? null
}

export function findTerm(id: number): TermRecord | null {
  return eventCategories.find((row) => row.id === id) ?? null
}

export function ids(value: unknown): number[] {
  return Array.isArray(value)
    ? value.map(Number).filter((one) => Number.isInteger(one) && one > 0)
    : []
}

/** One event as a screen reads it (§4.10) — per request, in the language the panel is open in. */
export function row(record: EventRecord, locale: string): EventRow {
  const live = record.live === null ? '' : text(record.live.slug, locale)
  const path = live === '' ? null : `${PREFIX}/${live}`
  const values = record.values

  return {
    id: record.id,
    title: text(values.title, locale) || text(values.slug, locale) || `#${record.id}`,
    slug: text(values.slug, locale),
    path,
    url: path === null ? null : `https://webx-demo.test/${path}`,
    cover: cover(values),
    starts_at: (values.starts_at as string | null) ?? null,
    ends_at: (values.ends_at as string | null) ?? null,
    all_day: values.all_day === true,
    when: when(values, locale),
    past: isPast(values),
    status: record.status,
    categories: named(ids(values.categories), locale),
    published_at: record.published_at,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
    revision: revision(record),
  }
}

/**
 * Upcoming: the ones with no date first, then from the nearest. Past: from the latest. All: the
 * upcoming ones in their order, then the past ones in theirs (decision 4).
 */
function ordered(found: EventRecord[], asked: string): EventRecord[] {
  const start = (record: EventRecord) => time(record.values.starts_at)
  const ahead = found
    .filter((record) => !isPast(record.values))
    .sort((one, two) => {
      const a = start(one)
      const b = start(two)

      if (a === null || b === null) return (a === null ? 0 : 1) - (b === null ? 0 : 1)

      return a - b
    })
  const behind = found
    .filter((record) => isPast(record.values))
    .sort((one, two) => (start(two) ?? 0) - (start(one) ?? 0))

  if (asked === 'past') return behind
  if (asked === 'all') return [...ahead, ...behind]

  return ahead
}

/** The list (§4.10): a page of it, narrowed by whatever was asked. */
export function listEvents(query: URLSearchParams, locale: string) {
  const trashed = query.get('trashed') === '1'
  const search = (query.get('q') ?? '').trim().toLowerCase()
  const status = query.get('status') ?? ''
  const asked = query.get('when') ?? 'upcoming'
  const perPage = Number(query.get('per_page') ?? 20) || 20
  const current = Number(query.get('page') ?? 1) || 1
  const by = (name: string): number | null => {
    const raw = Number(query.get(name) ?? '')

    return Number.isInteger(raw) && raw > 0 ? raw : null
  }

  let found = events.filter((record) => (record.deleted_at === null) !== trashed)

  if (search !== '') {
    found = found.filter((record) =>
      [record.values.title, record.values.slug].some((value) =>
        Object.values((value ?? {}) as Record<string, string>).some((one) =>
          String(one).toLowerCase().includes(search),
        ),
      ),
    )
  }

  // "Live" is asked as `published` and means both greens, as on the site.
  if (status === 'published') {
    found = found.filter((record) => record.status === 'published' || record.status === 'modified')
  } else if (status !== '') {
    found = found.filter((record) => record.status === status)
  }

  for (const [name, field] of [
    ['category', 'categories'],
    ['service', 'services'],
  ] as const) {
    const id = by(name)

    if (id !== null) found = found.filter((record) => ids(record.values[field]).includes(id))
  }

  found = ordered(found, trashed ? 'all' : asked)

  const total = found.length
  const from = (current - 1) * perPage
  const rows = found.slice(from, from + perPage)

  return {
    data: rows.map((record) => row(record, locale)),
    links: {},
    meta: {
      current_page: current,
      last_page: Math.max(1, Math.ceil(total / perPage)),
      per_page: perPage,
      total,
      from: total === 0 ? null : from + 1,
      to: total === 0 ? null : from + rows.length,
    },
    filters: {
      categories: eventCategories
        .filter((one) => one.deleted_at === null)
        .map((one) => ({ id: one.id, title: text(one.title, locale) || one.name })),
      services: services
        .filter((one) => one.deleted_at === null)
        .map((one) => ({ id: one.id, title: serviceRow(one, locale).title })),
    },
  }
}

function blank(): ScreenModel {
  return {
    title: {},
    slug: {},
    lead: {},
    gallery: [],
    starts_at: null,
    ends_at: null,
    all_day: false,
    date_note: {},
    attendance: 'offline',
    venue: {},
    address: {},
    map_url: null,
    description: {},
    highlights: [],
    price: {},
    price_amount: null,
    booking_url: null,
    categories: [],
    services: [],
    seo: {},
  }
}

export function createEvent(title: string, slug: string): EventRecord {
  const record: EventRecord = {
    id: Math.max(0, ...events.map((one) => one.id)) + 1,
    values: { ...blank(), title: { ru: title, en: title }, slug: { ru: slug, en: slug } },
    live: null,
    status: 'draft',
    published_at: null,
    updated_at: new Date().toISOString(),
    deleted_at: null,
    versions: [],
    snapshots: {},
  }

  events.push(record)

  return record
}

/** The next free `-2`, `-3` of a slug among every event, the bin included. */
function freeSlug(slug: string, locale: string): string {
  const taken = new Set(events.map((one) => text(one.values.slug, locale)))

  for (let suffix = 2; ; suffix += 1) {
    const candidate = `${slug.replace(/-\d+$/, '')}-${suffix}`

    if (!taken.has(candidate)) return candidate
  }
}

/**
 * Decision 9: a copy as a draft that was never published — every field, the categories and the
 * services, the same title, the SEO card, an address with the next free suffix in every language
 * and no history.
 */
export function duplicateEvent(source: EventRecord): EventRecord {
  const values = clone(source.values)
  const slug = (values.slug ?? {}) as Record<string, string>

  values.slug = Object.fromEntries(
    Object.entries(slug).map(([locale, one]) => [locale, one ? freeSlug(one, locale) : one]),
  )

  const record: EventRecord = {
    id: Math.max(0, ...events.map((one) => one.id)) + 1,
    values,
    live: null,
    status: 'draft',
    published_at: null,
    updated_at: new Date().toISOString(),
    deleted_at: null,
    versions: [],
    snapshots: {},
  }

  events.push(record)

  return record
}

/**
 * A save of the form (§4.10): what travelled, over what was there. The moments are brought to the
 * server's zone before anything else — the way `Panel\Instant` does it — and refused under their
 * own names when they do not add up.
 */
export function writeEvent(
  record: EventRecord,
  sent: Record<string, unknown>,
): Record<string, string[]> | null {
  const values = { ...sent }
  const errors: Record<string, string[]> = {}

  for (const name of ['starts_at', 'ends_at']) {
    if (!(name in values)) continue

    const read = moment(values[name])

    if (read === undefined) errors[name] = ['This is not a date.']
    else values[name] = read
  }

  const starts = time(after('starts_at'))
  const ends = time(after('ends_at'))

  /* What a key will hold once this save is written: sent, or kept. */
  function after(key: string): unknown {
    return key in values ? values[key] : record.values[key]
  }

  if (ends !== null && starts === null) {
    errors.ends_at = ['An end needs a start.']
  } else if (ends !== null && starts !== null && ends < starts) {
    errors.ends_at = ['The end is before the start.']
  }

  if (
    values.attendance !== undefined &&
    !['offline', 'online', 'mixed'].includes(String(values.attendance))
  ) {
    errors.attendance = ['Offline, online or both.']
  }

  if (values.price_amount !== undefined && values.price_amount !== null) {
    const amount = Number(values.price_amount)

    if (!Number.isFinite(amount) || amount < 0) errors.price_amount = ['A number, zero or more.']
    else values.price_amount = Math.round(amount * 100) / 100
  }

  if (Object.keys(errors).length > 0) return errors

  if (Array.isArray(values.gallery)) {
    values.gallery = (values.gallery as Record<string, unknown>[])
      .filter((one) => typeof one?.path === 'string')
      .map((one) => ({
        path: one.path,
        ...(one.alt === undefined ? {} : { alt: one.alt }),
        ...(one.title === undefined ? {} : { title: one.title }),
      }))
  }

  if (Array.isArray(values.highlights)) {
    values.highlights = (values.highlights as Record<string, unknown>[]).map((one) => ({
      title: (one?.title ?? {}) as LocalizedValue,
      text: (one?.text ?? {}) as LocalizedValue,
    }))
  }

  for (const key of ['categories', 'services']) {
    if (values[key] !== undefined) values[key] = [...new Set(ids(values[key]))]
  }

  if (values.all_day !== undefined) values.all_day = values.all_day === true

  record.values = { ...record.values, ...values }
  record.updated_at = new Date().toISOString()
  restate(record)

  return null
}

export function publish(record: EventRecord): void {
  const now = new Date().toISOString()
  const number = (record.versions[0]?.number ?? 0) + 1

  record.published_at = now
  record.status = 'published'
  record.live = clone(record.values)
  record.updated_at = now
  record.snapshots[number] = clone(record.values)
  record.versions.unshift({
    number,
    created_at: now,
    author: 'Анна Ковальчук',
    source: 'panel',
    comment: null,
    is_pinned: false,
  })
}

/** Throw away what is waiting and keep what the site is showing. */
export function discard(record: EventRecord): void {
  if (record.live !== null) {
    for (const field of draftedFields({ ...record.values, ...record.live })) {
      record.values[field] = clone(record.live[field] ?? null)
    }
  }

  record.updated_at = new Date().toISOString()
  restate(record)
}

export function restoreVersion(record: EventRecord, number: number): boolean {
  const snapshot = record.snapshots[number]

  if (snapshot === undefined) return false

  for (const field of draftedFields(snapshot)) {
    record.values[field] = clone(snapshot[field])
  }

  record.updated_at = new Date().toISOString()
  restate(record)

  return true
}

export function revision(record: EventRecord): string {
  return `${record.id}:${record.updated_at}`
}

/** One event as its editor opens it — `{ event, values, revision, prefix, preview_url }`. */
export function eventDetail(record: EventRecord, locale: string) {
  return {
    event: row(record, locale),
    values: clone(record.values),
    revision: revision(record),
    prefix: PREFIX,
    preview_url: `/preview/event/${record.id}`,
  }
}

/* ---------------------------------------------------------------------- categories ----- */

export function termRow(record: TermRecord, locale: string) {
  return {
    id: record.id,
    name: text(record.title, locale) || record.name,
    title: record.title,
    slug: record.slug,
    path: record.path,
    url: record.url,
    is_visible: record.is_visible,
    position: record.position,
    deleted_at: record.deleted_at,
    events_count: events.filter(
      (one) => one.deleted_at === null && ids(one.values.categories).includes(record.id),
    ).length,
  }
}

export function termDetail(record: TermRecord, locale: string) {
  return {
    category: termRow(record, locale),
    values: {
      ...record.extra,
      title: record.title,
      slug: record.slug,
      is_visible: record.is_visible,
      lead: record.lead,
      cover: record.cover,
      seo: record.seo,
    },
    prefix: PREFIX,
  }
}

export function createTerm(title: LocalizedValue, slug: string): TermRecord {
  const id = Math.max(0, ...eventCategories.map((one) => one.id)) + 1
  const record: TermRecord = {
    id,
    name: Object.values(title)[0] ?? '',
    title,
    slug: { ru: slug, en: slug },
    path: `${PREFIX}/${slug}`,
    url: `https://webx-demo.test/${PREFIX}/${slug}`,
    is_visible: true,
    position: eventCategories.length + 1,
    deleted_at: null,
    lead: {},
    cover: null,
    seo: {},
    extra: {},
  }

  eventCategories.push(record)

  return record
}

export function writeTerm(
  record: TermRecord,
  values: Record<string, unknown>,
  locale: string,
): Record<string, string[]> | null {
  if (
    values.title !== undefined &&
    Object.values((values.title ?? {}) as Record<string, string>).every((one) => !one)
  ) {
    return { title: ['Название нужно хотя бы на одном языке.'] }
  }

  for (const [name, value] of Object.entries(values)) {
    if (name === 'title') record.title = (value ?? {}) as LocalizedValue
    else if (name === 'is_visible') record.is_visible = value === true
    else if (name === 'slug') record.slug = (value ?? {}) as LocalizedValue
    else if (name === 'lead') record.lead = (value ?? {}) as LocalizedValue
    else if (name === 'cover') record.cover = (value ?? null) as Record<string, unknown> | null
    else if (name === 'seo') record.seo = (value ?? {}) as Record<string, unknown>
    else record.extra[name] = value
  }

  record.name = text(record.title, locale) || record.name

  const slug = text(record.slug, locale) || text(record.slug, 'ru')

  record.path = `${PREFIX}/${slug}`
  record.url = `https://webx-demo.test/${record.path}`

  return null
}

export function reorderTerms(order: number[]): void {
  const moved = order.map((id) => findTerm(id)).filter((one): one is TermRecord => !!one)
  const rest = eventCategories.filter((one) => !order.includes(one.id))

  eventCategories.splice(0, eventCategories.length, ...moved, ...rest)
  eventCategories.forEach((one, index) => {
    one.position = index + 1
  })
}

/* ------------------------------------------------------------------------------ site ----- */

const esc = (value: string): string =>
  value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

/**
 * The page of one event (§4.5), out of its draft — this is a preview — in the order of the parts
 * of `event.blade.php`: gallery, title and lead (with "It is over" on a past one), the facts, the
 * booking button and the calendar link, the description, "What to expect", the services.
 */
export function drawEventPage(record: EventRecord, locale = 'ru'): string {
  const values = record.values
  const t = (value: unknown) => text(value, locale)
  const gallery = pictures(values.gallery)
  const [first, ...rest] = gallery
  const past = isPast(values)
  const attendance = String(values.attendance ?? 'offline')
  const printed = when(values, locale)

  const place =
    attendance === 'online'
      ? '<li>Онлайн</li>'
      : [
          t(values.venue) ? `<li>${esc(t(values.venue))}</li>` : '',
          t(values.address)
            ? `<li>${
                typeof values.map_url === 'string' && values.map_url !== ''
                  ? `<a href="${esc(values.map_url)}">${esc(t(values.address))}</a>`
                  : esc(t(values.address))
              }</li>`
            : '',
          attendance === 'mixed' ? '<li>и онлайн</li>' : '',
        ].join('')

  const categories = ids(values.categories)
    .map((id) => findTerm(id))
    .filter((one): one is TermRecord => one !== null && one.deleted_at === null)
    .map((one) => `<li><a href="/${esc(one.path)}">${esc(t(one.title))}</a></li>`)
    .join('')

  const facts = [
    printed ? `<li>🗓 ${esc(printed)}</li>` : '',
    place,
    t(values.price) ? `<li>${esc(t(values.price))}</li>` : '',
    categories,
  ].join('')

  const booking =
    !past && typeof values.booking_url === 'string' && values.booking_url !== ''
      ? `<a class="e-book" href="${esc(values.booking_url)}">Записаться</a>`
      : ''
  const calendar =
    values.starts_at !== null
      ? `<a class="e-ics" href="/${PREFIX}/${esc(t(values.slug))}.ics">Добавить в календарь</a>`
      : ''

  const highlights = (Array.isArray(values.highlights) ? values.highlights : [])
    .map((one: { title?: unknown; text?: unknown }) => ({ title: t(one.title), text: t(one.text) }))
    .filter((one) => one.title !== '' || one.text !== '')

  const serviceCards = ids(values.services).flatMap((id) => {
    const found = findService(id)

    if (found === null || found.deleted_at !== null || found.live === null) return []

    const drawn = serviceRow(found, locale)

    return [
      `<a class="e-service" href="${esc(drawn.url ?? '#')}"><strong>${esc(drawn.title)}</strong></a>`,
    ]
  })

  return `<article class="site-wrap e-page">
    ${
      first
        ? `<div class="e-gallery"><img class="e-gallery__main" src="${esc(first.url)}" alt="${esc(first.alt)}">${
            rest.length > 0
              ? `<div class="e-gallery__strip">${rest.map((one) => `<img src="${esc(one.thumb)}" alt="${esc(one.alt)}">`).join('')}</div>`
              : ''
          }</div>`
        : ''
    }
    ${past ? '<p class="e-past">Событие прошло</p>' : ''}
    <h1>${esc(t(values.title))}</h1>
    ${t(values.lead) ? `<p class="e-lead">${esc(t(values.lead))}</p>` : ''}
    ${facts ? `<ul class="e-facts">${facts}</ul>` : ''}
    ${booking || calendar ? `<p class="e-actions">${booking}${calendar}</p>` : ''}
    ${t(values.description) ? `<section>${t(values.description)}</section>` : ''}
    ${
      highlights.length > 0
        ? `<section><h2>Чего ожидать</h2><div class="e-highlights">${highlights
            .map((one) => `<div><strong>${esc(one.title)}</strong><p>${esc(one.text)}</p></div>`)
            .join('')}</div></section>`
        : ''
    }
    ${serviceCards.length > 0 ? `<section><h2>Услуги</h2><div class="e-services">${serviceCards.join('')}</div></section>` : ''}
  </article>`
}

export const EVENT_PAGE_STYLES = `
  .e-page { padding-block: 32px; max-width: 860px; }
  .e-page section { margin-top: 32px; }
  .e-gallery__main { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 14px; display: block; }
  .e-gallery__strip { display: flex; gap: 8px; margin-top: 8px; overflow-x: auto; }
  .e-gallery__strip img { width: 120px; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 8px; flex: none; }
  .e-page h1 { margin-top: 24px; }
  .e-past { display: inline-block; margin: 24px 0 0; padding: 2px 10px; border-radius: 999px; background: #eef0f4; color: #4b5263; }
  .e-lead { font-size: 19px; color: #4b5263; }
  .e-facts { display: flex; flex-wrap: wrap; gap: 8px 20px; padding: 0; list-style: none; color: #4b5263; }
  .e-actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
  .e-book { padding: 10px 20px; border-radius: 10px; background: #2d6a3e; color: #fff; text-decoration: none; }
  .e-highlights { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
  .e-highlights > div { padding: 16px; border: 1px solid #e6e8ee; border-radius: 10px; }
  .e-services { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
  .e-service { padding: 16px; border: 1px solid #e6e8ee; border-radius: 10px; color: inherit; text-decoration: none; }
`

/* --------------------------------------------------------------------------- helpers ----- */

function restate(record: EventRecord): void {
  if (record.live === null || (record.status !== 'published' && record.status !== 'modified')) {
    return
  }

  record.status = drafted(record.values) === drafted(record.live) ? 'published' : 'modified'
}

function draftedFields(values: ScreenModel): string[] {
  return Object.keys(values).filter((name) => !UNDRAFTED.includes(name))
}

function drafted(values: ScreenModel): string {
  return draftedFields(values)
    .sort()
    .map((name) => JSON.stringify(values[name] ?? null))
    .join('|')
}

function pictures(value: unknown): { url: string; thumb: string; alt: string }[] {
  if (!Array.isArray(value)) return []

  return value.flatMap((one: { path?: unknown; alt?: unknown }) => {
    const file = typeof one?.path === 'string' ? fileByPath(one.path) : null

    return file === null
      ? []
      : [{ url: file.url, thumb: file.thumb ?? file.url, alt: text(one.alt, 'ru') }]
  })
}

function cover(values: ScreenModel): { thumb: string | null } | null {
  const first = pictures(values.gallery)[0]

  return first === undefined ? null : { thumb: first.thumb }
}

function named(list: number[], locale: string): EventTermRef[] {
  return list.flatMap((id) => {
    const found = findTerm(id)

    return found === null ? [] : [{ id, title: text(found.title, locale) || found.name }]
  })
}

/** One language out of a localized value — and nothing else: no falling back onto another. */
export function text(value: unknown, locale: string): string {
  if (typeof value === 'string') return value
  if (value === null || typeof value !== 'object') return ''

  const map = value as LocalizedValue

  return typeof map[locale] === 'string' ? map[locale] : ''
}

export function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

/**
 * The words of the two screen copies above, while the php half's `lang` is not on this branch —
 * `lang.ts` falls back on these for `webx-events::screen.*` only when there is no file to read.
 * EV3 takes both the copies and these away.
 */
export const INTERIM_SCREEN_WORDS: Record<string, string> = {
  event: 'Event',
  when: 'When',
  'all-day': 'All day',
  'all-day-help': 'For an event of a day or several: the site prints the dates without the time.',
  'starts-at': 'Starts',
  'starts-on': 'First day',
  'ends-at': 'Ends',
  'ends-at-help': 'Optional. Without it the event is over the moment it starts.',
  'ends-on': 'Last day',
  'ends-on-help': 'Optional. The same day as the first, or leave it empty, for one day.',
  'date-note': 'Date in words',
  'date-note-help':
    'Printed instead of the date: “every Saturday”, “dates to be announced”. Sorting, “over” and the calendar still go by the date.',
  'date-note-placeholder': 'Every Saturday, 9:00',
  where: 'Where',
  attendance: 'Format',
  'attendance-offline': 'In person',
  'attendance-online': 'Online',
  'attendance-mixed': 'Both',
  venue: 'Venue',
  address: 'Address',
  'map-url': 'Link to the map',
  booking: 'Booking',
  price: 'Price',
  'price-help': 'As the page prints it: “HK$480 per person”, “On request”.',
  'price-amount': 'Price as a number',
  'price-amount-help':
    'Only for search engines, in the currency the site is set to. Zero means free. Empty — no price in the markup.',
  'booking-url': 'Booking link',
  'booking-url-help': 'Where “Book” leads. No link, or the event is over — no button.',
  photos: 'Photos',
  gallery: 'Gallery',
  'gallery-help': 'The first picture is the cover. For an event that is over, this is the report.',
  about: 'About',
  description: 'Description',
  highlights: 'What to expect',
  'highlights-help': 'A heading and a line or two each. The same cards in every language.',
  'highlight-title': 'Heading',
  'highlight-text': 'Text',
  settings: 'Settings',
  naming: 'Name and address',
  title: 'Title',
  slug: 'Address',
  'slug-help': 'The address of the event page. Changing it keeps the old one working.',
  lead: 'Lead',
  'lead-help': 'One or two sentences for the cards and the search engines. No formatting.',
  taxonomy: 'Filing',
  categories: 'Categories',
  'categories-help': 'The first one is the main one: it goes in the breadcrumbs.',
  services: 'Services',
  'services-help': 'The services this event belongs to. Shown on its page.',
  seo: 'SEO',
  'seo-empty': 'Install the SEO module to edit the title and description for search engines.',
  history: 'History',
  content: 'Content',
  visible: 'Shown on the site',
  'category-visible-help': 'A hidden category has no page. Its events stay on the site.',
  'category-lead': 'Introduction',
  'category-lead-help': 'Printed above the events of the category.',
  presentation: 'Presentation',
  cover: 'Cover',
  'category-cover-help': 'The picture of the category page and its card.',
}
