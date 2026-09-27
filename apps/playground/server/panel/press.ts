import { defineFunction } from './blade'
import { dictionary } from './lang'
import { fileByPath } from './media'

/**
 * The press (`module-press`): outlets, and the articles each of them printed about the site.
 *
 * The panel half answers the way §4.10 of the module's spec fixes it — the list whole and without
 * pages, the form's values by field name with the articles among them as rows, the order dragged
 * in the whole list (decision 6). Saving writes the rows the way `OutletForm::save()` does (§4.6):
 * a row with an id of this outlet is updated, a row without one is made, a row that is gone is
 * deleted, the order of the rows is the order of the articles — and a refusal anywhere writes
 * nothing, not even the rows that were fine.
 *
 * The site half is `press()`, the helper the offered blocks call (§4.8): outlets in their order,
 * articles of every outlet by date, seen only in a language they have a title in (decision 7).
 */

type Localized = Record<string, string>

export interface Article {
  id: number
  title: Localized
  excerpt: Localized
  kind: string | null
  /** `Y-m-d`. */
  published_on: string | null
  date_precision: 'day' | 'month' | 'year'
  /** Only `http(s)://`: an address anywhere else is not an article somebody can open. */
  url: string | null
  /** The value of `wx-file`: a key in the library, never an address. */
  file: { path: string } | null
  is_hidden: boolean
  extra: Record<string, unknown>
}

export interface Outlet {
  id: number
  title: Localized
  slug: Localized
  summary: Localized
  logo: { path: string } | null
  website_url: string | null
  featured: boolean
  published: boolean
  /** In their own order (`position`). */
  articles: Article[]
  seo: Record<string, unknown>
  updated_at: string
  deleted_at: string | null
  extra: Record<string, unknown>
}

const STAMP = '2026-09-27T09:00:00+00:00'
const LOCALES = ['ru', 'en']
/** The language a name falls back on (decision 7): the site's default one. */
const DEFAULT = 'ru'
/** `webx-press.prefix`: never empty (decision 11). */
const PREFIX = 'press'
/** `webx-press.kinds`, the default four (decision 3). */
const KINDS = ['mention', 'interview', 'expert_comment', 'authored']
const PRECISIONS = ['day', 'month', 'year']

let nextArticle = 1

