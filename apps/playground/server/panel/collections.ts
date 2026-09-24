/**
 * A section blocks can show records from (`wx-collection`), standing in for the FAQ module until
 * it has fixtures of its own — the contract is built before its first consumer (§3 of
 * `WEBX_UI_MODULE_FAQ.md`), and a field nobody can try is a field nobody has looked at.
 *
 * What it answers is what `CollectionType::resolve()` answers on a site: the records by the rules
 * of §3.1 — one chosen category in its own order, anything else in the general one, without
 * repeats — and the groups of the filter, which are the visible categories that still hold a
 * shown record.
 */

type Localized = { ru: string; en: string }

interface FaqCategory {
  id: number
  title: Localized
  is_visible: boolean
  /** The questions in it, in the order they are dragged into inside it (`item_position`). */
  items: number[]
}

interface FaqQuestion {
  id: number
  anchor: string
  question: Localized
  /** HTML, as `wx-rich-text` stores it. An empty language is a question not shown in it. */
  answer: Localized
  published: boolean
}

export const faqCategories: FaqCategory[] = [
  { id: 1, title: { ru: 'Оплата', en: 'Payment' }, is_visible: true, items: [2, 1, 5] },
  { id: 2, title: { ru: 'Доставка', en: 'Delivery' }, is_visible: true, items: [3, 4] },
  { id: 3, title: { ru: 'Гарантия', en: 'Warranty' }, is_visible: true, items: [6, 5] },
  /* Hidden, so a filter over "all" shows three buttons and not four. */
  { id: 4, title: { ru: 'Для партнёров', en: 'For partners' }, is_visible: false, items: [7] },
]

/** In the general order (`position`). */
export const faqQuestions: FaqQuestion[] = [
  {
    id: 1,
    anchor: 'kak-oplatit',
    question: { ru: 'Как оплатить заказ?', en: 'How do I pay for an order?' },
    answer: {
      ru: '<p>Картой на сайте, переводом по счёту или наличными курьеру.</p>',
      en: '<p>By card on the site, by bank transfer or in cash to the courier.</p>',
    },
    published: true,
  },
  {
    id: 2,
    anchor: 'oplata-kartoy',
    question: { ru: 'Можно ли оплатить картой?', en: 'Can I pay by card?' },
    answer: {
      ru: '<p>Да, <strong>Visa</strong> и <strong>Mastercard</strong>, без комиссии.</p>',
      en: '<p>Yes, <strong>Visa</strong> and <strong>Mastercard</strong>, with no fee.</p>',
    },
    published: true,
  },
  {
    id: 3,
    anchor: 'srok-dostavki',
    question: { ru: 'Сколько идёт доставка?', en: 'How long does delivery take?' },
    answer: {
      ru: '<p>По городу — на следующий день, по стране — от двух до пяти дней.</p>',
      en: '<p>Next day in the city, two to five days across the country.</p>',
    },
    published: true,
  },
  {
    id: 4,
    anchor: 'samovyvoz',
    question: { ru: 'Есть ли самовывоз?', en: '' },
    /* Only in Russian: decision 9 — no answer in a language, no question in it. */
    answer: { ru: '<p>Да, из офиса на Крещатике, 22, по будням с 9 до 18.</p>', en: '' },
    published: true,
  },
  {
    id: 5,
    anchor: 'vozvrat-deneg',
    question: { ru: 'Как вернуть деньги?', en: 'How do I get a refund?' },
    answer: {
      ru: '<p>Напишите нам в течение 14 дней — вернём на ту же карту.</p>',
      en: '<p>Write to us within 14 days and we refund to the same card.</p>',
    },
    published: true,
  },
  {
    id: 6,
    anchor: 'garantiya',
    question: { ru: 'Какая гарантия?', en: 'What is the warranty?' },
    answer: {
      ru: '<p>Двенадцать месяцев на всё, что мы сделали.</p>',
      en: '<p>Twelve months on everything we made.</p>',
    },
    published: true,
  },
  {
    id: 7,
    anchor: 'optovye-ceny',
    question: { ru: 'Есть ли оптовые цены?', en: 'Are there wholesale prices?' },
    answer: { ru: '<p>Да, от десяти штук.</p>', en: '<p>Yes, from ten items.</p>' },
    /* A draft: never shown, whatever is chosen. */
    published: false,
  },
]

/** `GET /api/cms/collections`, as `CollectionController` answers it. */
export function collectionSources(locale: string): unknown[] {
  return [
    {
      key: 'faq',
      title: locale === 'ru' ? 'Вопросы и ответы' : 'FAQ',
      categories: 'faq/categories',
      markup: true,
    },
  ]
}

/** One category as the shared category API answers it (`CategoryResource`): no address, no SEO. */
export function faqCategoryRow(category: FaqCategory, locale: string): Record<string, unknown> {
  const name = pick(category.title, locale)

  return {
    id: category.id,
    name,
    title: category.title,
    slug: null,
    path: null,
    url: null,
    is_visible: category.is_visible,
    position: faqCategories.indexOf(category),
    deleted_at: null,
    questions_count: category.items.length,
  }
}

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

  const shown = (question: FaqQuestion): boolean =>
    question.published &&
    pick(question.question, locale, false) !== '' &&
    pick(question.answer, locale, false) !== ''

  const inCategories = (id: number): number[] =>
    faqCategories.filter((category) => category.items.includes(id)).map((one) => one.id)

  let ordered: FaqQuestion[]

  if (chosen.length === 1) {
    const category = faqCategories.find((one) => one.id === chosen[0])
    ordered = (category?.items ?? [])
      .map((id) => faqQuestions.find((one) => one.id === id))
      .filter((one): one is FaqQuestion => one !== undefined)
  } else {
    ordered = faqQuestions.filter(
      (question) =>
        chosen.length === 0 || inCategories(question.id).some((id) => chosen.includes(id)),
    )
  }

  const items = ordered
    .filter(shown)
    .slice(0, limit ?? undefined)
    .map((question) => ({
      id: question.id,
      anchor: question.anchor,
      categories: inCategories(question.id),
      question: pick(question.question, locale),
      answer: pick(question.answer, locale),
    }))

  const groups = filter
    ? faqCategories
        .filter((category) => category.is_visible)
        .filter((category) => chosen.length === 0 || chosen.includes(category.id))
        .map((category) => ({
          id: category.id,
          title: pick(category.title, locale),
          items: items.filter((item) => item.categories.includes(category.id)).map((i) => i.id),
        }))
        .filter((group) => group.items.length > 0)
    : []

  return { items, groups, filter }
}

function pick(value: Localized, locale: string, fallback = true): string {
  const own = locale === 'en' ? value.en : value.ru

  return own !== '' || !fallback ? own : value.ru || value.en
}
