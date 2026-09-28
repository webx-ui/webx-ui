import { pages } from './pages'
import { find as findService, row as serviceRow } from './services'

/**
 * The tariffs (`module-tariffs`): price cards, their flat groups, and the collection source blocks
 * show them through (`wx-collection`).
 *
 * The panel half answers the way §5.4 of the module's spec fixes it — the list whole and without
 * pages, the form's values by field name, the order dragged either in the whole list or inside
 * one group, refusals under the name of the field (a line of the list under `features.<n>.text`).
 * The site half is `resolveTariffs()`: what `TariffsSource` answers on a site — published and not
 * in the bin, in any language (decision 12), one chosen group in its own order, narrowed by a
 * relation to services, the limit last — as cards of §4.1.
 *
 * Categories in the code, groups in the words (decision 14): everything here says `category`.
 */

type Localized = Record<string, string>

/** A value of `wx-link`, as the form sends it. */
type Link = Record<string, unknown>

export interface TariffCategory {
  id: number
  title: Localized
  is_visible: boolean
  /** The tariffs in it, in the order they are dragged into inside it (`item_position`). */
  items: number[]
  deleted_at: string | null
  /** What a project patched onto `tariffs.category-form`. */
  extra: Record<string, unknown>
}

export interface Tariff {
  id: number
  name: Localized
  badge: Localized
  /** A number or nothing — then `price_text` is what the site prints. */
  price: number | null
  /** A key of `CURRENCIES`; one the config dropped stays here and is printed as its code. */
  currency: string | null
  period: Localized
  price_text: Localized
  /** In the order of the form: `{ text }` rather than a bare map, as `wx-repeater` writes it. */
  features: { text: Localized }[]
  description: Localized
  button_label: Localized
  button_link: Link | null
  button_variant: string | null
  featured: boolean
  published: boolean
  /** The value of `wx-relations` with `target: service` — ids in the order chosen. */
  services: number[]
  updated_at: string
  deleted_at: string | null
  extra: Record<string, unknown>
}

const STAMP = '2026-09-28T09:00:00+00:00'
/** The language a name falls back on (decision 12), and the one it is required in. */
const DEFAULT = 'ru'
const LOCALES = ['ru', 'en']

/**
 * `webx-tariffs.currencies` of this demo site: code → the symbol the site prints. The first one is
 * what a new tariff starts with (decision 15).
 */
export const CURRENCIES: Record<string, string> = {
  USD: '$',
  EUR: '€',
  UAH: '₴',
  PLN: 'zł',
}

/** `webx-tariffs.variants`: the looks of the button. The first one is the fallback (decision 11). */
export const VARIANTS: Record<string, string> = {
  primary: 'Primary',
  secondary: 'Secondary',
  link: 'Link',
}

/** A page of the tree by its slug — what the demo's buttons point at (§5.6). */
function page(slug: string): Link {
  const found = [...pages.values()].find((record) => record.row.slug === slug)

  return {
    target: 'entity',
    entity_type: 'page',
    entity_id: found?.row.id ?? 1,
    url: null,
    hash: null,
    new_tab: false,
    rel: [],
  }
}

function address(url: string, newTab = false): Link {
  return {
    target: 'url',
    entity_type: null,
    entity_id: null,
    url,
    hash: null,
    new_tab: newTab,
    rel: [],
  }
}

function lines(...rows: Localized[]): { text: Localized }[] {
  return rows.map((text) => ({ text }))
}

export const tariffCategories: TariffCategory[] = [
  category(1, { ru: 'Для бизнеса', en: 'For business' }, [1, 2, 3]),
  category(2, { ru: 'Для частных', en: 'For individuals' }, [4]),
]

