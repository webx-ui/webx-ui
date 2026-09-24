import { fileByPath } from './media'

/**
 * The reviews (`module-reviews`): reviews, their flat categories, and the collection source blocks
 * show them through (`wx-collection`).
 *
 * The panel half answers the way §4.7 of the module's spec fixes it — the list whole and without
 * pages, the form's values by field name, the order dragged either in the whole list or inside
 * one category (decision 6). The site half is `resolveReviews()`: what `ReviewsSource` answers on
 * a site — one chosen category in its own order, anything else in the general one, without
 * repeats; seen only with a text in the language (decision 7); the limit after that — as cards of
 * §4.3, plus the groups of the filter.
 */

type Localized = Record<string, string>

export interface ReviewCategory {
  id: number
  title: Localized
  is_visible: boolean
  /** The reviews in it, in the order they are dragged into inside it (`item_position`). */
  items: number[]
  deleted_at: string | null
  /** What a project patched onto `reviews.category-form`. */
  extra: Record<string, unknown>
}

export interface Review {
  id: number
  name: Localized
  job_title: Localized
  /** Plain text, paragraphs by line breaks. An empty language is a review not shown in it. */
  text: Localized
  rating: number | null
  /** `Y-m-d`: shown if a template wants it, and nothing else (decision 4). */
  reviewed_on: string | null
  profile_url: string | null
  /** The value of `wx-media`: a key in the library, never an address. */
  photo: { path: string } | null
  published: boolean
  updated_at: string
  deleted_at: string | null
  extra: Record<string, unknown>
}

const STAMP = '2026-09-24T09:00:00+00:00'
const LOCALES = ['ru', 'en']
/** The language a name falls back on (decision 7): the site's default one. */
const DEFAULT = 'ru'

export const reviewCategories: ReviewCategory[] = [
  category(1, { ru: 'Клиника', en: 'The clinic' }, [1, 2, 4, 5, 7]),
  category(2, { ru: 'Имплантация', en: 'Implants' }, [3, 6, 2]),
  /* Hidden: a filter over "all" shows two buttons and not three. */
  category(3, { ru: 'На главную', en: 'For the home page' }, [4, 1, 3], false),
]

/** In the general order (`position`). */
export const reviews: Review[] = [
  review(1, {
    name: { ru: 'Анна Петрова', en: 'Anna Petrova' },
    job_title: { ru: 'Директор, «Акме»', en: 'CEO, Acme' },
    text: {
      ru: 'Пришла с одной проблемой, ушла без трёх. Всё объяснили до того, как начать, и сделали ровно так, как объяснили.',
      en: 'Came in with one problem and left without three. Everything was explained before it began, and done exactly as explained.',
    },
    rating: 5,
    reviewed_on: '2026-09-12',
    photo: { path: 'reviews/anna-petrova.svg' },
  }),
  /* In two categories: the one review a block over both must not show twice. */
  review(2, {
    name: { ru: 'Игорь Мельник', en: 'Ihor Melnyk' },
    job_title: { ru: 'Архитектор', en: 'Architect' },
    text: {
      ru: 'Боялся имплантации десять лет.\nЗря: два визита, и никакой боли.',
      en: 'I was afraid of implants for ten years.\nFor nothing: two visits, no pain.',
    },
    rating: 4,
    reviewed_on: '2026-08-30',
    profile_url: 'https://www.linkedin.com/in/example',
    photo: { path: 'reviews/ihor-melnyk.svg' },
  }),
  /* A name in Russian only: shown on the English site under the Russian one (decision 7). */
  review(3, {
    name: { ru: 'Ольга Сидоренко', en: '' },
    job_title: { ru: '', en: '' },
    text: {
      ru: 'Поставили два импланта за один день. Через месяц уже не помню, какие зубы свои.',
      en: 'Two implants in one day. A month later I cannot tell which teeth are mine.',
    },
    rating: 5,
    reviewed_on: '2026-07-18',
  }),
  /* No rating at all: no stars, rather than five empty ones. */
  review(4, {
    name: { ru: 'Марко Росси', en: 'Marco Rossi' },
    job_title: { ru: 'Шеф-повар', en: 'Chef' },
    text: {
      ru: 'Лучший администратор, какого я видел в клиниках: перезвонила сама, напомнила, перенесла.',
      en: 'The best front desk I have seen in a clinic: called back herself, reminded me, moved the visit.',
    },
    rating: null,
  }),
  /* Only in Russian: no text in English, so not on the English site at all. */
  review(5, {
    name: { ru: 'Татьяна Ковальчук', en: 'Tetiana Kovalchuk' },
    job_title: { ru: 'Учитель', en: 'Teacher' },
    text: { ru: 'Дети больше не боятся стоматолога. Это о многом говорит.', en: '' },
    rating: 5,
  }),
  review(6, {
    name: { ru: 'Сергей Бондаренко', en: 'Serhii Bondarenko' },
    job_title: { ru: 'Инженер', en: 'Engineer' },
    text: {
      ru: 'Долго, но качественно. Три звезды за ожидание в коридоре, остальное — отлично.',
      en: 'Slow, but well done. Three stars for the wait in the corridor; the rest was excellent.',
    },
    rating: 3,
    reviewed_on: '2026-06-02',
  }),
  /* A draft: never shown, whatever is chosen. */
  review(7, {
    name: { ru: 'Дмитрий Лысенко', en: 'Dmytro Lysenko' },
    job_title: { ru: '', en: '' },
    text: { ru: 'Черновик: дописать после второго визита.', en: '' },
    rating: 4,
    published: false,
  }),
  /* Published and seen nowhere: a review with no text in any language. */
  review(8, {
    name: { ru: 'Елена Гончар', en: 'Olena Honchar' },
    job_title: { ru: 'Фотограф', en: 'Photographer' },
    text: { ru: '', en: '' },
    rating: 5,
  }),
]

