import { slugify } from './pages'

/**
 * The FAQ (`module-faq`): questions, their flat categories, and the collection source blocks show
 * them through (`wx-collection`).
 *
 * The panel half answers the way `QuestionController` and the shared category API do — the list
 * whole and without pages, the form's values by field name, the order dragged either in the
 * whole list or inside one category (decision 4). The site half is `resolveFaq()`: what
 * `CollectionType::resolve()` answers on a site, by the rules of §3.1 — one chosen category in
 * its own order, anything else in the general one, without repeats — plus the groups of the
 * filter, which are the visible categories that still hold a shown record.
 */

type Localized = Record<string, string>

export interface FaqCategory {
  id: number
  title: Localized
  is_visible: boolean
  /** The questions in it, in the order they are dragged into inside it (`item_position`). */
  items: number[]
  deleted_at: string | null
  /** What a project patched onto `faq.category-form`. */
  extra: Record<string, unknown>
}

export interface FaqQuestion {
  id: number
  anchor: string
  question: Localized
  /** HTML, as `wx-rich-text` stores it. An empty language is a question not shown in it. */
  answer: Localized
  published: boolean
  updated_at: string
  deleted_at: string | null
  extra: Record<string, unknown>
}

const STAMP = '2026-09-24T09:00:00+00:00'

export const faqCategories: FaqCategory[] = [
  category(1, { ru: 'Оплата', en: 'Payment' }, [2, 1, 5]),
  category(2, { ru: 'Доставка', en: 'Delivery' }, [3, 4, 8]),
  category(3, { ru: 'Гарантия', en: 'Warranty' }, [6, 5, 9]),
  /* Hidden, so a filter over "all" shows three buttons and not four. */
  category(4, { ru: 'Для партнёров', en: 'For partners' }, [7], false),
]

/** In the general order (`position`). */
export const faqQuestions: FaqQuestion[] = [
  question(
    1,
    'kak-oplatit',
    { ru: 'Как оплатить заказ?', en: 'How do I pay for an order?' },
    {
      ru: '<p>Картой на сайте, переводом по счёту или наличными курьеру.</p>',
      en: '<p>By card on the site, by bank transfer or in cash to the courier.</p>',
    },
  ),
  question(
    2,
    'oplata-kartoy',
    { ru: 'Можно ли оплатить картой?', en: 'Can I pay by card?' },
    {
      ru: '<p>Да, <strong>Visa</strong> и <strong>Mastercard</strong>, без комиссии.</p>',
      en: '<p>Yes, <strong>Visa</strong> and <strong>Mastercard</strong>, with no fee.</p>',
    },
  ),
  question(
    3,
    'srok-dostavki',
    { ru: 'Сколько идёт доставка?', en: 'How long does delivery take?' },
    {
      ru: '<p>По городу — на следующий день, по стране — от двух до пяти дней.</p>',
      en: '<p>Next day in the city, two to five days across the country.</p>',
    },
  ),
  /* Only in Russian: decision 9 — no answer in a language, no question in it. */
  question(
    4,
    'samovyvoz',
    { ru: 'Есть ли самовывоз?', en: '' },
    { ru: '<p>Да, из офиса на Крещатике, 22, по будням с 9 до 18.</p>', en: '' },
  ),
  /* In two categories: the one question a block over both must not show twice. */
  question(
    5,
    'vozvrat-deneg',
    { ru: 'Как вернуть деньги?', en: 'How do I get a refund?' },
    {
      ru: '<p>Напишите нам в течение 14 дней — вернём на ту же карту.</p>',
      en: '<p>Write to us within 14 days and we refund to the same card.</p>',
    },
  ),
  question(
    6,
    'garantiya',
    { ru: 'Какая гарантия?', en: 'What is the warranty?' },
    {
      ru: '<p>Двенадцать месяцев на всё, что мы сделали.</p>',
      en: '<p>Twelve months on everything we made.</p>',
    },
  ),
  /* A draft: never shown, whatever is chosen. */
  question(
    7,
    'optovye-ceny',
    { ru: 'Есть ли оптовые цены?', en: 'Are there wholesale prices?' },
    { ru: '<p>Да, от десяти штук.</p>', en: '<p>Yes, from ten items.</p>' },
    false,
  ),
  question(
    8,
    'dostavka-za-granicu',
    { ru: 'Доставляете ли вы за границу?', en: 'Do you ship abroad?' },
    {
      ru: '<p>В страны ЕС — да, <a href="/contacts">напишите нам</a> для расчёта.</p>',
      en: '<p>Within the EU, yes — <a href="/contacts">write to us</a> for a quote.</p>',
    },
  ),
  /* Published and seen nowhere: a question with no answer in any language. */
  question(
    9,
    'garantiya-na-remont',
    { ru: 'Даёте ли гарантию на ремонт?', en: '' },
    { ru: '', en: '' },
  ),
]

function category(id: number, title: Localized, items: number[], visible = true): FaqCategory {
  return { id, title, is_visible: visible, items, deleted_at: null, extra: {} }
}