/** In the general order (`position`). The first three are the demo of §5.6. */
export const tariffs: Tariff[] = [
  /* Six lines, one of them in English only: on the Russian page the list is a line shorter. */
  tariff(1, {
    name: { ru: 'Combo Starter', en: 'Combo Starter' },
    badge: { ru: '30 ЧАСОВ / 25$', en: '30 HOURS / 25$' },
    price: 750,
    currency: 'USD',
    period: { ru: '/мес', en: '/mo' },
    features: lines(
      { ru: 'Дизайн', en: 'Design' },
      { ru: 'Вёрстка', en: 'Front-end' },
      { ru: 'SEO-аудит', en: 'SEO audit' },
      { ru: 'Тексты', en: 'Copywriting' },
      { ru: 'Отчёт раз в месяц', en: 'A monthly report' },
      { ru: '', en: 'Slack channel' },
    ),
    description: {
      ru: 'Для тех, кто начинает: тридцать часов команды в месяц на то, что нужно сейчас.',
      en: 'For a start: thirty hours of the team a month, spent on what matters now.',
    },
    button_label: { ru: 'Начать', en: 'Get started' },
    button_link: page('contacts'),
    button_variant: 'secondary',
  }),
  /* Featured, and related to the company website: the one card of the block on that service. */
  tariff(2, {
    name: { ru: 'Combo Growth', en: 'Combo Growth' },
    badge: { ru: '60 ЧАСОВ / 23$', en: '60 HOURS / 23$' },
    price: 1380,
    currency: 'USD',
    period: { ru: '/мес', en: '/mo' },
    features: lines(
      { ru: 'Всё из Starter', en: 'Everything in Starter' },
      { ru: 'Личный менеджер', en: 'A personal manager' },
      { ru: 'A/B-тесты', en: 'A/B tests' },
    ),
    description: {
      ru: 'Когда сайт уже работает и нужно, чтобы он приносил больше.',
      en: 'When the site already works and has to bring in more.',
    },
    button_label: { ru: 'Начать', en: 'Get started' },
    button_link: page('contacts'),
    button_variant: 'primary',
    featured: true,
    services: [2],
  }),
  /* No number: the words stand where the price would. */
  tariff(3, {
    name: { ru: 'Combo Enterprise', en: 'Combo Enterprise' },
    badge: { ru: '120+ ЧАСОВ', en: '120+ HOURS' },
    price_text: { ru: 'По запросу', en: 'On request' },
    features: lines({ ru: 'Выделенная команда', en: 'A dedicated team' }, { ru: 'SLA', en: 'SLA' }),
    button_label: { ru: 'Связаться', en: 'Contact us' },
    button_link: page('contacts'),
    button_variant: 'link',
  }),
  /* Another currency, a fraction-free price with a thousands separator, and an address. */
  tariff(4, {
    name: { ru: 'Лендинг «Старт»', en: 'Landing “Start”' },
    badge: { ru: 'ЗА 10 ДНЕЙ', en: 'IN 10 DAYS' },
    price: 12500,
    currency: 'UAH',
    period: { ru: 'разово', en: 'one-off' },
    features: lines(
      { ru: 'Одна страница', en: 'One page' },
      { ru: 'Форма заявки', en: 'A request form' },
      { ru: 'Хостинг на год', en: 'A year of hosting' },
    ),
    button_label: { ru: 'Заказать', en: 'Order' },
    button_link: address('https://example.com/order', true),
    button_variant: 'primary',
    services: [1],
  }),
  /* A draft, with cents, and a button to a page that is itself a draft: no button on the site. */
  tariff(5, {
    name: { ru: 'Поддержка Pro', en: 'Support Pro' },
    badge: { ru: '', en: '' },
    price: 99.5,
    currency: 'EUR',
    period: { ru: '/мес', en: '/mo' },
    features: lines({ ru: 'Ответ за час', en: 'An answer within the hour' }),
    button_label: { ru: 'Проекты', en: 'Projects' },
    button_link: page('projects'),
    button_variant: 'primary',
    published: false,
  }),
]

function category(id: number, title: Localized, items: number[], visible = true): TariffCategory {
  return { id, title, is_visible: visible, items, deleted_at: null, extra: {} }
}

function tariff(id: number, seed: Partial<Omit<Tariff, 'id'>> & Pick<Tariff, 'name'>): Tariff {
  return {
    badge: {},
    price: null,
    currency: null,
    period: {},
    price_text: {},
    features: [],
    description: {},
    button_label: {},
    button_link: null,
    button_variant: null,
    featured: false,
    published: true,
    services: [],
    updated_at: STAMP,
    deleted_at: null,
    extra: {},
    ...seed,
    id,
  }
}

/* ------------------------------------------------------------------------------ panel ----- */

const live = (one: { deleted_at: string | null }): boolean => one.deleted_at === null