function category(id: number, title: Localized, items: number[], visible = true): ReviewCategory {
  return { id, title, is_visible: visible, items, deleted_at: null, extra: {} }
}

function review(
  id: number,
  seed: Partial<Omit<Review, 'id' | 'name' | 'job_title' | 'text'>> &
    Pick<Review, 'name' | 'job_title' | 'text'>,
): Review {
  return {
    rating: null,
    reviewed_on: null,
    profile_url: null,
    photo: null,
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

/** The categories a review is in, in the order of the category list. */
function categoriesOf(id: number): ReviewCategory[] {
  return reviewCategories.filter((one) => live(one) && one.items.includes(id))
}

/** Where the review can be seen: its text written in the language (decision 7). */
function localesOf(record: Review): string[] {
  return LOCALES.filter((code) => said(record.text[code]) !== '')
}

/** The photo as the list draws it: the thumbnail, found in the library by its key. */
function photoOf(record: Review): { thumb: string | null } | null {
  const file = record.photo === null ? null : fileByPath(record.photo.path)

  return file === null ? null : { thumb: file.thumb }
}

/** One review as `ReviewResource` draws it (§4.7). */
export function reviewRow(record: Review, locale: string): Record<string, unknown> {
  return {
    id: record.id,
    name: pick(record.name, locale) || `#${record.id}`,
    job_title: pick(record.job_title, locale) || null,
    rating: record.rating,
    photo: photoOf(record),
    published: record.published,
    position: reviews.indexOf(record) + 1,
    locales: localesOf(record),
    categories: categoriesOf(record.id).map((one) => ({
      id: one.id,
      title: pick(one.title, locale),
    })),
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
  }
}

/** `GET /reviews`: the whole list, or a category's in its own order, or the bin. */
export function listReviews(
  query: Record<string, string>,
  locale: string,
): Record<string, unknown> {
  let found: Review[]

  if (query.trashed === '1') {
    found = reviews
      .filter((one) => !live(one))
      .sort((a, b) => String(b.deleted_at).localeCompare(String(a.deleted_at)))
  } else {
    const inside = query.category ? findCategory(Number(query.category)) : null

    found = (
      inside === null
        ? reviews
        : inside.items.map((id) => findReview(id)).filter((one): one is Review => one !== null)
    ).filter(live)

    const term = (query.search ?? '').trim().toLowerCase()

    if (term !== '') {
      found = found.filter((one) =>
        [one.name, one.job_title, one.text].some((map) =>
          Object.values(map).some((words) => words.toLowerCase().includes(term)),
        ),
      )
    }
  }

  return {
    data: found.map((one) => reviewRow(one, locale)),
    filters: {
      categories: reviewCategories
        .filter(live)
        .map((one) => ({ id: one.id, title: pick(one.title, locale) })),
    },
  }
}

/** One review as its form opens it (`ReviewForm::describe()`). */
export function reviewDetail(record: Review, locale: string): Record<string, unknown> {
  return {
    review: {
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
      rating: record.rating,
      reviewed_on: record.reviewed_on,
      profile_url: record.profile_url,
      photo: record.photo === null ? null : { ...record.photo },
      published: record.published,
      categories: categoriesOf(record.id).map((one) => one.id),
    },
  }
}

export function findReview(id: number): Review | null {
  return reviews.find((one) => one.id === id) ?? null
}

export function findCategory(id: number): ReviewCategory | null {
  return reviewCategories.find((one) => one.id === id && live(one)) ?? null
}

/**
 * The values of `reviews.form`, checked and written — a new review when `record` is `null`. What
 * is refused comes back under the name of the field, as a 422 would carry it, and nothing is
 * written then: not even the new row (the server does it in a transaction).
 */
export function writeReview(
  record: Review | null,
  values: Record<string, unknown>,
): { record: Review } | { errors: Record<string, string[]> } {
  const errors: Record<string, string[]> = {}
  const chosen = Array.isArray(values.categories) ? values.categories.map(Number) : undefined

  if (chosen?.some((id) => findCategory(id) === null)) {
    errors.categories = ['Одной из этих категорий больше нет.']
  }

  const rating = values.rating

  if (
    rating !== undefined &&
    rating !== null &&
    rating !== 0 &&
    !(Number.isInteger(rating) && (rating as number) >= 1 && (rating as number) <= 5)
  ) {
    errors.rating = ['Оценка — целое число от 1 до 5.']
  }

  const profile = values.profile_url

  if (typeof profile === 'string' && profile.trim() !== '' && !/^https?:\/\//i.test(profile)) {
    errors.profile_url = ['Ссылка должна начинаться с http:// или https://.']
  }

  for (const [code, words] of Object.entries(asMap(values.name))) {
    if (words.length > 255) errors[`name.${code}`] = ['Не длиннее 255 символов.']
  }

  if (Object.keys(errors).length > 0) return { errors }

  const target =
    record ??
    review(Math.max(0, ...reviews.map((one) => one.id)) + 1, {
      name: {},
      job_title: {},
      text: {},
      published: false,
    })

  for (const [name, value] of Object.entries(values)) {
    if (name === 'name') target.name = asMap(value)
    else if (name === 'job_title') target.job_title = asMap(value)
    else if (name === 'text') target.text = asMap(value)
    // A cleared `wx-rate` is a zero: no stars, which the server stores as nothing.
    else if (name === 'rating')
      target.rating = typeof value === 'number' && value > 0 ? value : null
    else if (name === 'reviewed_on') target.reviewed_on = day(value)
    else if (name === 'profile_url')
      target.profile_url = typeof value === 'string' && value.trim() !== '' ? value.trim() : null
    else if (name === 'photo') target.photo = media(value)
    else if (name === 'published') target.published = value === true
    else if (name !== 'categories') target.extra[name] = value
  }

  // A new review goes to the end of the general order (`max + 1`, §4.1).
  if (record === null) reviews.push(target)

  if (chosen !== undefined) {
    for (const one of reviewCategories) {
      const inside = one.items.includes(target.id)
      const wanted = chosen.includes(one.id)

      // A new member goes to the end of the category, the way the general order has it.
      if (wanted && !inside) one.items.push(target.id)
      if (!wanted && inside) one.items.splice(one.items.indexOf(target.id), 1)
    }
  }

  target.updated_at = new Date().toISOString()

  return { record: target }
}

/** The order on screen, written whole: the general one, or one category's. */
export function reorderReviews(ids: number[], categoryId: number | null): void {
  if (categoryId !== null) {
    const inside = findCategory(categoryId)

    if (inside !== null) {
      inside.items = [
        ...ids.filter((id) => inside.items.includes(id)),
        ...inside.items.filter((id) => !ids.includes(id)),
      ]
    }

    return
  }

  const moved = ids.map((id) => findReview(id)).filter((one): one is Review => !!one)
  const rest = reviews.filter((one) => !ids.includes(one.id))

  reviews.splice(0, reviews.length, ...moved, ...rest)
}

/* ------------------------------------------------------------------------- categories ----- */

/** One category as the shared category API answers it (`CategoryResource`): no address, no SEO. */
export function reviewCategoryRow(record: ReviewCategory, locale: string): Record<string, unknown> {
  return {
    id: record.id,
    name: pick(record.title, locale) || `#${record.id}`,
    title: record.title,
    slug: null,
    path: null,
    url: null,
    is_visible: record.is_visible,
    position: reviewCategories.indexOf(record) + 1,
    deleted_at: record.deleted_at,
    reviews_count: record.items.filter((id) => {
      const one = findReview(id)

      return one !== null && live(one)
    }).length,
  }
}

export function reviewCategoryDetail(
  record: ReviewCategory,
  locale: string,
): Record<string, unknown> {
  return {
    category: reviewCategoryRow(record, locale),
    values: { ...record.extra, title: { ...record.title }, is_visible: record.is_visible },
    prefix: null,
  }
}

export function createCategory(title: unknown): ReviewCategory {
  const record = category(
    Math.max(0, ...reviewCategories.map((one) => one.id)) + 1,
    asMap(title),
    [],
  )

  reviewCategories.push(record)

  return record
}

export function writeCategory(
  record: ReviewCategory,
  values: Record<string, unknown>,
): Record<string, string[]> | null {
  if (values.title !== undefined && Object.values(asMap(values.title)).every((one) => !one)) {
    return { title: ['Название нужно хотя бы на одном языке.'] }
  }

  for (const [name, value] of Object.entries(values)) {
    if (name === 'title') record.title = asMap(value)
    else if (name === 'is_visible') record.is_visible = value === true
    else record.extra[name] = value
  }

  return null
}

export function reorderCategories(ids: number[]): void {
  const moved = ids.map((id) => findCategory(id)).filter((one): one is ReviewCategory => !!one)
  const rest = reviewCategories.filter((one) => !ids.includes(one.id))

  reviewCategories.splice(0, reviewCategories.length, ...moved, ...rest)
}

/* ------------------------------------------------------------------------------- site ----- */

/**
 * A stored choice, read the way the site reads it (`ReviewsSource::items()`), as cards of §4.3.
 * Anything malformed is the default — which is also what an untouched block holds: nothing,
 * meaning everything (`ResolvesMissing`).
 */
export function resolveReviews(stored: unknown, locale = 'ru'): Record<string, unknown> {
  const value = (typeof stored === 'object' && stored !== null ? stored : {}) as Record<
    string,
    unknown
  >
  const chosen = Array.isArray(value.categories) ? value.categories.map(Number) : []
  const limit = typeof value.limit === 'number' && value.limit >= 1 ? value.limit : null
  const filter = value.filter === true

  const shown = (record: Review): boolean =>
    live(record) && record.published && said(record.text[locale]) !== ''

  const inCategories = (id: number): number[] => categoriesOf(id).map((one) => one.id)

  let ordered: Review[]

  if (chosen.length === 1) {
    ordered = (findCategory(chosen[0]!)?.items ?? [])
      .map((id) => findReview(id))
      .filter((one): one is Review => one !== null)
  } else {
    ordered = reviews.filter(
      (record) => chosen.length === 0 || inCategories(record.id).some((id) => chosen.includes(id)),
    )
  }

  // The limit after visibility: "the first six" in Russian are six Russian reviews (§4.4).
  const items = ordered
    .filter(shown)
    .slice(0, limit ?? undefined)
    .map((record) => card(record, locale, inCategories(record.id)))

  const groups = filter
    ? reviewCategories
        .filter((one) => live(one) && one.is_visible)
        .filter((one) => chosen.length === 0 || chosen.includes(one.id))
        .map((one) => ({
          id: one.id,
          title: pick(one.title, locale),
          items: items.filter((item) => item.categories.includes(one.id)).map((i) => i.id),
        }))
        .filter((group) => group.items.length > 0)
    : []

  return { items, groups, filter }
}

/** A review as a template reads it — `Rendering\Cards`, §4.3. */
function card(record: Review, locale: string, categories: number[]) {
  const file = record.photo === null ? null : fileByPath(record.photo.path)
  const name = said(record.name[locale]) || said(record.name[DEFAULT])

  return {
    id: record.id,
    anchor: `review-${record.id}`,
    categories,
    name,
    // The first letters of the first two words, by letters and not by bytes (§4.3).
    initials: name
      .split(/\s+/)
      .filter((word) => word !== '')
      .slice(0, 2)
      .map((word) => Array.from(word)[0]!.toUpperCase())
      .join(''),
    job_title: said(record.job_title[locale]) || said(record.job_title[DEFAULT]),
    text: record.text[locale]!,
    rating: record.rating,
    date: record.reviewed_on,
    profile: record.profile_url,
    photo:
      file === null
        ? null
        : { url: file.url, thumb: file.thumb, width: file.width, height: file.height, alt: name },
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

function asMap(value: unknown): Localized {
  if (typeof value === 'string') return { [DEFAULT]: value }
  if (value === null || typeof value !== 'object') return {}

  const out: Localized = {}

  for (const [code, words] of Object.entries(value as Record<string, unknown>)) {
    out[code] = typeof words === 'string' ? words : ''
  }

  return out
}

/** `Y-m-d` out of whatever the date picker sends — a day, or a moment on it. */
function day(value: unknown): string | null {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value) ? value.slice(0, 10) : null
}

function media(value: unknown): { path: string } | null {
  const path = (value as { path?: unknown } | null)?.path

  return typeof path === 'string' && path !== '' ? { path } : null
}