function question(
  id: number,
  anchor: string,
  text: Localized,
  answer: Localized,
  published = true,
): FaqQuestion {
  return {
    id,
    anchor,
    question: text,
    answer,
    published,
    updated_at: STAMP,
    deleted_at: null,
    extra: {},
  }
}

/* ------------------------------------------------------------------------------ panel ----- */

/** `GET /api/cms/collections`, as `CollectionController` answers it. */
export function collectionSources(locale: string): unknown[] {
  return [
    {
      key: 'faq',
      title: locale === 'ru' ? 'Вопросы и ответы' : 'FAQ',
      categories: 'faq/categories',
      markup: true,
    },
    {
      key: 'reviews',
      title: locale === 'ru' ? 'Отзывы' : 'Reviews',
      categories: 'reviews/categories',
      markup: false,
    },
    {
      key: 'services',
      title: locale === 'ru' ? 'Услуги' : 'Services',
      categories: 'services/categories',
      markup: false,
    },
  ]
}

const live = (one: { deleted_at: string | null }): boolean => one.deleted_at === null

/** The categories a question is in, in the order of the category list. */
function categoriesOf(id: number): FaqCategory[] {
  return faqCategories.filter((one) => live(one) && one.items.includes(id))
}

/** Where the question can be seen: both halves written in the language (decision 9). */
function localesOf(record: FaqQuestion): string[] {
  return ['ru', 'en'].filter(
    (code) => text(record.question[code]) !== '' && text(record.answer[code]) !== '',
  )
}

/** One question as `QuestionResource` draws it. */
export function questionRow(record: FaqQuestion, locale: string): Record<string, unknown> {
  return {
    id: record.id,
    question: pick(record.question, locale) || `#${record.id}`,
    anchor: record.anchor,
    published: record.published,
    position: faqQuestions.indexOf(record) + 1,
    locales: localesOf(record),
    categories: categoriesOf(record.id).map((one) => ({
      id: one.id,
      title: pick(one.title, locale),
    })),
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
  }
}

/** `GET /faq/questions`: the whole list, or a category's in its own order, or the bin. */
export function listQuestions(
  query: Record<string, string>,
  locale: string,
): Record<string, unknown> {
  let found: FaqQuestion[]

  if (query.trashed === '1') {
    found = faqQuestions
      .filter((one) => !live(one))
      .sort((a, b) => String(b.deleted_at).localeCompare(String(a.deleted_at)))
  } else {
    const inside = query.category ? findCategory(Number(query.category)) : null

    found = (
      inside === null
        ? faqQuestions
        : inside.items
            .map((id) => findQuestion(id))
            .filter((one): one is FaqQuestion => one !== null)
    ).filter(live)

    const term = (query.search ?? '').trim().toLowerCase()

    if (term !== '') {
      found = found.filter(
        (one) =>
          one.anchor.includes(term) ||
          Object.values(one.question).some((said) => said.toLowerCase().includes(term)),
      )
    }
  }

  return {
    data: found.map((one) => questionRow(one, locale)),
    filters: {
      categories: faqCategories
        .filter(live)
        .map((one) => ({ id: one.id, title: pick(one.title, locale) })),
    },
  }
}

/** One question as its form opens it (`QuestionForm::describe()`). */
export function questionDetail(record: FaqQuestion, locale: string): Record<string, unknown> {
  return {
    question: questionRow(record, locale),
    values: {
      ...record.extra,
      question: { ...record.question },
      answer: { ...record.answer },
      published: record.published,
      categories: categoriesOf(record.id).map((one) => one.id),
    },
  }
}

export function findQuestion(id: number): FaqQuestion | null {
  return faqQuestions.find((one) => one.id === id) ?? null
}

export function findCategory(id: number): FaqCategory | null {
  return faqCategories.find((one) => one.id === id && live(one)) ?? null
}

/**
 * The values of `faq.form`, checked and written — a new question when `record` is `null`. What
 * is refused comes back under the name of the field, as a 422 would carry it, and nothing is
 * written then: not even the new row (the server does it in a transaction).
 */