/** The options of the currency select, patched onto `tariffs.form` from the config (§4.3). */
export function currencyOptions(): { value: string; label: string }[] {
  return Object.entries(CURRENCIES).map(([value, symbol]) => ({
    value,
    label: `${value} — ${symbol}`,
  }))
}

/** The options of the button's look, the same way. */
export function variantOptions(): { value: string; label: string }[] {
  return Object.entries(VARIANTS).map(([value, label]) => ({ value, label }))
}

/** The groups a tariff is in, in the order of the group list. */
function categoriesOf(id: number): TariffCategory[] {
  return tariffCategories.filter((one) => live(one) && one.items.includes(id))
}

/** The symbol of a currency: the code itself for one the config dropped (decision 15). */
function symbolOf(code: string | null): string | null {
  return code === null ? null : (CURRENCIES[code] ?? code)
}

/** One tariff as the list line draws it (§5.4). */
export function tariffRow(record: Tariff, locale: string): Record<string, unknown> {
  return {
    id: record.id,
    name: pick(record.name, locale) || `#${record.id}`,
    badge: pick(record.badge, locale) || null,
    price: record.price,
    currency: record.currency,
    symbol: symbolOf(record.currency),
    period: pick(record.period, locale) || null,
    price_text: pick(record.price_text, locale) || null,
    featured: record.featured,
    published: record.published,
    position: tariffs.indexOf(record) + 1,
    categories: categoriesOf(record.id).map((one) => ({
      id: one.id,
      title: pick(one.title, locale),
    })),
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
  }
}

/** `GET /tariffs`: the whole list, or a group's in its own order, or the bin. */
export function listTariffs(
  query: Record<string, string>,
  locale: string,
): Record<string, unknown> {
  let found: Tariff[]

  if (query.trashed === '1') {
    found = tariffs
      .filter((one) => !live(one))
      .sort((a, b) => String(b.deleted_at).localeCompare(String(a.deleted_at)))
  } else {
    const inside = query.category ? findCategory(Number(query.category)) : null

    found = (
      inside === null
        ? tariffs
        : inside.items.map((id) => findTariff(id)).filter((one): one is Tariff => one !== null)
    ).filter(live)
  }

  const term = (query.search ?? '').trim().toLowerCase()

  if (term !== '') {
    found = found.filter((one) =>
      [one.name, one.badge, one.description].some((map) =>
        Object.values(map).some((words) => words.toLowerCase().includes(term)),
      ),
    )
  }

  return {
    data: found.map((one) => tariffRow(one, locale)),
    filters: {
      categories: tariffCategories
        .filter(live)
        .map((one) => ({ id: one.id, title: pick(one.title, locale) })),
    },
  }
}

/** One tariff as its form opens it (`TariffForm::describe()`). */
export function tariffDetail(record: Tariff, locale: string): Record<string, unknown> {
  return {
    tariff: {
      id: record.id,
      name: pick(record.name, locale) || `#${record.id}`,
      published: record.published,
      deleted_at: record.deleted_at,
    },
    values: {
      ...record.extra,
      name: { ...record.name },
      badge: { ...record.badge },
      price: record.price,
      currency: record.currency,
      period: { ...record.period },
      price_text: { ...record.price_text },
      features: record.features.map((one) => ({ text: { ...one.text } })),
      description: { ...record.description },
      button_label: { ...record.button_label },
      button_link: record.button_link === null ? null : { ...record.button_link },
      button_variant: record.button_variant,
      featured: record.featured,
      published: record.published,
      categories: categoriesOf(record.id).map((one) => one.id),
      services: [...record.services],
    },
  }
}

export function findTariff(id: number): Tariff | null {
  return tariffs.find((one) => one.id === id) ?? null
}

export function findCategory(id: number): TariffCategory | null {
  return tariffCategories.find((one) => one.id === id && live(one)) ?? null
}

/** The fields the form writes into columns; everything else is the project's (`extra`). */
const OWN = new Set([
  'name',
  'badge',
  'price',
  'currency',
  'period',
  'price_text',
  'features',
  'description',
  'button_label',
  'button_link',
  'button_variant',
  'featured',
  'published',
  'categories',
  'services',
])

