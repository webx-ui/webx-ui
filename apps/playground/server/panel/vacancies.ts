import type { LocalizedValue } from '../../../../packages/core/src/composables/useLocalized'
import type {
  VacancyClosedReason,
  VacancyRow,
  VacancyStatus,
  VacancyTermRef,
  VacancyVersion,
} from '../../../../packages/module-vacancies/src/types'
import type { ScreenModel } from '../../../../packages/schema/src/types'
import { vacanciesMessages } from '../../../../packages/module-vacancies/src/messages'
import { forms } from './inbox'

/**
 * The vacancies the playground edits, answering in the shapes of §4.11 of the vacancies spec:
 * every vacancy at once in the one order, split into the open and the closed ones, their drafts,
 * and the categories they are grouped under — groups with a key, not pages.
 *
 * The two dates are the point of this fixture. `valid_through` and `posted_at` are calendar days,
 * `YYYY-MM-DD`, and a vacancy is open through the whole of its last day *in the application's
 * zone* — Hong Kong here, as with the events, so "today" is not the browser's today on purpose.
 * Every seeded day is counted from the moment the server started, or the demo would expire by
 * itself: one vacancy is closed by hand, one ran out the day before yesterday, one is a draft.
 *
 * Everything a vacancy is filed under — the categories, the response form, "closed" — waits in the
 * draft and reaches the site on "Publish"; only the SEO card is written as it is saved.
 */

/** The first segment of every vacancy address (§4.4). The categories have none. */
export const PREFIX = 'careers'

/** The zone of the application — what `config('app.timezone')` would be. Not UTC on purpose. */
const OFFSET_MINUTES = 8 * 60

/** `webx-vacancies.currencies`: code → symbol, the first one for a new vacancy (§4.9). */
export const CURRENCIES: Record<string, string> = { USD: '$', EUR: '€', UAH: '₴', PLN: 'zł' }

/** `webx-vacancies.country`. */
const COUNTRY = 'UA'

const EMPLOYMENT = [
  'FULL_TIME',
  'PART_TIME',
  'CONTRACTOR',
  'TEMPORARY',
  'INTERN',
  'VOLUNTEER',
  'PER_DIEM',
  'OTHER',
]

const UNITS = ['HOUR', 'DAY', 'WEEK', 'MONTH', 'YEAR']

/** The three lists of a vacancy (decision 18): rows of one translated line each. */
const LISTS = ['duties', 'requirements', 'benefits'] as const

export interface VacancyRecord {
  id: number
  /** What the editor opens: the draft laid over what was last published. */
  values: ScreenModel
  /** What the site is showing, or `null` for a vacancy that has never been on it. */
  live: ScreenModel | null
  status: VacancyStatus
  position: number
  published_at: string | null
  updated_at: string
  deleted_at: string | null
  versions: VacancyVersion[]
  snapshots: Record<number, ScreenModel>
}

export interface VacancyTermRecord {
  id: number
  name: string
  title: LocalizedValue
  slug: LocalizedValue
  is_visible: boolean
  position: number
  deleted_at: string | null
  extra: Record<string, unknown>
}

export const vacancies: VacancyRecord[] = []
export const vacancyCategories: VacancyTermRecord[] = []

/** Written as it is saved rather than kept for the publication. */
const UNDRAFTED = ['seo']

/* --------------------------------------------------------------------------------- days ----- */

/** Today in the application's zone, `YYYY-MM-DD` — the day a vacancy is compared with. */
export function today(): string {
  return dayFromNow(0)
}

/** A day counted from today in the application's zone. */
function dayFromNow(days: number): string {
  const local = new Date(Date.now() + OFFSET_MINUTES * 60_000)

  local.setUTCDate(local.getUTCDate() + days)

  return local.toISOString().slice(0, 10)
}

const DAY = /^\d{4}-\d{2}-\d{2}$/

/**
 * Decision 13: closed by hand, or its last day is behind us. `manual` wins when both are true.
 * Read off what the site shows — the draft's "closed" is not closed until it is published.
 */
export function closedReason(record: VacancyRecord): VacancyClosedReason | null {
  const shown = record.live ?? record.values

  if (shown.is_closed === true) return 'manual'

  const until = shown.valid_through

  return typeof until === 'string' && until !== '' && until < today() ? 'expired' : null
}

/* -------------------------------------------------------------------------------- terms ----- */

function term(title: [string, string], slug: string): void {
  const id = vacancyCategories.length + 1

  vacancyCategories.push({
    id,
    name: title[0],
    title: { ru: title[0], en: title[1] },
    slug: { ru: slug, en: slug },
    is_visible: true,
    position: id,
    deleted_at: null,
    extra: {},
  })
}