/** In the general order (`position`). */
export const outlets: Outlet[] = [
  outlet(1, {
    title: { ru: 'Здоровье и стиль', en: 'Health & Style' },
    slug: { ru: 'zdorovye-i-stil', en: 'health-and-style' },
    summary: {
      ru: 'Ежемесячный журнал о здоровье, питании и образе жизни.',
      en: 'A monthly magazine on health, food and the way we live.',
    },
    logo: { path: 'press/health-style.svg' },
    website_url: 'https://health-style.example',
    featured: true,
    articles: [
      article({
        title: {
          ru: 'Интервью: как собрать тарелку на неделю',
          en: 'Interview: planning a week of plates',
        },
        excerpt: {
          ru: 'Разговор о том, почему меню на неделю проще, чем кажется.',
          en: 'On why a menu for the week is simpler than it looks.',
        },
        kind: 'interview',
        published_on: '2025-03-14',
        url: 'https://health-style.example/interview-week-plates',
      }),
      /* A link and a PDF: the link leads, the scan is offered beside it (decision 8). */
      article({
        title: { ru: 'Колонка: завтрак без сахара', en: 'Column: breakfast without sugar' },
        kind: 'authored',
        published_on: '2025-03-01',
        date_precision: 'month',
        url: 'https://health-style.example/column-breakfast',
        file: { path: 'press/health-style-2025-03.pdf' },
      }),
      /* Only in English: not on the Russian site at all (decision 7). */
      article({
        title: { ru: '', en: 'Ten kitchens worth a visit' },
        kind: 'mention',
        published_on: '2024-11-20',
        url: 'https://health-style.example/ten-kitchens',
      }),
    ],
  }),
  outlet(2, {
    title: { ru: 'Деловой вестник', en: 'Business Herald' },
    slug: { ru: 'delovoy-vestnik', en: 'business-herald' },
    summary: { ru: 'Деловая газета города.', en: 'The business paper of the city.' },
    logo: { path: 'press/business-herald.svg' },
    website_url: 'https://herald.example',
    featured: true,
    articles: [
      article({
        title: {
          ru: 'Комментарий эксперта: рынок здорового питания',
          en: 'Expert comment: the market of healthy food',
        },
        kind: 'expert_comment',
        published_on: '2023-01-01',
        date_precision: 'year',
        url: 'https://herald.example/healthy-food-market',
      }),
      /* A PDF only: the scan is the article. */
      article({
        title: { ru: 'Пять новых мест для обеда', en: 'Five new places for lunch' },
        kind: 'mention',
        published_on: '2024-08-12',
        date_precision: 'month',
        file: { path: 'press/health-style-2025-03.pdf' },
      }),
    ],
  }),
  /* Not in the logo strip: a strip of "featured only" leaves it out. */
  outlet(3, {
    title: { ru: 'Городской портал', en: 'City Portal' },
    slug: { ru: 'gorodskoy-portal', en: 'city-portal' },
    summary: { ru: '', en: '' },
    logo: { path: 'press/city-portal.svg' },
    website_url: 'https://city.example',
    articles: [
      article({
        title: { ru: 'Авторская колонка о сезонных продуктах', en: 'On seasonal produce' },
        kind: 'authored',
        published_on: '2024-06-05',
        url: 'https://city.example/seasonal',
      }),
      /* Hidden: kept with the outlet, and off the site. */
      article({
        title: { ru: 'Старая заметка', en: 'An old note' },
        kind: 'mention',
        published_on: '2019-02-10',
        url: 'https://city.example/old-note',
        is_hidden: true,
      }),
    ],
  }),
  /* Not published: on no page, whatever its articles are. */
  outlet(4, {
    title: { ru: 'Кухня недели', en: 'Kitchen Weekly' },
    slug: { ru: 'kukhnya-nedeli', en: 'kitchen-weekly' },
    summary: { ru: '', en: '' },
    logo: { path: 'press/kitchen-weekly.svg' },
    website_url: null,
    published: false,
    articles: [
      article({
        title: { ru: 'Рецепт недели', en: 'Recipe of the week' },
        kind: 'mention',
        published_on: '2025-05-02',
        url: 'https://kitchen.example/recipe',
      }),
    ],
  }),
  /* Published and seen nowhere: the one article it has is hidden — the list warns about it. */
  outlet(5, {
    title: { ru: 'Утренний эфир', en: '' },
    slug: { ru: 'utrenniy-efir', en: '' },
    summary: { ru: '', en: '' },
    logo: null,
    website_url: 'https://radio.example',
    articles: [
      article({
        title: { ru: 'Гость студии', en: '' },
        kind: 'interview',
        published_on: '2025-07-01',
        url: 'https://radio.example/guest',
        is_hidden: true,
      }),
    ],
  }),
]

function outlet(
  id: number,
  seed: Partial<Omit<Outlet, 'id'>> & Pick<Outlet, 'title' | 'slug' | 'summary' | 'articles'>,
): Outlet {
  return {
    logo: null,
    website_url: null,
    featured: false,
    published: true,
    seo: {},
    updated_at: STAMP,
    deleted_at: null,
    extra: {},
    ...seed,
    id,
  }
}

function article(seed: Partial<Omit<Article, 'id'>> & Pick<Article, 'title'>): Article {
  return {
    excerpt: {},
    kind: null,
    published_on: null,
    date_precision: 'day',
    url: null,
    file: null,
    is_hidden: false,
    extra: {},
    ...seed,
    id: nextArticle++,
  }
}

/* ------------------------------------------------------------------------------ panel ----- */

const live = (one: { deleted_at: string | null }): boolean => one.deleted_at === null

/** Seen in a language: not hidden, and a title in it — no other language stands in (decision 7). */
function seenIn(record: Article, locale: string): boolean {
  return !record.is_hidden && said(record.title[locale]) !== ''
}

/** Where the outlet can be seen, `published` aside: the languages any of its articles is seen in. */
function localesOf(record: Outlet): string[] {
  return LOCALES.filter((code) => record.articles.some((one) => seenIn(one, code)))
}