/**
 * The values of `tariffs.form`, checked and written — a new tariff when `record` is `null`. What
 * is refused comes back under the name of the field, as a 422 would carry it (§5.4), and nothing
 * is written then: not even the new row (the server does it in a transaction).
 */
export function writeTariff(
  record: Tariff | null,
  values: Record<string, unknown>,
): { record: Tariff } | { errors: Record<string, string[]> } {
  const errors: Record<string, string[]> = {}

  // The one required field: a card with no name is a card nobody can be told from.
  if (record === null || values.name !== undefined) {
    const name = asMap(values.name ?? record?.name)

    if (said(name[DEFAULT]) === '')
      errors[`name.${DEFAULT}`] = ['Название нужно на языке по умолчанию.']

    for (const [code, words] of Object.entries(name)) {
      if (words.length > 255) errors[`name.${code}`] = ['Не длиннее 255 символов.']
    }
  }

  let price = record?.price ?? null

  if (values.price !== undefined) {
    const raw = values.price

    if (raw === null || raw === '') {
      price = null
    } else if (
      typeof raw !== 'number' ||
      !Number.isFinite(raw) ||
      raw < 0 ||
      raw >= 1e8 ||
      Math.round(raw * 100) !== raw * 100
    ) {
      errors.price = ['Цена — число от 0 до 99 999 999.99, не больше двух знаков после запятой.']
    } else {
      price = raw
    }
  }

  let currency = record?.currency ?? null

  if (values.currency !== undefined) {
    const raw = typeof values.currency === 'string' ? values.currency.trim() : ''

    if (raw === '') {
      currency = null
    } else if (!(raw in CURRENCIES) && raw !== record?.currency) {
      // A code the config dropped goes back as it came, but is not chosen anew (decision 15).
      errors.currency = ['Такой валюты нет в настройках сайта.']
    } else {
      currency = raw
    }
  }

  // A new tariff starts in the first currency of the list (decision 15).
  if (record === null && currency === null) currency = Object.keys(CURRENCIES)[0] ?? null

  let variant = record?.button_variant ?? null

  if (values.button_variant !== undefined) {
    const raw = typeof values.button_variant === 'string' ? values.button_variant.trim() : ''

    if (raw === '') variant = null
    else if (!(raw in VARIANTS) && raw !== record?.button_variant) {
      errors.button_variant = ['Такого вида нет в настройках сайта.']
    } else variant = raw
  }

  const label =
    values.button_label !== undefined ? asMap(values.button_label) : record?.button_label
  const link =
    values.button_link !== undefined
      ? values.button_link !== null && typeof values.button_link === 'object'
        ? (values.button_link as Link)
        : null
      : (record?.button_link ?? null)

  // A label with nowhere to go is a button that would lead nowhere; a link without one just waits.
  if (label && Object.values(label).some((words) => said(words) !== '') && !linked(link)) {
    errors.button_link = ['Укажите, куда ведёт кнопка.']
  }

  let features: { text: Localized }[] | undefined

  if (values.features !== undefined) {
    features = []

    asRows(values.features).forEach((row, index) => {
      const text = asMap(row.text)

      // A line with no words in any language is a line somebody added and left: dropped (§5.4).
      if (Object.values(text).every((words) => said(words) === '')) return

      // Numbered the way the editor sees the rows, the empty ones counted (`rowErrors`).
      if (Object.values(text).some((words) => words.length > 255)) {
        errors[`features.${index}.text`] = ['Строка не длиннее 255 символов.']
      }

      features!.push({ text })
    })
  }

  let chosenCategories: number[] | undefined

  if (values.categories !== undefined) {
    chosenCategories = [...new Set(asIds(values.categories))]

    if (chosenCategories.some((id) => findCategory(id) === null)) {
      errors.categories = ['Одной из этих групп больше нет.']
    }
  }

  let chosenServices: number[] | undefined

  if (values.services !== undefined) {
    chosenServices = [...new Set(asIds(values.services))]

    if (chosenServices.some((id) => findService(id) === null)) {
      errors.services = ['Одной из этих услуг больше нет.']
    }
  }

  if (Object.keys(errors).length > 0) return { errors }

  const target =
    record ??
    tariff(Math.max(0, ...tariffs.map((one) => one.id)) + 1, {
      name: {},
      published: false,
    })

  for (const [name, value] of Object.entries(values)) {
    if (name === 'name') target.name = asMap(value)
    else if (name === 'badge') target.badge = asMap(value)
    else if (name === 'period') target.period = asMap(value)
    else if (name === 'price_text') target.price_text = asMap(value)
    else if (name === 'description') target.description = asMap(value)
    else if (name === 'button_label') target.button_label = asMap(value)
    else if (name === 'button_link') target.button_link = link
    else if (name === 'featured') target.featured = value === true
    else if (name === 'published') target.published = value === true
    else if (!OWN.has(name)) target.extra[name] = value
  }

  target.price = price
  target.currency = currency
  target.button_variant = variant
  if (features !== undefined) target.features = features
  if (chosenServices !== undefined) target.services = chosenServices

  // A new tariff goes to the end of the general order (`max + 1`, §5.1).
  if (record === null) tariffs.push(target)

  if (chosenCategories !== undefined) {
    for (const one of tariffCategories) {
      const inside = one.items.includes(target.id)
      const wanted = chosenCategories.includes(one.id)

      // A new member goes to the end of the group, the way the general order has it.
      if (wanted && !inside) one.items.push(target.id)
      if (!wanted && inside) one.items.splice(one.items.indexOf(target.id), 1)
    }
  }

  target.updated_at = new Date().toISOString()

  return { record: target }
}