term(['Разработка', 'Development'], 'development')
term(['Продажи', 'Sales'], 'sales')
term(['Поддержка', 'Support'], 'support')

/* ---------------------------------------------------------------------------- vacancies ----- */

interface Seed {
  title: [string, string]
  slug: string
  lead: [string, string]
  workplace?: 'onsite' | 'remote' | 'hybrid'
  city?: [string, string]
  address?: [string, string]
  employment?: string[]
  salary?: [string, string]
  salary_min?: number | null
  salary_max?: number | null
  salary_unit?: string | null
  salary_currency?: string | null
  description?: string
  duties?: [string, string][]
  requirements?: [string, string][]
  benefits?: [string, string][]
  is_closed?: boolean
  valid_through?: string | null
  posted_at?: string | null
  categories: number[]
  form?: number[]
  status?: VacancyStatus
}

const pair = (value: [string, string] | undefined): LocalizedValue =>
  value === undefined ? {} : { ru: value[0], en: value[1] }

const lines = (list: [string, string][] | undefined) =>
  (list ?? []).map((one) => ({ text: pair(one) }))

function vacancy(seed: Seed): VacancyRecord {
  const id = vacancies.length + 1
  const status = seed.status ?? 'published'

  const values: ScreenModel = {
    title: pair(seed.title),
    slug: { ru: seed.slug, en: seed.slug },
    lead: pair(seed.lead),
    workplace: seed.workplace ?? 'onsite',
    city: pair(seed.city),
    address: pair(seed.address),
    country: COUNTRY,
    employment_types: seed.employment ?? ['FULL_TIME'],
    salary: pair(seed.salary),
    salary_min: seed.salary_min ?? null,
    salary_max: seed.salary_max ?? null,
    salary_unit: seed.salary_unit ?? null,
    salary_currency: seed.salary_currency ?? 'UAH',
    description: { ru: seed.description ?? '', en: '' },
    duties: lines(seed.duties),
    requirements: lines(seed.requirements),
    benefits: lines(seed.benefits),
    is_closed: seed.is_closed ?? false,
    valid_through: seed.valid_through ?? null,
    posted_at: status === 'draft' ? null : (seed.posted_at ?? dayFromNow(-10)),
    categories: seed.categories,
    form: seed.form ?? [],
    seo: {},
  }

  const published = status === 'draft' ? null : `${dayFromNow(-10)}T09:00:00+08:00`
  const record: VacancyRecord = {
    id,
    values,
    live: status === 'draft' ? null : clone(values),
    status,
    position: id,
    published_at: published,
    updated_at: `${dayFromNow(-3)}T10:00:00+08:00`,
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

  vacancies.push(record)

  return record
}

const KYIV = {
  city: ['Киев', 'Kyiv'] as [string, string],
  address: ['ул. Антоновича, 44, 3 этаж', '44 Antonovycha St, 3rd floor'] as [string, string],
}

/* In the office, full time, a range in hryvnias a month, a form to answer with: the one to open. */
vacancy({
  title: ['Senior PHP-разработчик', 'Senior PHP developer'],
  slug: 'senior-php-developer',
  lead: [
    'Laravel, очереди и API для десятка сайтов клиентов.',
    'Laravel, queues and APIs for a dozen client sites.',
  ],
  ...KYIV,
  employment: ['FULL_TIME'],
  salary: ['от 90 000 ₴ на руки', 'from ₴90,000 net'],
  salary_min: 90000,
  salary_max: 120000,
  salary_unit: 'MONTH',
  salary_currency: 'UAH',
  description:
    '<p>Мы делаем сайты и панели для клиентов из трёх стран. Ищем того, кто возьмёт на себя серверную часть.</p>',
  duties: [
    ['Писать и поддерживать API панели', 'Write and keep the panel API'],
    ['Разбирать заявки поддержки', 'Look into support requests'],
  ],
  requirements: [
    ['PHP 8.3 и Laravel от трёх лет', 'PHP 8.3 and Laravel, three years or more'],
    ['MySQL или MariaDB', 'MySQL or MariaDB'],
  ],
  benefits: [
    ['Оплачиваемый отпуск 24 дня', '24 days of paid leave'],
    ['Обучение за счёт компании', 'Courses paid by the company'],
  ],
  valid_through: dayFromNow(30),
  categories: [1],
  form: [1],
})

/* Remote contract, dollars an hour. */
vacancy({
  title: ['Frontend-разработчик (Vue)', 'Frontend developer (Vue)'],
  slug: 'frontend-developer-vue',
  lead: ['Удалённо, почасовая оплата.', 'Remote, paid by the hour.'],
  workplace: 'remote',
  employment: ['CONTRACTOR'],
  salary: ['25–40 $ в час', '$25–40 an hour'],
  salary_min: 25,
  salary_max: 40,
  salary_unit: 'HOUR',
  salary_currency: 'USD',
  requirements: [['Vue 3 и TypeScript', 'Vue 3 and TypeScript']],
  valid_through: dayFromNow(45),
  categories: [1],
})

/* Hybrid, part or full time, the pay in words only, and no last day. */
vacancy({
  title: ['Менеджер по продажам', 'Sales manager'],
  slug: 'sales-manager',
  lead: ['Во Львове, два дня в офисе.', 'In Lviv, two days a week in the office.'],
  workplace: 'hybrid',
  city: ['Львов', 'Lviv'],
  employment: ['FULL_TIME', 'PART_TIME'],
  salary: ['Ставка и процент — обсудим на встрече', 'A base and commission — we will talk'],
  categories: [2],
  form: [2],
})

/* Closed by hand before its last day: its page stays, off the lists. */
vacancy({
  title: ['Специалист поддержки', 'Support specialist'],
  slug: 'support-specialist',
  lead: ['Первая линия, смены.', 'First line, in shifts.'],
  ...KYIV,
  employment: ['FULL_TIME'],
  salary_min: 35000,
  salary_unit: 'MONTH',
  salary_currency: 'UAH',
  is_closed: true,
  valid_through: dayFromNow(20),
  categories: [3],
})

/* Its last day was the day before yesterday. */
vacancy({
  title: ['Аккаунт-менеджер', 'Account manager'],
  slug: 'account-manager',
  lead: ['Ведение ключевых клиентов.', 'Looking after the key clients.'],
  ...KYIV,
  employment: ['FULL_TIME'],
  valid_through: dayFromNow(-2),
  posted_at: dayFromNow(-40),
  categories: [2],
})

/* Never published, in two categories. */
vacancy({
  title: ['QA-инженер', 'QA engineer'],
  slug: 'qa-engineer',
  lead: ['Ручное и автоматическое тестирование.', 'Manual and automated testing.'],
  ...KYIV,
  employment: ['FULL_TIME'],
  categories: [1, 3],
  status: 'draft',
})

/* No category, and no Russian: the site in Russian does not list it. */
vacancy({
  title: ['', 'Office administrator'],
  slug: 'office-administrator',
  lead: ['', 'Keeps the office running.'],
  ...KYIV,
  employment: ['PART_TIME'],
  categories: [],
})

/* In the bin: the list can bring it back. */
const withdrawn = vacancy({
  title: ['Стажёр-дизайнер', 'Design intern'],
  slug: 'design-intern',
  lead: ['', ''],
  ...KYIV,
  employment: ['INTERN'],
  categories: [1],
})

withdrawn.deleted_at = `${dayFromNow(-1)}T15:00:00+08:00`

/* ------------------------------------------------------------------------------ reading ----- */

export function find(id: number): VacancyRecord | null {
  return vacancies.find((record) => record.id === id) ?? null
}

export function findTerm(id: number): VacancyTermRecord | null {
  return vacancyCategories.find((row) => row.id === id) ?? null
}

export function ids(value: unknown): number[] {
  return Array.isArray(value)
    ? value.map(Number).filter((one) => Number.isInteger(one) && one > 0)
    : []
}

/** In the panel's language, else in the default one (`ru`), else nothing. */
function inPanel(value: unknown, locale: string): string {
  return text(value, locale) || text(value, 'ru')
}

/** One vacancy as a screen reads it (§4.11) — per request, in the panel's language. */
export function row(record: VacancyRecord, locale: string): VacancyRow {
  const live = record.live === null ? '' : text(record.live.slug, locale)
  const path = live === '' ? null : `${PREFIX}/${live}`
  const values = record.values
  const reason = closedReason(record)

  return {
    id: record.id,
    title: inPanel(values.title, locale) || `#${record.id}`,
    slug: text(values.slug, locale),
    path,
    url: path === null ? null : `https://webx-demo.test/${path}`,
    workplace: (values.workplace as VacancyRow['workplace']) ?? 'onsite',
    city: inPanel(values.city, locale),
    employment_types: Array.isArray(values.employment_types)
      ? (values.employment_types as string[])
      : [],
    valid_through: (values.valid_through as string | null) ?? null,
    posted_at: (values.posted_at as string | null) ?? null,
    closed: reason !== null,
    closed_reason: reason,
    status: record.status,
    position: record.position,
    categories: named(ids(values.categories), locale),
    published_at: record.published_at,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
    revision: revision(record),
  }
}

/** The list (§4.11): all of it, in the one order, narrowed by whatever was asked. */
export function listVacancies(query: URLSearchParams, locale: string) {
  const trashed = query.get('trashed') === '1'
  const search = (query.get('q') ?? '').trim().toLowerCase()
  const status = query.get('status') ?? ''
  const state = query.get('state') ?? 'open'
  const category = Number(query.get('category') ?? '')

  let found = vacancies.filter((record) => (record.deleted_at === null) !== trashed)

  if (!trashed && state !== 'all') {
    found = found.filter((record) => (closedReason(record) !== null) === (state === 'closed'))
  }

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

  if (Number.isInteger(category) && category > 0) {
    found = found.filter((record) => ids(record.values.categories).includes(category))
  }

  found = [...found].sort((one, two) => one.position - two.position || one.id - two.id)

  return {
    data: found.map((record) => row(record, locale)),
    filters: {
      categories: vacancyCategories
        .filter((one) => one.deleted_at === null)
        .sort((one, two) => one.position - two.position)
        .map((one) => ({ id: one.id, title: inPanel(one.title, locale) || one.name })),
    },
  }
}

function blank(): ScreenModel {
  return {
    title: {},
    slug: {},
    lead: {},
    workplace: 'onsite',
    city: {},
    address: {},
    country: COUNTRY,
    employment_types: ['FULL_TIME'],
    salary: {},
    salary_min: null,
    salary_max: null,
    salary_unit: null,
    salary_currency: Object.keys(CURRENCIES)[0] ?? null,
    description: {},
    duties: [],
    requirements: [],
    benefits: [],
    is_closed: false,
    valid_through: null,
    posted_at: null,
    categories: [],
    form: [],
    seo: {},
  }
}

/** A new one goes to the end: `max + 1`, the bin counted (§4.1). */
function nextPosition(): number {
  return Math.max(0, ...vacancies.map((one) => one.position)) + 1
}

export function createVacancy(title: string, slug: string): VacancyRecord {
  const record: VacancyRecord = {
    id: Math.max(0, ...vacancies.map((one) => one.id)) + 1,
    values: { ...blank(), title: { ru: title, en: title }, slug: { ru: slug, en: slug } },
    live: null,
    status: 'draft',
    position: nextPosition(),
    published_at: null,
    updated_at: new Date().toISOString(),
    deleted_at: null,
    versions: [],
    snapshots: {},
  }

  vacancies.push(record)

  return record
}

/** The next free `-2`, `-3` of a slug among every vacancy, the bin included. */
function freeSlug(slug: string, locale: string): string {
  const taken = new Set(vacancies.map((one) => text(one.values.slug, locale)))

  for (let suffix = 2; ; suffix += 1) {
    const candidate = `${slug.replace(/-\d+$/, '')}-${suffix}`

    if (!taken.has(candidate)) return candidate
  }
}

/**
 * Decision 20: a copy as a draft that was never published — every field, the categories and the
 * form, the same title, the SEO card, an address with the next free suffix in every language, no
 * posting day, open, no history, and right after the original.
 */
export function duplicateVacancy(source: VacancyRecord): VacancyRecord {
  const values = clone(source.values)
  const slug = (values.slug ?? {}) as Record<string, string>

  values.slug = Object.fromEntries(
    Object.entries(slug).map(([locale, one]) => [locale, one ? freeSlug(one, locale) : one]),
  )
  values.posted_at = null
  values.is_closed = false

  for (const one of vacancies) {
    if (one.position > source.position) one.position += 1
  }

  const record: VacancyRecord = {
    id: Math.max(0, ...vacancies.map((one) => one.id)) + 1,
    values,
    live: null,
    status: 'draft',
    position: source.position + 1,
    published_at: null,
    updated_at: new Date().toISOString(),
    deleted_at: null,
    versions: [],
    snapshots: {},
  }

  vacancies.push(record)

  return record
}

const hasText = (value: unknown): boolean =>
  value !== null &&
  typeof value === 'object' &&
  Object.values(value as Record<string, unknown>).some(
    (one) => typeof one === 'string' && one.trim() !== '',
  )

/**
 * A save of the form (§4.11): what travelled, over what was there, refused under the names §4.11
 * puts each refusal under — so the panel can be seen drawing every one of them where it belongs.
 */
export function writeVacancy(
  record: VacancyRecord,
  sent: Record<string, unknown>,
): Record<string, string[]> | null {
  const values = { ...sent }
  const errors: Record<string, string[]> = {}

  /* What a key will hold once this save is written: sent, or kept. */
  const after = (key: string): unknown => (key in values ? values[key] : record.values[key])

  if (
    values.workplace !== undefined &&
    !['onsite', 'remote', 'hybrid'].includes(String(values.workplace))
  ) {
    errors.workplace = ['On site, remote or hybrid.']
  }

  if (typeof values.country === 'string') {
    values.country = values.country.trim().toUpperCase() || null
  }

  if (values.country != null && !/^[A-Z]{2}$/.test(String(values.country))) {
    errors.country = ['Two letters of ISO 3166: UA, PL, DE.']
  }

  if (values.employment_types !== undefined) {
    const list = Array.isArray(values.employment_types) ? values.employment_types : []

    if (list.some((one) => !EMPLOYMENT.includes(String(one)))) {
      errors.employment_types = ['A kind of employment Google does not know.']
    } else {
      values.employment_types = [...new Set(list.map(String))]
    }
  }

  for (const name of ['salary_min', 'salary_max']) {
    if (values[name] === undefined || values[name] === null || values[name] === '') {
      if (values[name] === '') values[name] = null
      continue
    }

    const amount = Number(values[name])

    if (!Number.isFinite(amount) || amount < 0) errors[name] = ['A number, zero or more.']
    else values[name] = Math.round(amount * 100) / 100
  }

  const min = after('salary_min')
  const max = after('salary_max')

  if (typeof min === 'number' && typeof max === 'number' && max < min && !errors.salary_max) {
    errors.salary_max = ['“To” is less than “From”.']
  }

  if ((typeof min === 'number' || typeof max === 'number') && !after('salary_unit')) {
    errors.salary_unit = ['A salary as a number needs a unit: an hour, a month, a year.']
  }

  if (
    values.salary_unit != null &&
    values.salary_unit !== '' &&
    !UNITS.includes(String(values.salary_unit))
  ) {
    errors.salary_unit = ['An hour, a day, a week, a month or a year.']
  }

  if (values.salary_unit === '') values.salary_unit = null

  // A currency taken off the list does not lock the vacancy that already has it (§4.9).
  if (
    values.salary_currency != null &&
    !(String(values.salary_currency) in CURRENCIES) &&
    values.salary_currency !== record.values.salary_currency
  ) {
    errors.salary_currency = [`One of ${Object.keys(CURRENCIES).join(', ')}.`]
  }

  for (const name of ['valid_through', 'posted_at']) {
    if (values[name] === '' || values[name] === undefined) {
      if (values[name] === '') values[name] = null
      continue
    }

    if (values[name] !== null && !DAY.test(String(values[name]))) {
      errors[name] = ['A day, YYYY-MM-DD.']
    }
  }

  const until = after('valid_through')
  const posted = after('posted_at')

  if (
    typeof until === 'string' &&
    typeof posted === 'string' &&
    until < posted &&
    !errors.valid_through
  ) {
    errors.valid_through = ['The last day is before the day it was posted.']
  }

  // Rows without a word in any language go; a word too long is refused under its own row.
  for (const name of LISTS) {
    if (values[name] === undefined) continue

    const kept = (Array.isArray(values[name]) ? (values[name] as Record<string, unknown>[]) : [])
      .map((one) => ({ text: (one?.text ?? {}) as LocalizedValue }))
      .filter((one) => hasText(one.text))

    kept.forEach((one, index) => {
      if (Object.values(one.text).some((line) => String(line).length > 500)) {
        errors[`${name}.${index}.text`] = ['Up to 500 characters.']
      }
    })

    values[name] = kept
  }

  if (values.form !== undefined) {
    const chosen = ids(values.form)

    if (chosen.length > 1) errors.form = ['One form, not more.']
    else if (chosen.some((id) => !forms.some((one) => one.id === id))) {
      errors.form = ['There is no such form.']
    } else values.form = chosen
  }

  if (values.categories !== undefined) values.categories = [...new Set(ids(values.categories))]
  if (values.is_closed !== undefined) values.is_closed = values.is_closed === true

  if (Object.keys(errors).length > 0) return errors

  record.values = { ...record.values, ...values }
  record.updated_at = new Date().toISOString()
  restate(record)

  return null
}

/** Decision 15: the day it was first put up, set by the first publication and kept after. */
export function publish(record: VacancyRecord): void {
  const now = new Date().toISOString()
  const number = (record.versions[0]?.number ?? 0) + 1

  if (!record.values.posted_at) record.values.posted_at = today()

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

/**
 * "Close the hiring" and "reopen it" (§4.11): the switch written and published in one go — so
 * edits waiting in the draft would go out with it, and that is refused.
 */
export function setClosed(record: VacancyRecord, closed: boolean): 'edits' | null {
  if (record.status === 'modified') return 'edits'

  record.values.is_closed = closed
  publish(record)

  return null
}

/** Throw away what is waiting and keep what the site is showing. */
export function discard(record: VacancyRecord): void {
  if (record.live !== null) {
    for (const field of draftedFields({ ...record.values, ...record.live })) {
      record.values[field] = clone(record.live[field] ?? null)
    }
  }

  record.updated_at = new Date().toISOString()
  restate(record)
}

export function restoreVersion(record: VacancyRecord, number: number): boolean {
  const snapshot = record.snapshots[number]

  if (snapshot === undefined) return false

  for (const field of draftedFields(snapshot)) {
    record.values[field] = clone(snapshot[field])
  }

  record.updated_at = new Date().toISOString()
  restate(record)

  return true
}

/**
 * One order, and only one — no category, ever (§4.11).
 *
 * The "Open" tab is dragged too, and it sends the open vacancies only: the closed ones are not on
 * screen. So the ids that came take the places they already held, in the new order, and whatever
 * was not sent keeps its own place — a closed vacancy between two open ones stays between them
 * rather than being pushed to the end of the list by a drag nobody saw it in.
 */
export function reorderVacancies(order: number[]): void {
  const moved = order.map((id) => find(id)).filter((one): one is VacancyRecord => !!one)
  const places = moved.map((one) => one.position).sort((one, two) => one - two)

  moved.forEach((one, index) => {
    one.position = places[index]!
  })
}

export function revision(record: VacancyRecord): string {
  return `${record.id}:${record.updated_at}`
}

/** One vacancy as its editor opens it — `{ vacancy, values, revision, prefix, preview_url }`. */
export function vacancyDetail(record: VacancyRecord, locale: string) {
  return {
    vacancy: row(record, locale),
    values: clone(record.values),
    revision: revision(record),
    prefix: PREFIX,
    preview_url: `/preview/vacancy/${record.id}`,
  }
}

/* --------------------------------------------------------------------------- categories ----- */

export function termRow(record: VacancyTermRecord, locale: string) {
  return {
    id: record.id,
    name: inPanel(record.title, locale) || record.name,
    title: record.title,
    slug: record.slug,
    // A group, not a page (decision 2): nothing to print where the other modules print an address.
    path: null,
    url: null,
    is_visible: record.is_visible,
    position: record.position,
    deleted_at: record.deleted_at,
    vacancies_count: vacancies.filter(
      (one) => one.deleted_at === null && ids(one.values.categories).includes(record.id),
    ).length,
  }
}

export function termDetail(record: VacancyTermRecord, locale: string) {
  return {
    category: termRow(record, locale),
    values: {
      ...record.extra,
      title: record.title,
      slug: record.slug,
      is_visible: record.is_visible,
    },
    prefix: null,
  }
}

/**
 * Decision 11: the slug is the key of `?category=` and is kept unique in every language among the
 * categories of vacancies, the bin included. Refused under `slug.<language>`, as the server does.
 */
function slugTaken(slug: LocalizedValue, except: number | null): Record<string, string[]> | null {
  const errors: Record<string, string[]> = {}

  for (const [locale, one] of Object.entries(slug)) {
    if (!one) continue

    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(one)) {
      errors[`slug.${locale}`] = ['Small latin letters, digits and dashes.']
    } else if (
      vacancyCategories.some((other) => other.id !== except && text(other.slug, locale) === one)
    ) {
      errors[`slug.${locale}`] = ['Another category already has this key.']
    }
  }

  return Object.keys(errors).length > 0 ? errors : null
}

export function createTerm(
  title: LocalizedValue,
  slug: string,
): VacancyTermRecord | { errors: Record<string, string[]> } {
  const keys = Object.fromEntries(Object.keys(title).map((locale) => [locale, slug]))
  const refused = slugTaken(keys, null)

  if (refused !== null) return { errors: refused }

  const id = Math.max(0, ...vacancyCategories.map((one) => one.id)) + 1
  const record: VacancyTermRecord = {
    id,
    name: Object.values(title)[0] ?? '',
    title,
    slug: keys,
    is_visible: true,
    position: vacancyCategories.length + 1,
    deleted_at: null,
    extra: {},
  }

  vacancyCategories.push(record)

  return record
}

export function writeTerm(
  record: VacancyTermRecord,
  values: Record<string, unknown>,
  locale: string,
): Record<string, string[]> | null {
  if (values.title !== undefined && !hasText(values.title)) {
    return { title: ['Название нужно хотя бы на одном языке.'] }
  }

  if (values.slug !== undefined) {
    const refused = slugTaken((values.slug ?? {}) as LocalizedValue, record.id)

    if (refused !== null) return refused
  }

  for (const [name, value] of Object.entries(values)) {
    if (name === 'title') record.title = (value ?? {}) as LocalizedValue
    else if (name === 'is_visible') record.is_visible = value === true
    else if (name === 'slug') record.slug = (value ?? {}) as LocalizedValue
    else record.extra[name] = value
  }

  record.name = inPanel(record.title, locale) || record.name

  return null
}

export function reorderTerms(order: number[]): void {
  const moved = order.map((id) => findTerm(id)).filter((one): one is VacancyTermRecord => !!one)
  const rest = vacancyCategories.filter((one) => !order.includes(one.id))

  vacancyCategories.splice(0, vacancyCategories.length, ...moved, ...rest)
  vacancyCategories.forEach((one, index) => {
    one.position = index + 1
  })
}

/* --------------------------------------------------------------------------------- site ----- */

const esc = (value: string): string =>
  value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

const SITE_WORDS = {
  closed: 'Вакансия закрыта',
  remote: 'Удалённо',
  hybrid: 'можно удалённо',
  until: 'Действует до',
  duties: 'Задачи',
  requirements: 'Требования',
  benefits: 'Мы предлагаем',
  employment: {
    FULL_TIME: 'Полная занятость',
    PART_TIME: 'Частичная занятость',
    CONTRACTOR: 'Договор подряда',
    TEMPORARY: 'Временная работа',
    INTERN: 'Стажировка',
    VOLUNTEER: 'Волонтёрство',
    PER_DIEM: 'Посуточно',
    OTHER: 'Другое',
  } as Record<string, string>,
  unit: {
    HOUR: 'в час',
    DAY: 'в день',
    WEEK: 'в неделю',
    MONTH: 'в месяц',
    YEAR: 'в год',
  } as Record<string, string>,
}

/**
 * The salary as the site prints it (§4.6): the editor's words when there are any, otherwise the
 * line made of the numbers — "40 000–60 000 ₴ в месяц". A currency taken off the list prints as
 * its code.
 */
function salaryLine(values: ScreenModel, locale: string): string {
  const words = text(values.salary, locale)

  if (words !== '') return words

  const min = typeof values.salary_min === 'number' ? values.salary_min : null
  const max = typeof values.salary_max === 'number' ? values.salary_max : null

  if (min === null && max === null) return ''

  const number = (value: number) => new Intl.NumberFormat('ru-RU').format(value)
  const code = String(values.salary_currency ?? '')
  const symbol = CURRENCIES[code] ?? code
  const range =
    min !== null && max !== null
      ? `${number(min)}–${number(max)}`
      : min !== null
        ? `от ${number(min)}`
        : `до ${number(max!)}`
  const unit = SITE_WORDS.unit[String(values.salary_unit ?? '')] ?? ''

  return [range, symbol, unit].filter((part) => part !== '').join(' ')
}

/**
 * The page of one vacancy (§4.6), out of its draft — this is a preview — in the order of the parts
 * of `vacancy.blade.php`: the position and the lead (with "closed" on a closed one), the facts, the
 * description, the three lists, and the place for the response, empty in the package.
 */
export function drawVacancyPage(record: VacancyRecord, locale = 'ru'): string {
  const values = record.values
  const t = (value: unknown) => text(value, locale)
  const shown = { ...record, live: values }
  const closed = closedReason(shown) !== null
  const workplace = String(values.workplace ?? 'onsite')

  const place =
    workplace === 'remote'
      ? SITE_WORDS.remote
      : [t(values.city), t(values.address), workplace === 'hybrid' ? SITE_WORDS.hybrid : '']
          .filter((part) => part !== '')
          .join(', ')

  const employment = (Array.isArray(values.employment_types) ? values.employment_types : [])
    .map((code: string) => SITE_WORDS.employment[code] ?? code)
    .join(', ')

  const until =
    typeof values.valid_through === 'string' && values.valid_through !== ''
      ? new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })
          .format(new Date(`${values.valid_through}T12:00:00`))
          .replace(/\s?г\.$/, '')
      : ''

  const categories = ids(values.categories)
    .map((id) => findTerm(id))
    .filter((one): one is VacancyTermRecord => one !== null && one.deleted_at === null)
    .map((one) => t(one.title))
    .filter((one) => one !== '')
    .join(', ')

  const facts = [
    place ? `<li>📍 ${esc(place)}</li>` : '',
    employment ? `<li>${esc(employment)}</li>` : '',
    salaryLine(values, locale) ? `<li>${esc(salaryLine(values, locale))}</li>` : '',
    until ? `<li>${SITE_WORDS.until} ${esc(until)}</li>` : '',
    categories ? `<li>${esc(categories)}</li>` : '',
  ].join('')

  const list = (name: (typeof LISTS)[number]) => {
    const found = (Array.isArray(values[name]) ? values[name] : [])
      .map((one: { text?: unknown }) => t(one.text))
      .filter((one: string) => one !== '')

    return found.length === 0
      ? ''
      : `<section><h2>${SITE_WORDS[name]}</h2><ul>${found.map((one: string) => `<li>${esc(one)}</li>`).join('')}</ul></section>`
  }

  return `<article class="site-wrap v-page">
    ${closed ? `<p class="v-closed">${SITE_WORDS.closed}</p>` : ''}
    <h1>${esc(t(values.title))}</h1>
    ${t(values.lead) ? `<p class="v-lead">${esc(t(values.lead))}</p>` : ''}
    ${facts ? `<ul class="v-facts">${facts}</ul>` : ''}
    ${t(values.description) ? `<section>${t(values.description)}</section>` : ''}
    ${list('duties')}
    ${list('requirements')}
    ${list('benefits')}
  </article>`
}