function logoOf(record: Outlet): { thumb: string | null } | null {
  const file = record.logo === null ? null : fileByPath(record.logo.path)

  return file === null ? null : { thumb: file.thumb }
}

/** One outlet as a line of the list (§4.10). */
export function outletRow(record: Outlet, locale: string): Record<string, unknown> {
  return {
    id: record.id,
    title: pick(record.title, locale) || `#${record.id}`,
    logo: logoOf(record),
    published: record.published,
    featured: record.featured,
    position: outlets.indexOf(record) + 1,
    locales: localesOf(record),
    articles_count: record.articles.length,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
  }
}

/** `GET /press`: the whole list, or the bin; a search over the name in any language. */
export function listOutlets(
  query: Record<string, string>,
  locale: string,
): Record<string, unknown> {
  let found: Outlet[]

  if (query.trashed === '1') {
    found = outlets
      .filter((one) => !live(one))
      .sort((a, b) => String(b.deleted_at).localeCompare(String(a.deleted_at)))
  } else {
    found = outlets.filter(live)

    const term = (query.search ?? '').trim().toLowerCase()

    if (term !== '') {
      found = found.filter((one) =>
        Object.values(one.title).some((words) => words.toLowerCase().includes(term)),
      )
    }
  }

  return { data: found.map((one) => outletRow(one, locale)) }
}

/**
 * The page of the outlet on the site, in the language of the panel — `null` where it has none.
 * The playground draws no outlet pages, so this is only ever the address a site would have.
 */
function urlOf(record: Outlet, locale: string): string | null {
  const code = LOCALES.includes(locale) ? locale : DEFAULT

  if (!record.published || !localesOf(record).includes(code) || said(record.slug[code]) === '') {
    return null
  }

  return `${code === DEFAULT ? '' : `/${code}`}/${PREFIX}/${record.slug[code]}`
}

/** One outlet as its form opens it (`OutletForm::describe()`, §4.10). */
export function outletDetail(record: Outlet, locale: string): Record<string, unknown> {
  return {
    outlet: {
      id: record.id,
      title: pick(record.title, locale) || `#${record.id}`,
      published: record.published,
      deleted_at: record.deleted_at,
      url: urlOf(record, locale),
    },
    values: {
      ...record.extra,
      title: { ...record.title },
      slug: { ...record.slug },
      summary: { ...record.summary },
      logo: record.logo === null ? null : { ...record.logo },
      website_url: record.website_url,
      featured: record.featured,
      published: record.published,
      articles: record.articles.map((one) => ({
        ...one.extra,
        id: one.id,
        title: { ...one.title },
        excerpt: { ...one.excerpt },
        kind: one.kind,
        published_on: one.published_on,
        date_precision: one.date_precision,
        url: one.url,
        file: one.file === null ? null : { ...one.file },
        is_hidden: one.is_hidden,
      })),
      seo: { ...record.seo },
    },
    prefix: PREFIX,
  }
}

export function findOutlet(id: number): Outlet | null {
  return outlets.find((one) => one.id === id) ?? null
}

/** The kinds as the server hands them to the field (§4.6): options, in the panel's language. */
export function kindOptions(locale: string): { value: string; label: string }[] {
  return KINDS.map((kind) => ({ value: kind, label: word(locale, `kinds.${kind}`) }))
}

/**
 * The values of `press.outlet-form`, checked and written — a new outlet when `record` is `null`.
 * What is refused comes back under the name of the field, an article's under
 * `articles.<n>.<field>`, as a 422 would carry it; and nothing is written then (§4.6).
 */