/** The order on screen, written whole: the general one, or one group's. */
export function reorderTariffs(ids: number[], categoryId: number | null): void {
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

  const moved = ids.map((id) => findTariff(id)).filter((one): one is Tariff => !!one)
  const rest = tariffs.filter((one) => !ids.includes(one.id))

  tariffs.splice(0, tariffs.length, ...moved, ...rest)
}

/* ----------------------------------------------------------------------------- groups ----- */

/** One group as the shared category API answers it (`CategoryResource`): no address, no SEO. */
export function tariffCategoryRow(record: TariffCategory, locale: string): Record<string, unknown> {
  return {
    id: record.id,
    name: pick(record.title, locale) || `#${record.id}`,
    title: record.title,
    slug: null,
    path: null,
    url: null,
    is_visible: record.is_visible,
    position: tariffCategories.indexOf(record) + 1,
    deleted_at: record.deleted_at,
    tariffs_count: record.items.filter((id) => {
      const one = findTariff(id)

      return one !== null && live(one)
    }).length,
  }
}

export function tariffCategoryDetail(
  record: TariffCategory,
  locale: string,
): Record<string, unknown> {
  return {
    category: tariffCategoryRow(record, locale),
    values: { ...record.extra, title: { ...record.title }, is_visible: record.is_visible },
    prefix: null,
  }
}

export function createCategory(title: unknown): TariffCategory {
  const record = category(
    Math.max(0, ...tariffCategories.map((one) => one.id)) + 1,
    asMap(title),
    [],
  )

  tariffCategories.push(record)

  return record
}

export function writeCategory(
  record: TariffCategory,
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
  const moved = ids.map((id) => findCategory(id)).filter((one): one is TariffCategory => !!one)
  const rest = tariffCategories.filter((one) => !ids.includes(one.id))

  tariffCategories.splice(0, tariffCategories.length, ...moved, ...rest)
}

/* ------------------------------------------------------------------------------- site ----- */

/** How a stored `wx-link` becomes an address — the mock's router knows every fixture. */
export type LinkResolver = (link: Link) => Link

/**
 * A stored choice, read the way the site reads it (`TariffsSource::items()`), as cards of §4.1.
 * `entity` is the record whose page the block is on: "related to the current one" is answered
 * against it, and without one (a block drawn on its sample) that filter narrows nothing.
 */