export const VACANCY_PAGE_STYLES = `
  .v-page { padding-block: 32px; max-width: 780px; }
  .v-page section { margin-top: 28px; }
  .v-page h1 { margin-top: 12px; }
  .v-closed { display: inline-block; margin: 0; padding: 2px 10px; border-radius: 999px; background: #eef0f4; color: #4b5263; }
  .v-lead { font-size: 19px; color: #4b5263; }
  .v-facts { display: flex; flex-wrap: wrap; gap: 8px 20px; padding: 0; list-style: none; color: #4b5263; }
  .v-page ul:not(.v-facts) { padding-left: 20px; }
  .v-page li + li { margin-top: 4px; }
`

/* ------------------------------------------------------------------------------ helpers ----- */

function restate(record: VacancyRecord): void {
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

function named(list: number[], locale: string): VacancyTermRef[] {
  return list.flatMap((id) => {
    const found = findTerm(id)

    return found === null ? [] : [{ id, title: inPanel(found.title, locale) || found.name }]
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

/* ------------------------------------------------------------------------ interim words ----- */

/**
 * What the server will say under `webx-vacancies::` once the php half is on this branch, and until
 * then only: `lang.ts` hands these out while `php/packages/module-vacancies/lang` does not exist.
 * The panel's groups come from the package's own English; the screen's words and the rest of the
 * site's group are here. V3 deletes this with the copies of the screens (`vacancies/*.json`).
 */
export const INTERIM_VACANCIES_WORDS: Record<string, Record<string, string>> = {
  ...(vacanciesMessages as Record<string, Record<string, string>>),
  vacancy: {
    ...(vacanciesMessages.vacancy as Record<string, string>),
    'unit-hour': 'per hour',
    'unit-day': 'per day',
    'unit-week': 'per week',
    'unit-month': 'per month',
    'unit-year': 'per year',
  },
  screen: {
    vacancy: 'Vacancy',
    where: 'Where',
    workplace: 'Where the work is done',
    city: 'City',
    address: 'Address',
    'address-help': 'The street, the district — whatever a candidate needs to find the office.',
    country: 'Country',
    'country-help': 'ISO: UA, PL. Only for search engines — the page prints the city.',
    terms: 'Terms',
    'employment-types': 'Employment',
    salary: 'Salary',
    'salary-help': 'In words, as the page prints it: “from ₴60,000”, “after the interview”.',
    'salary-min': 'From',
    'salary-max': 'To',
    'salary-unit': 'Per',
    'salary-currency': 'Currency',
    'salary-numbers-help':
      'The numbers are for search engines and the cards; the words above are what the page says.',
    about: 'About the job',
    description: 'Description',
    lists: 'Duties, requirements, what we offer',
    duties: 'Duties',
    'duties-help': 'One line each. An empty line is dropped.',
    requirements: 'Requirements',
    'requirements-help': 'One line each. An empty line is dropped.',
    benefits: 'What we offer',
    'benefits-help': 'One line each. An empty line is dropped.',
    line: 'Line',
    settings: 'Settings',
    naming: 'Name and address',
    title: 'Position',
    slug: 'Address',
    'slug-help': 'Changing it keeps the old address working: it leads to the new one.',
    lead: 'Lead',
    'lead-help': 'A sentence or two without formatting, for the cards and search engines.',
    taxonomy: 'Categories and response',
    categories: 'Categories',
    'categories-help': 'The groups the list of vacancies is split into, and its filter.',
    form: 'Response form',
    'form-help': 'The inbox form a candidate answers with. The site prints it on the page.',
    hiring: 'Hiring',
    'is-closed': 'The hiring is closed',
    'is-closed-help': 'Closed early: the vacancy leaves the lists, its page stays with a note.',
    'valid-through': 'Open until',
    'valid-through-help': 'The last day, included. After it the vacancy closes by itself.',
    'posted-at': 'Posted on',
    'posted-at-help':
      'For search engines. Set by the first publication; change it when the hiring opens again.',
    'posted-at-placeholder': 'Set when published',
    seo: 'SEO',
    'seo-empty': 'Install module-seo to write the title and description of this page.',
    history: 'History',
    'category-key-help':
      'In the address of the filter: ?category=… Small latin letters and dashes.',
    visible: 'Shown on the site',
    'category-visible-help': 'A hidden category is no group of the list and no filter.',
  },
}