export function writeOutlet(
  record: Outlet | null,
  values: Record<string, unknown>,
): { record: Outlet } | { errors: Record<string, string[]> } {
  const errors: Record<string, string[]> = {}

  const website = values.website_url

  if (typeof website === 'string' && website.trim() !== '' && !/^https?:\/\//i.test(website)) {
    errors.website_url = ['Адрес должен начинаться с http:// или https://.']
  }

  for (const [code, words] of Object.entries(asMap(values.title))) {
    if (words.length > 255) errors[`title.${code}`] = ['Не длиннее 255 символов.']
  }

  const rows = values.articles === undefined ? undefined : asRows(values.articles)
  const own = new Set((record?.articles ?? []).map((one) => one.id))

  rows?.forEach((row, index) => {
    const at = `articles.${index}`

    if (row.id !== undefined && row.id !== null && !own.has(Number(row.id))) {
      errors.articles = ['Одна из строк — материал другого издания.']
    }

    for (const [code, words] of Object.entries(asMap(row.title))) {
      if (words.length > 255) errors[`${at}.title.${code}`] = ['Не длиннее 255 символов.']
    }

    const url = typeof row.url === 'string' ? row.url.trim() : ''
    const file = media(row.file)

    if (url !== '' && !/^https?:\/\//i.test(url)) {
      errors[`${at}.url`] = ['Адрес должен начинаться с http:// или https://.']
    } else if (url === '' && file === null) {
      errors[`${at}.url`] = ['Нужен адрес статьи или PDF.']
    }

    if (file !== null && fileByPath(file.path)?.mime !== 'application/pdf') {
      errors[`${at}.file`] = ['Это не PDF.']
    }

    if (row.kind !== undefined && row.kind !== null && row.kind !== '') {
      if (!KINDS.includes(String(row.kind))) errors[`${at}.kind`] = ['Такого вида нет.']
    }

    if (row.date_precision !== undefined && row.date_precision !== null) {
      if (!PRECISIONS.includes(String(row.date_precision))) {
        errors[`${at}.date_precision`] = ['День, месяц или год.']
      }
    }
  })

  if (Object.keys(errors).length > 0) return { errors }

  const target =
    record ??
    outlet(Math.max(0, ...outlets.map((one) => one.id)) + 1, {
      title: {},
      slug: {},
      summary: {},
      articles: [],
      published: false,
    })

  for (const [name, value] of Object.entries(values)) {
    if (name === 'title') target.title = asMap(value)
    else if (name === 'slug') target.slug = asMap(value)
    else if (name === 'summary') target.summary = asMap(value)
    else if (name === 'logo') target.logo = media(value)
    else if (name === 'website_url')
      target.website_url = typeof value === 'string' && value.trim() !== '' ? value.trim() : null
    else if (name === 'featured') target.featured = value === true
    else if (name === 'published') target.published = value === true
    else if (name === 'seo') target.seo = (value ?? {}) as Record<string, unknown>
    else if (name !== 'articles') target.extra[name] = value
  }

  // A language with a name and no address gets one out of the name, as the server does.
  for (const code of LOCALES) {
    if (said(target.slug[code]) === '' && said(target.title[code]) !== '') {
      target.slug[code] = slugify(target.title[code]!) || `outlet-${target.id}`
    }
  }

  if (rows !== undefined) {
    // In the order of the rows: that is the order of the articles (`position`).
    target.articles = rows.map((row) => {
      const kept = target.articles.find((one) => one.id === Number(row.id))
      const written = kept ?? article({ title: {} })
      const extra: Record<string, unknown> = { ...written.extra }

      for (const [name, value] of Object.entries(row)) {
        if (!ARTICLE_KEYS.includes(name)) extra[name] = value
      }

      return {
        ...written,
        title: asMap(row.title),
        excerpt: asMap(row.excerpt),
        kind: typeof row.kind === 'string' && row.kind !== '' ? row.kind : null,
        published_on: day(row.published_on),
        date_precision: PRECISIONS.includes(String(row.date_precision))
          ? (row.date_precision as Article['date_precision'])
          : 'day',
        url: typeof row.url === 'string' && row.url.trim() !== '' ? row.url.trim() : null,
        file: media(row.file),
        is_hidden: row.is_hidden === true,
        extra,
      }
    })
  }

  // A new outlet goes to the end of the general order (`max + 1`, §4.1).
  if (record === null) outlets.push(target)

  target.updated_at = new Date().toISOString()

  return { record: target }
}

const ARTICLE_KEYS = [
  'id',
  'title',
  'excerpt',
  'kind',
  'published_on',
  'date_precision',
  'url',
  'file',
  'is_hidden',
]

/** The order on screen, written whole. */
export function reorderOutlets(ids: number[]): void {
  const moved = ids.map((id) => findOutlet(id)).filter((one): one is Outlet => !!one)
  const rest = outlets.filter((one) => !ids.includes(one.id))

  outlets.splice(0, outlets.length, ...moved, ...rest)
}