export function resolveTariffs(
  stored: unknown,
  locale: string,
  entity: { type: string; id: number } | null,
  resolveLink: LinkResolver,
): Record<string, unknown> {
  const value = (typeof stored === 'object' && stored !== null ? stored : {}) as Record<
    string,
    unknown
  >
  const chosen = Array.isArray(value.categories) ? asIds(value.categories) : []
  const limit = typeof value.limit === 'number' && value.limit >= 1 ? value.limit : null
  const related = value.related as
    { type?: string; ids?: unknown; current?: unknown } | null | undefined

  let wanted: number[] | null = null

  if (related?.type === 'service') {
    // An empty list with a type is "related to nothing": no tariff (§4.2 of the spec).
    if (related.current === true) wanted = entity?.type === 'service' ? [entity.id] : null
    else wanted = asIds(related.ids)
  }

  let ordered: Tariff[]

  // One group: its own order. Several, or none: the general one, without repeats.
  if (chosen.length === 1) {
    ordered = (findCategory(chosen[0]!)?.items ?? [])
      .map((id) => findTariff(id))
      .filter((one): one is Tariff => one !== null)
  } else {
    ordered = tariffs.filter(
      (record) =>
        chosen.length === 0 || categoriesOf(record.id).some((one) => chosen.includes(one.id)),
    )
  }

  const items = ordered
    .filter((record) => live(record) && record.published)
    .filter((record) => wanted === null || record.services.some((id) => wanted!.includes(id)))
    .slice(0, limit ?? undefined)
    .map((record) => card(record, locale, resolveLink))

  return { items, groups: [], filter: false }
}

/** A tariff as a template reads it — `Rendering\Cards`, §4.1. */
function card(record: Tariff, locale: string, resolveLink: LinkResolver) {
  // The name, the plate, the period and the words: the default language where the page's has none.
  const fallback = (map: Localized) => said(map[locale]) || said(map[DEFAULT])

  return {
    id: record.id,
    anchor: `tariff-${record.id}`,
    categories: categoriesOf(record.id).map((one) => one.id),
    name: fallback(record.name),
    badge: fallback(record.badge),
    price: record.price,
    amount: record.price === null ? '' : amount(record.price, locale),
    currency: record.currency,
    symbol: symbolOf(record.currency),
    period: fallback(record.period),
    price_text: fallback(record.price_text),
    // Only the lines written in the language of the page: shorter rather than foreign.
    features: record.features.map((one) => said(one.text[locale])).filter((words) => words !== ''),
    description: said(record.description[locale]),
    button: button(record, locale, resolveLink),
    featured: record.featured,
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

/**
 * The button, or nothing: no label in the page's language, or a link to what the site does not
 * show (a draft, the bin) — a button to a 404 is worse than none (§4.1).
 */
function button(record: Tariff, locale: string, resolveLink: LinkResolver) {
  const label = said(record.button_label[locale])

  if (label === '' || record.button_link === null || !linked(record.button_link)) return null

  const resolved = resolveLink(record.button_link)

  if (resolved.available === false || typeof resolved.url !== 'string' || resolved.url === '') {
    return null
  }

  const newTab = record.button_link.new_tab === true
  const rel = new Set(
    Array.isArray(record.button_link.rel) ? (record.button_link.rel as string[]) : [],
  )

  if (newTab) {
    rel.add('noopener')
    rel.add('noreferrer')
  }

  const variant =
    record.button_variant !== null && record.button_variant in VARIANTS
      ? record.button_variant
      : Object.keys(VARIANTS)[0]!

  return {
    label,
    url: resolved.url,
    new_tab: newTab,
    rel: rel.size > 0 ? [...rel].join(' ') : null,
    variant,
  }
}

/**
 * The number as the card carries it (§4.1): no fraction when it is whole, two places when it is
 * not, the separators of the page's language — a no-break space between thousands in Russian.
 */
function amount(price: number, locale: string): string {
  const whole = Number.isInteger(price)

  return new Intl.NumberFormat(locale, {
    minimumFractionDigits: whole ? 0 : 2,
    maximumFractionDigits: whole ? 0 : 2,
  }).format(price)
}

/* ---------------------------------------------------------------------------- helpers ----- */

/** The language asked for, or the default one where it is empty — the panel names what it can. */
function pick(value: Localized, locale: string): string {
  return (
    said(value[locale]) ||
    said(value[DEFAULT]) ||
    LOCALES.map((code) => said(value[code])).find(Boolean) ||
    ''
  )
}

function said(value: string | undefined): string {
  return (value ?? '').trim()
}

/** Somewhere to go: a chosen record, or an address that is not empty. */
function linked(link: Link | null): boolean {
  if (link === null) return false
  if (link.target === 'entity') {
    return typeof link.entity_type === 'string' && Number(link.entity_id) > 0
  }
  if (link.target === 'url') return typeof link.url === 'string' && link.url.trim() !== ''

  return false
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