export function writeQuestion(
  record: FaqQuestion | null,
  values: Record<string, unknown>,
): { record: FaqQuestion } | { errors: Record<string, string[]> } {
  const errors: Record<string, string[]> = {}
  const chosen = Array.isArray(values.categories) ? values.categories.map(Number) : undefined

  if (chosen?.some((id) => findCategory(id) === null)) {
    errors.categories = ['Одной из этих категорий больше нет.']
  }

  for (const [code, said] of Object.entries(asMap(values.question))) {
    if (said.length > 500) errors[`question.${code}`] = ['Не длиннее 500 символов.']
  }

  if (Object.keys(errors).length > 0) return { errors }

  const target =
    record ?? question(Math.max(0, ...faqQuestions.map((one) => one.id)) + 1, '', {}, {}, false)

  for (const [name, value] of Object.entries(values)) {
    if (name === 'question') target.question = asMap(value)
    else if (name === 'answer') target.answer = asMap(value)
    else if (name === 'published') target.published = value === true
    else if (name !== 'categories') target.extra[name] = value
  }

  if (record === null) {
    // Once, from the question in the default language, and never again (decision 10).
    target.anchor = uniqueAnchor(slugify(text(target.question.ru)), target.id)
    faqQuestions.push(target)
  }

  if (chosen !== undefined) {
    for (const one of faqCategories) {
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

function uniqueAnchor(base: string, id: number): string {
  if (base === '') return `q-${id}`

  const taken = new Set(faqQuestions.map((one) => one.anchor))
  let anchor = base

  for (let n = 2; taken.has(anchor); n++) anchor = `${base}-${n}`

  return anchor
}

/** The order on screen, written whole: the general one, or one category's. */
export function reorderQuestions(ids: number[], categoryId: number | null): void {
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

  const moved = ids.map((id) => findQuestion(id)).filter((one): one is FaqQuestion => !!one)
  const rest = faqQuestions.filter((one) => !ids.includes(one.id))

  faqQuestions.splice(0, faqQuestions.length, ...moved, ...rest)
}

/* ------------------------------------------------------------------------- categories ----- */

/** One category as the shared category API answers it (`CategoryResource`): no address, no SEO. */
export function faqCategoryRow(record: FaqCategory, locale: string): Record<string, unknown> {
  return {
    id: record.id,
    name: pick(record.title, locale) || `#${record.id}`,
    title: record.title,
    slug: null,
    path: null,
    url: null,
    is_visible: record.is_visible,
    position: faqCategories.indexOf(record) + 1,
    deleted_at: record.deleted_at,
    questions_count: record.items.filter((id) => {
      const one = findQuestion(id)

      return one !== null && live(one)
    }).length,
  }
}

export function faqCategoryDetail(record: FaqCategory, locale: string): Record<string, unknown> {
  return {
    category: faqCategoryRow(record, locale),
    values: { ...record.extra, title: { ...record.title }, is_visible: record.is_visible },
    prefix: null,
  }
}

export function createCategory(title: unknown): FaqCategory {
  const record = category(Math.max(0, ...faqCategories.map((one) => one.id)) + 1, asMap(title), [])

  faqCategories.push(record)

  return record
}

export function writeCategory(
  record: FaqCategory,
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
  const moved = ids.map((id) => findCategory(id)).filter((one): one is FaqCategory => !!one)
  const rest = faqCategories.filter((one) => !ids.includes(one.id))

  faqCategories.splice(0, faqCategories.length, ...moved, ...rest)
}

/* ------------------------------------------------------------------------------- site ----- */

/**
 * A stored choice, read the way the site reads it. Anything malformed is the default — which is
 * also what an untouched block holds: nothing, meaning everything (`ResolvesMissing`).
 */
export function resolveFaq(stored: unknown, locale = 'ru'): Record<string, unknown> {
  const value = (typeof stored === 'object' && stored !== null ? stored : {}) as Record<
    string,
    unknown
  >
  const chosen = Array.isArray(value.categories) ? value.categories.map(Number) : []
  const limit = typeof value.limit === 'number' && value.limit >= 1 ? value.limit : null
  const filter = value.filter === true

  const shown = (record: FaqQuestion): boolean =>
    live(record) && record.published && localesOf(record).includes(locale)

  const inCategories = (id: number): number[] => categoriesOf(id).map((one) => one.id)

  let ordered: FaqQuestion[]

  if (chosen.length === 1) {
    ordered = (findCategory(chosen[0]!)?.items ?? [])
      .map((id) => findQuestion(id))
      .filter((one): one is FaqQuestion => one !== null)
  } else {
    ordered = faqQuestions.filter(
      (record) => chosen.length === 0 || inCategories(record.id).some((id) => chosen.includes(id)),
    )
  }

  const items = ordered
    .filter(shown)
    .slice(0, limit ?? undefined)
    .map((record) => ({
      id: record.id,
      anchor: record.anchor,
      categories: inCategories(record.id),
      question: record.question[locale],
      answer: record.answer[locale],
    }))

  const groups = filter
    ? faqCategories
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

/* ---------------------------------------------------------------------------- helpers ----- */

/** The language asked for, or another where it is empty — the panel names what it can. */
function pick(value: Localized, locale: string): string {
  return text(value[locale]) || text(value.ru) || text(value.en)
}

/** Written, as the server counts it: a paragraph with nothing in it is not an answer. */
function text(value: string | undefined): string {
  return (value ?? '').replace(/<[^>]*>/g, '').trim() === '' ? '' : (value ?? '')
}

function asMap(value: unknown): Localized {
  if (typeof value === 'string') return { ru: value }
  if (value === null || typeof value !== 'object') return {}

  const out: Localized = {}

  for (const [code, said] of Object.entries(value as Record<string, unknown>)) {
    out[code] = typeof said === 'string' ? said : ''
  }

  return out
}