/* ------------------------------------------------------------------------------- site ----- */

interface QueryState {
  what: 'outlets' | 'articles'
  featured: boolean
  kinds: string[]
  only: number[] | null
  except: number[]
  take: number | null
  locale: string
}

/**
 * `press()` as a template calls it (§4.8): each step hands back a new query, `get()` the cards.
 *
 * `kind()` takes one key or a list of them — the blocks hand it their `kinds` field whole, and an
 * empty list is every kind, like an untouched field.
 */
function query(state: QueryState): Record<string, (...args: unknown[]) => unknown> {
  const next = (patch: Partial<QueryState>) => query({ ...state, ...patch })
  const ids = (value: unknown): number[] =>
    (Array.isArray(value) ? value : [value])
      .map((one) => (typeof one === 'object' && one !== null ? (one as { id: unknown }).id : one))
      .map(Number)
      .filter((one) => Number.isFinite(one))

  return {
    outlets: () => next({ what: 'outlets' }),
    articles: () => next({ what: 'articles' }),
    featured: () => next({ featured: true }),
    kind: (value) =>
      next({
        kinds: (Array.isArray(value) ? value : value === null || value === undefined ? [] : [value])
          .map(String)
          .filter((one) => one !== ''),
      }),
    only: (value) => next({ only: ids(value) }),
    except: (value) => next({ except: ids(value) }),
    take: (value) => next({ take: typeof value === 'number' && value > 0 ? value : null }),
    locale: (value) => next({ locale: String(value) }),
    get: () => cards(state),
    first: () => cards({ ...state, take: 1 })[0] ?? null,
  }
}

function cards(state: QueryState): Record<string, unknown>[] {
  const { locale } = state
  const shown = (record: Outlet) =>
    live(record) && record.published && record.articles.some((one) => seenIn(one, locale))
  const wanted = (one: Article) => state.kinds.length === 0 || state.kinds.includes(one.kind ?? '')

  let found: Record<string, unknown>[]

  if (state.what === 'outlets') {
    let list = outlets.filter(shown)

    if (state.featured) list = list.filter((one) => one.featured)
    // An outlet of a kind: one that has an article of it, seen in the language (§4.8).
    if (state.kinds.length > 0) {
      list = list.filter((one) => one.articles.some((a) => seenIn(a, locale) && wanted(a)))
    }
    if (state.only !== null) {
      const only = state.only

      list = only.map((id) => list.find((one) => one.id === id)).filter((one) => !!one) as Outlet[]
    }

    found = list
      .filter((one) => !state.except.includes(one.id))
      .map((one) => outletCard(one, locale))
  } else {
    const list = outlets
      .filter(shown)
      .flatMap((one) =>
        one.articles.filter((a) => seenIn(a, locale) && wanted(a)).map((a) => ({ one, a })),
      )
      // By date, newest first; without a date — at the end (decision 6).
      .sort((x, y) => (y.a.published_on ?? '').localeCompare(x.a.published_on ?? ''))
      .filter(
        ({ a }) =>
          (state.only === null || state.only.includes(a.id)) && !state.except.includes(a.id),
      )

    found = list.map(({ one, a }) => articleCard(a, one, locale))
  }

  // The limit after visibility: "the first six" in Russian are six seen in Russian.
  return state.take === null ? found : found.slice(0, state.take)
}

function logoCard(record: Outlet, title: string): Record<string, unknown> | null {
  const file = record.logo === null ? null : fileByPath(record.logo.path)

  return file === null
    ? null
    : { url: file.url, width: file.width, height: file.height, alt: title }
}

/** An outlet as a template reads it (§4.8). */
function outletCard(record: Outlet, locale: string): Record<string, unknown> {
  const title = displayTitle(record, locale)
  const url = urlOf(record, locale)
  const seen = record.articles.filter((one) => seenIn(one, locale))

  return {
    id: record.id,
    url,
    link: url ?? record.website_url,
    title,
    summary: said(record.summary[locale]),
    logo: logoCard(record, title),
    website: record.website_url,
    featured: record.featured,
    count: seen.length,
    kinds: [...new Set(seen.map((one) => one.kind).filter((one) => one !== null))],
    fields: { ...record.extra },
  }
}

/** An article as a template reads it (§4.8). */
function articleCard(record: Article, owner: Outlet, locale: string): Record<string, unknown> {
  const title = displayTitle(owner, locale)
  const pdf = record.file === null ? null : (fileByPath(record.file.path)?.url ?? null)

  return {
    id: record.id,
    outlet: { id: owner.id, title, url: urlOf(owner, locale), logo: logoCard(owner, title) },
    title: record.title[locale],
    excerpt: said(record.excerpt[locale]),
    kind: record.kind,
    kind_label: record.kind === null ? '' : word(locale, `kinds.${record.kind}`),
    date: record.published_on,
    when: when(record.published_on, record.date_precision, locale),
    target: record.url ?? pdf,
    url: record.url,
    pdf,
    fields: { ...record.extra },
  }
}

/** The name in the language, else in any other — the default one first (decision 7). */
function displayTitle(record: Outlet, locale: string): string {
  return (
    said(record.title[locale]) ||
    said(record.title[DEFAULT]) ||
    LOCALES.map((code) => said(record.title[code])).find((one) => one !== '') ||
    ''
  )
}

/**
 * `Rendering\When` (§4.5): «12 августа 2023», «август 2023», «2023». `Intl` gives the Russian
 * month in the nominative for a month and a year, and adds «г.», which the site does not print.
 */
export function when(date: string | null, precision: string, locale: string): string {
  if (date === null) return ''

  const moment = new Date(`${date}T12:00:00Z`)
  const options: Intl.DateTimeFormatOptions =
    precision === 'year'
      ? { year: 'numeric', timeZone: 'UTC' }
      : precision === 'month'
        ? { month: 'long', year: 'numeric', timeZone: 'UTC' }
        : { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }

  return new Intl.DateTimeFormat(locale, options).format(moment).replace(/\s*г\.$/, '')
}

/*
 * What the offered blocks call, beside `press()` itself: the kinds of the config — a block with
 * nothing chosen groups by all of them — and the dictionary, for the heading of a group.
 */
defineFunction('press', () =>
  query({
    what: 'outlets',
    featured: false,
    kinds: [],
    only: null,
    except: [],
    take: null,
    locale: DEFAULT,
  }),
)
defineFunction('config', (key, fallback) => (key === 'webx-press.kinds' ? [...KINDS] : fallback))
defineFunction('__', (key) => {
  const [namespace, path] = String(key).split('::')

  return namespace === 'webx-press' && path !== undefined ? word(DEFAULT, path) : String(key)
})

/* ---------------------------------------------------------------------------- helpers ----- */

/** A line of the dictionary, `group.key`, in the language or in English. */
function word(locale: string, path: string): string {
  const [group, key] = path.split('.')

  for (const code of [locale, 'en']) {
    const value = dictionary(code)['webx-press']?.[group!]

    if (typeof value === 'object' && typeof value[key!] === 'string') return value[key!] as string
  }

  return path
}

/** The language asked for, or any other where it is empty — the panel names what it can. */
function pick(value: Localized, locale: string): string {
  return said(value[locale]) || said(value[DEFAULT]) || said(value.en)
}

function said(value: string | undefined): string {
  return (value ?? '').trim()
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

/** `Y-m-d` out of whatever the date picker sends — a day, or a moment on it. */
function day(value: unknown): string | null {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value) ? value.slice(0, 10) : null
}

function media(value: unknown): { path: string } | null {
  const path = (value as { path?: unknown } | null)?.path

  return typeof path === 'string' && path !== '' ? { path } : null
}

const LATIN: Record<string, string> = Object.fromEntries([
  ...'абвгдеёзийклмнопрстуфхцы'
    .split('')
    .map((letter, index) => [letter, 'abvgdeezijklmnoprstufhcy'.split('')[index]!]),
  ['ж', 'zh'],
  ['ч', 'ch'],
  ['ш', 'sh'],
  ['щ', 'sch'],
  ['ю', 'yu'],
  ['я', 'ya'],
  ['ъ', ''],
  ['ь', ''],
  ['э', 'e'],
] as [string, string][])

function slugify(text: string): string {
  return [...text.toLowerCase()]
    .map((letter) => LATIN[letter] ?? letter)
    .join('')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}
