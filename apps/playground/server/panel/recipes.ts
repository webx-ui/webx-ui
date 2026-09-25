import type { LocalizedValue } from '../../../../packages/core/src/composables/useLocalized'
import type {
  RecipeRow,
  RecipeStatus,
  RecipeTermRef,
  RecipeVersion,
} from '../../../../packages/module-recipes/src/types'
import type { ScreenModel } from '../../../../packages/schema/src/types'
import { fileByPath } from './media'
import { find as findService, row as serviceRow, services } from './services'

/**
 * The recipes the playground edits (§5.10 of the recipes spec): recipes with their drafts, the
 * categories they are filed under — with pages of their own — and what they are rich in, which
 * has no page and is a chip and a filter only.
 *
 * Unlike services, everything a recipe is filed under waits in the draft: its categories, its
 * nutrients, its services and its similar recipes reach the site on "Publish", with the text
 * (§5.14, итог RC1). Only the SEO card is written as it is saved. So a recipe on the site whose
 * categories were changed shows "edits" — and that is the thing this fixture is here to show.
 *
 * The ids of the first six are the ones the relations fixture named before this file existed,
 * so the "Recipes" field a project patched onto the services form keeps pointing at the same
 * dishes.
 */

/** The first segment of every recipe address, the categories' included (§5.3). */
export const PREFIX = 'recipes'

/** The five keys of nutrition, the old site's set (decision 7). */
export const NUTRITION = ['calories', 'protein', 'fat', 'carbohydrates', 'fiber'] as const

export interface RecipeRecord {
  id: number
  /** What the editor opens: the draft laid over what was last published. */
  values: ScreenModel
  /** What the site is showing, or `null` for a recipe that has never been on it. */
  live: ScreenModel | null
  status: RecipeStatus
  position: number
  published_at: string | null
  updated_at: string
  deleted_at: string | null
  versions: RecipeVersion[]
  snapshots: Record<number, ScreenModel>
}

/**
 * A category or a nutrient: the row the shared list draws and what its page edits. A nutrient
 * has no address, no introduction and no cover — `path` stays `null`, and the shared list leaves
 * the address line out.
 */
export interface TermRecord {
  id: number
  name: string
  title: LocalizedValue
  slug: LocalizedValue | null
  path: string | null
  url: string | null
  is_visible: boolean
  position: number
  deleted_at: string | null
  lead: LocalizedValue
  cover: Record<string, unknown> | null
  seo: Record<string, unknown>
  extra: Record<string, unknown>
}

export type TermKind = 'categories' | 'nutrients'

export const recipes: RecipeRecord[] = []
export const recipeCategories: TermRecord[] = []
export const recipeNutrients: TermRecord[] = []

export const terms = (kind: TermKind): TermRecord[] =>
  kind === 'categories' ? recipeCategories : recipeNutrients

/** Written as it is saved rather than kept for the publication. */
const UNDRAFTED = ['seo']

/* ------------------------------------------------------------------------------ terms ----- */

function term(kind: TermKind, title: [string, string], slug: string | null, lead = ''): void {
  const list = terms(kind)
  const id = list.length + 1

  list.push({
    id,
    name: title[0],
    title: { ru: title[0], en: title[1] },
    slug: slug === null ? null : { ru: slug, en: slug },
    path: slug === null ? null : `${PREFIX}/${slug}`,
    url: slug === null ? null : `https://webx-demo.test/${PREFIX}/${slug}`,
    is_visible: true,
    position: id,
    deleted_at: null,
    lead: { ru: lead, en: '' },
    cover: null,
    seo: {},
    extra: {},
  })
}

term('categories', ['Завтраки', 'Breakfasts'], 'breakfasts', '<p>С чего начать день.</p>')
term('categories', ['Супы', 'Soups'], 'soups', '<p>Горячее на обед.</p>')
term('categories', ['Основные блюда', 'Mains'], 'mains')
term('categories', ['Салаты', 'Salads'], 'salads')
term('categories', ['Напитки', 'Drinks'], 'drinks')

term('nutrients', ['Железо', 'Iron'], null)
term('nutrients', ['Клетчатка', 'Fibre'], null)
term('nutrients', ['Белок', 'Protein'], null)
term('nutrients', ['Омега-3', 'Omega-3'], null)
term('nutrients', ['Витамин C', 'Vitamin C'], null)

/* ---------------------------------------------------------------------------- recipes ----- */

interface Seed {
  title: [string, string]
  slug: string
  lead: [string, string]
  gallery: string[]
  ingredients: string[]
  steps: string[]
  nutrition?: [string, string, string, string, string]
  minutes: number | null
  servings: number | null
  categories: number[]
  nutrients: number[]
  services?: number[]
  related?: number[]
  status?: RecipeStatus
  versions?: number
}

const list = (items: string[], tag: 'ul' | 'ol'): string =>
  `<${tag}>${items.map((item) => `<li><p>${item}</p></li>`).join('')}</${tag}>`

function recipe(seed: Seed): RecipeRecord {
  const id = recipes.length + 1
  const status = seed.status ?? 'published'

  const values: ScreenModel = {
    title: { ru: seed.title[0], en: seed.title[1] },
    slug: { ru: seed.slug, en: seed.slug },
    lead: { ru: seed.lead[0], en: seed.lead[1] },
    gallery: seed.gallery.map((path) => ({ path, alt: { ru: seed.title[0], en: seed.title[1] } })),
    ingredients: { ru: list(seed.ingredients, 'ul'), en: '' },
    method: { ru: list(seed.steps, 'ol'), en: '' },
    ...Object.fromEntries(
      NUTRITION.map((key, index) => [
        `nutrition.${key}`,
        seed.nutrition ? { ru: seed.nutrition[index]!, en: seed.nutrition[index]! } : {},
      ]),
    ),
    total_minutes: seed.minutes,
    servings: seed.servings,
    categories: seed.categories,
    nutrients: seed.nutrients,
    services: seed.services ?? [],
    related: seed.related ?? [],
    seo: {},
  }

  const published = status === 'draft' ? null : '2026-09-20T09:00:00+00:00'
  const record: RecipeRecord = {
    id,
    values,
    live: status === 'draft' ? null : clone(values),
    status,
    position: id,
    published_at: published,
    updated_at: '2026-09-22T10:00:00+00:00',
    deleted_at: null,
    versions: [],
    snapshots: {},
  }

  const count = seed.versions ?? (record.live === null ? 0 : 1)

  for (let number = count; number > 0; number -= 1) {
    record.versions.push({
      number,
      created_at: shift('2026-09-20T09:00:00+00:00', (number - count) * 3),
      author: 'Анна Ковальчук',
      source: 'panel',
      comment: number === 1 ? 'Первая публикация' : null,
      is_pinned: false,
    })

    record.snapshots[number] = clone(values)
  }

  recipes.push(record)

  return record
}

/* A gallery of three and every part of the page filled: the one to look at first. */
recipe({
  title: ['Овсянка с ягодами', 'Porridge with berries'],
  slug: 'porridge-with-berries',
  lead: [
    'Тёплая овсянка на молоке с ягодами и мёдом — за пятнадцать минут.',
    'Warm oats in milk with berries and honey, in fifteen minutes.',
  ],
  gallery: ['recipes/porridge.svg', 'recipes/porridge-berries.svg', 'recipes/porridge-bowl.svg'],
  ingredients: ['Овсяные хлопья — 80 г', 'Молоко — 300 мл', 'Ягоды — горсть', 'Мёд — 1 ч. л.'],
  steps: [
    'Доведите молоко до кипения.',
    'Всыпьте хлопья и варите пять минут, помешивая.',
    'Разложите по тарелкам, сверху ягоды и мёд.',
  ],
  nutrition: ['320 ккал', '12 г', '8 г', '52 г', '6 г'],
  minutes: 15,
  servings: 2,
  categories: [1],
  nutrients: [2, 1],
  services: [1],
  versions: 2,
})

/* In two categories, and with its similar recipes chosen by hand. */
recipe({
  title: ['Чечевичный суп', 'Lentil soup'],
  slug: 'lentil-soup',
  lead: [
    'Густой суп из красной чечевицы с кумином и лимоном.',
    'A thick red lentil soup with cumin and lemon.',
  ],
  gallery: ['recipes/lentil-soup.svg'],
  ingredients: ['Красная чечевица — 200 г', 'Морковь — 1 шт.', 'Лук — 1 шт.', 'Кумин — 1 ч. л.'],
  steps: ['Обжарьте лук и морковь.', 'Добавьте чечевицу и воду, варите 30 минут.', 'Пюрируйте.'],
  nutrition: ['280 ккал', '16 г', '5 г', '40 г', '11 г'],
  minutes: 45,
  servings: 4,
  categories: [2, 3],
  nutrients: [1, 3, 2],
  related: [5, 3],
})

/* On the site, with edits waiting — among them a category it was not in. */
const salad = recipe({
  title: ['Салат с киноа', 'Quinoa salad'],
  slug: 'quinoa-salad',
  lead: ['Киноа, огурец, томаты и зелень.', 'Quinoa, cucumber, tomatoes and herbs.'],
  gallery: [],
  ingredients: ['Киноа — 150 г', 'Огурец — 1 шт.', 'Томаты черри — 10 шт.'],
  steps: ['Отварите киноа.', 'Нарежьте овощи.', 'Смешайте и заправьте маслом.'],
  nutrition: ['240 ккал', '8 г', '9 г', '30 г', '5 г'],
  minutes: 20,
  servings: 2,
  categories: [4],
  nutrients: [3, 2],
})

salad.values.categories = [4, 3]
salad.status = 'modified'

/* Never published: the smoothie has no nutrition written, and the site would print no table. */
recipe({
  title: ['Смузи со шпинатом', 'Spinach smoothie'],
  slug: 'spinach-smoothie',
  lead: ['Шпинат, банан и яблоко.', 'Spinach, banana and apple.'],
  gallery: ['recipes/smoothie.svg'],
  ingredients: ['Шпинат — 50 г', 'Банан — 1 шт.', 'Яблоко — 1 шт.'],
  steps: ['Сложите всё в блендер и взбейте.'],
  minutes: 5,
  servings: 1,
  categories: [5],
  nutrients: [5, 1],
  status: 'draft',
})

recipe({
  title: ['Запечённая рыба', 'Baked fish'],
  slug: 'baked-fish',
  lead: ['Филе в фольге с лимоном и травами.', 'A fillet in foil with lemon and herbs.'],
  gallery: ['recipes/baked-fish.svg', 'recipes/table.svg'],
  ingredients: ['Филе трески — 400 г', 'Лимон — 1 шт.', 'Тимьян — 3 веточки'],
  steps: ['Разогрейте духовку до 200°.', 'Заверните рыбу в фольгу с лимоном.', 'Запекайте час.'],
  nutrition: ['210 ккал', '34 г', '6 г', '2 г', ''],
  minutes: 75,
  servings: 2,
  categories: [3],
  nutrients: [4, 3],
  services: [4],
})

/* In the bin: named where it is already chosen, never offered. */
const granola = recipe({
  title: ['Гранола', 'Granola'],
  slug: 'granola',
  lead: ['Запечённые хлопья с орехами.', 'Baked oats with nuts.'],
  gallery: [],
  ingredients: ['Овсяные хлопья — 300 г', 'Орехи — 100 г', 'Мёд — 3 ст. л.'],
  steps: ['Смешайте всё и запекайте 25 минут.'],
  minutes: 35,
  servings: 6,
  categories: [1],
  nutrients: [2],
})

granola.deleted_at = '2026-09-21T12:00:00+00:00'

/* ---------------------------------------------------------------------------- reading ----- */

export function find(id: number): RecipeRecord | null {
  return recipes.find((record) => record.id === id) ?? null
}

export function findTerm(kind: TermKind, id: number): TermRecord | null {
  return terms(kind).find((row) => row.id === id) ?? null
}

export function ids(value: unknown): number[] {
  return Array.isArray(value)
    ? value.map(Number).filter((one) => Number.isInteger(one) && one > 0)
    : []
}

/** One recipe as a screen reads it — per request, in the language the panel is open in. */
export function row(record: RecipeRecord, locale: string): RecipeRow {
  const live = record.live === null ? '' : text(record.live.slug, locale)
  const path = live === '' ? null : `${PREFIX}/${live}`
  const minutes = record.values.total_minutes

  return {
    id: record.id,
    title: text(record.values.title, locale) || text(record.values.slug, locale) || `#${record.id}`,
    slug: text(record.values.slug, locale),
    path,
    url: path === null ? null : `https://webx-demo.test/${path}`,
    cover: cover(record.values),
    minutes: typeof minutes === 'number' && minutes > 0 ? minutes : null,
    status: record.status,
    position: record.position,
    categories: named('categories', ids(record.values.categories), locale),
    published_at: record.published_at,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
    revision: revision(record),
  }
}

/** The list (§5.10): the whole of it, in its one order, narrowed by whatever was asked. */
export function listRecipes(query: URLSearchParams, locale: string) {
  const trashed = query.get('trashed') === '1'
  const search = (query.get('q') ?? query.get('search') ?? '').trim().toLowerCase()
  const status = query.get('status') ?? ''
  const by = (name: string): number | null => {
    const raw = Number(query.get(name) ?? '')

    return Number.isInteger(raw) && raw > 0 ? raw : null
  }

  let found = recipes.filter((record) => (record.deleted_at === null) !== trashed)

  if (search !== '') {
    found = found.filter((record) =>
      [record.values.title, record.values.slug].some((value) =>
        Object.values((value ?? {}) as Record<string, string>).some((one) =>
          String(one).toLowerCase().includes(search),
        ),
      ),
    )
  }

  if (status !== '') found = found.filter((record) => record.status === status)

  for (const [name, field] of [
    ['category', 'categories'],
    ['nutrient', 'nutrients'],
    ['service', 'services'],
  ] as const) {
    const id = by(name)

    if (id !== null) found = found.filter((record) => ids(record.values[field]).includes(id))
  }

  found.sort((one, two) =>
    trashed
      ? Date.parse(two.deleted_at ?? '') - Date.parse(one.deleted_at ?? '')
      : one.position - two.position,
  )

  const offer = (kind: TermKind): RecipeTermRef[] =>
    terms(kind)
      .filter((one) => one.deleted_at === null)
      .map((one) => ({ id: one.id, title: text(one.title, locale) || one.name }))

  return {
    data: found.map((record) => row(record, locale)),
    filters: {
      categories: offer('categories'),
      nutrients: offer('nutrients'),
      services: services
        .filter((one) => one.deleted_at === null)
        .map((one) => ({ id: one.id, title: serviceRow(one, locale).title })),
    },
  }
}

export function createRecipe(title: string, slug: string): RecipeRecord {
  const record: RecipeRecord = {
    id: Math.max(0, ...recipes.map((one) => one.id)) + 1,
    values: {
      title: { ru: title, en: title },
      slug: { ru: slug, en: slug },
      lead: {},
      gallery: [],
      ingredients: {},
      method: {},
      ...Object.fromEntries(NUTRITION.map((key) => [`nutrition.${key}`, {}])),
      total_minutes: null,
      servings: null,
      categories: [],
      nutrients: [],
      services: [],
      related: [],
      seo: {},
    },
    live: null,
    status: 'draft',
    // A new one goes to the end of the one order — where the next person to reorder sees it.
    position: Math.max(0, ...recipes.map((one) => one.position)) + 1,
    published_at: null,
    updated_at: new Date().toISOString(),
    deleted_at: null,
    versions: [],
    snapshots: {},
  }

  recipes.push(record)

  return record
}

/**
 * A save of the form: what travelled, over what was there — nothing a type would not keep. The
 * gallery keeps keys and words, never an address; the relations keep ids, in order, once; the
 * nutrition keeps its five keys and not a sixth.
 */
export function writeRecipe(record: RecipeRecord, sent: Record<string, unknown>): void {
  const values = { ...sent }

  for (const name of Object.keys(values)) {
    if (name.startsWith('nutrition.') && !NUTRITION.some((key) => name === `nutrition.${key}`)) {
      delete values[name]
    }
  }

  if (Array.isArray(values.gallery)) {
    values.gallery = (values.gallery as Record<string, unknown>[])
      .filter((one) => typeof one?.path === 'string')
      .map((one) => ({
        path: one.path,
        ...(one.alt === undefined ? {} : { alt: one.alt }),
        ...(one.title === undefined ? {} : { title: one.title }),
      }))
  }

  for (const name of ['categories', 'nutrients', 'services', 'related']) {
    if (values[name] !== undefined) values[name] = [...new Set(ids(values[name]))]
  }

  if (values.related !== undefined) {
    values.related = ids(values.related).filter((id) => id !== record.id)
  }

  record.values = { ...record.values, ...values }
  record.updated_at = new Date().toISOString()
  restate(record)
}

/** The one order (decision 4). What the screen did not send keeps its place after what it did. */
export function reorderRecipes(order: number[]): void {
  const rest = recipes
    .filter((record) => !order.includes(record.id))
    .sort((one, two) => one.position - two.position)
  const sequence = [...order.flatMap((id) => find(id) ?? []), ...rest]

  sequence.forEach((record, index) => {
    record.position = index + 1
  })
}

export function publish(record: RecipeRecord): void {
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
export function discard(record: RecipeRecord): void {
  if (record.live !== null) {
    for (const field of draftedFields({ ...record.values, ...record.live })) {
      record.values[field] = clone(record.live[field] ?? null)
    }
  }

  record.updated_at = new Date().toISOString()
  restate(record)
}

export function restoreVersion(record: RecipeRecord, number: number): boolean {
  const snapshot = record.snapshots[number]

  if (snapshot === undefined) return false

  for (const field of draftedFields(snapshot)) {
    record.values[field] = clone(snapshot[field])
  }

  record.updated_at = new Date().toISOString()
  restate(record)

  return true
}

export function revision(record: RecipeRecord): string {
  return `${record.id}:${record.updated_at}`
}

/** One recipe as its editor opens it — `{ recipe, values, revision, prefix, preview_url }`. */
export function recipeDetail(record: RecipeRecord, locale: string) {
  return {
    recipe: row(record, locale),
    values: clone(record.values),
    revision: revision(record),
    prefix: PREFIX,
    preview_url: `/preview/recipe/${record.id}`,
  }
}

/* ---------------------------------------------------------------------- categories ----- */

export function termRow(kind: TermKind, record: TermRecord, locale: string) {
  const field = kind === 'categories' ? 'categories' : 'nutrients'

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
    recipes_count: recipes.filter(
      (one) => one.deleted_at === null && ids(one.values[field]).includes(record.id),
    ).length,
  }
}

export function termDetail(kind: TermKind, record: TermRecord, locale: string) {
  return {
    category: termRow(kind, record, locale),
    values:
      kind === 'categories'
        ? {
            ...record.extra,
            title: record.title,
            slug: record.slug,
            is_visible: record.is_visible,
            lead: record.lead,
            cover: record.cover,
            seo: record.seo,
          }
        : { ...record.extra, title: record.title, is_visible: record.is_visible },
    prefix: kind === 'categories' ? PREFIX : null,
  }
}

export function createTerm(kind: TermKind, title: LocalizedValue, slug: string): TermRecord {
  const list = terms(kind)
  const id = Math.max(0, ...list.map((one) => one.id)) + 1
  const address = kind === 'categories' ? `${PREFIX}/${slug}` : null
  const record: TermRecord = {
    id,
    name: Object.values(title)[0] ?? '',
    title,
    slug: kind === 'categories' ? { ru: slug, en: slug } : null,
    path: address,
    url: address === null ? null : `https://webx-demo.test/${address}`,
    is_visible: true,
    position: list.length + 1,
    deleted_at: null,
    lead: {},
    cover: null,
    seo: {},
    extra: {},
  }

  list.push(record)

  return record
}

export function writeTerm(
  kind: TermKind,
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
    else if (kind === 'nutrients') record.extra[name] = value
    else if (name === 'slug') record.slug = (value ?? {}) as LocalizedValue
    else if (name === 'lead') record.lead = (value ?? {}) as LocalizedValue
    else if (name === 'cover') record.cover = (value ?? null) as Record<string, unknown> | null
    else if (name === 'seo') record.seo = (value ?? {}) as Record<string, unknown>
    else record.extra[name] = value
  }

  record.name = text(record.title, locale) || record.name

  if (kind === 'categories') {
    const slug = text(record.slug, locale) || text(record.slug, 'ru')

    record.path = `${PREFIX}/${slug}`
    record.url = `https://webx-demo.test/${record.path}`
  }

  return null
}

export function reorderTerms(kind: TermKind, order: number[]): void {
  const list = terms(kind)
  const moved = order.map((id) => findTerm(kind, id)).filter((one): one is TermRecord => !!one)
  const rest = list.filter((one) => !order.includes(one.id))

  list.splice(0, list.length, ...moved, ...rest)
  list.forEach((one, index) => {
    one.position = index + 1
  })
}

/* ------------------------------------------------------------------------------ site ----- */

/** On the site: published, not in the bin, and not taken off it. */
export function visible(record: RecipeRecord): boolean {
  return record.deleted_at === null && record.live !== null && record.status !== 'unpublished'
}

/**
 * A card, as `Rendering\Cards` makes one (§5.8), out of what the site shows — the published
 * values, never the draft — in one language.
 */
export function card(record: RecipeRecord, locale: string, draft = false) {
  const values = draft ? record.values : (record.live ?? record.values)
  const slug = text(values.slug, locale)
  const gallery = pictures(values.gallery)

  return {
    id: record.id,
    url: `/${PREFIX}/${slug}`,
    title: text(values.title, locale),
    lead: text(values.lead, locale),
    cover: gallery[0] ?? null,
    gallery,
    minutes: typeof values.total_minutes === 'number' ? values.total_minutes : null,
    time: minutesText(values.total_minutes),
    servings: typeof values.servings === 'number' ? values.servings : null,
    categories: ids(values.categories),
    nutrients: ids(values.nutrients).flatMap((id) => {
      const found = findTerm('nutrients', id)

      return found && found.deleted_at === null && found.is_visible
        ? [{ id, title: text(found.title, locale) }]
        : []
    }),
  }
}

/**
 * The card a component is tried on (`BlockShapes` sample of `recipes.card`): the first visible
 * recipe, with the two keys §5.1 of the components spec adds beside `categories` — the chosen
 * categories and services with their addresses, in the order they were chosen.
 */
export function sampleCard(locale = 'ru'): Record<string, unknown> {
  const record = recipes.find(visible)

  if (record === undefined) return {}

  const page = recipePage(record, locale)

  return {
    ...card(record, locale),
    category_links: ids(record.values.categories).flatMap((id) => {
      const found = findTerm('categories', id)

      return found && found.deleted_at === null
        ? [{ id, title: text(found.title, locale), url: `/${found.path}` }]
        : []
    }),
    service_links: page.services.map((one, index) => ({
      id: ids(record.values.services)[index] ?? index,
      title: one.title,
      url: one.url,
    })),
  }
}

/**
 * A `wx-collection` value of the `recipes` source, read the way `RecipesSource` reads it: the
 * visible recipes of the chosen categories (none — all), related to the chosen services (§3.6),
 * in the one order, up to the limit. `groups` are the nutrients the catalog filters by.
 */
export function resolveRecipes(stored: unknown, locale = 'ru'): Record<string, unknown> {
  const value = (typeof stored === 'object' && stored !== null ? stored : {}) as Record<
    string,
    unknown
  >
  const chosen = ids(value.categories)
  const limit = typeof value.limit === 'number' && value.limit >= 1 ? value.limit : null
  const related = value.related as { type?: string; ids?: unknown } | null | undefined
  const services = related?.type === 'service' ? ids(related.ids) : []

  const items = recipes
    .filter(visible)
    .filter((record) => {
      const values = record.live!

      return (
        (chosen.length === 0 || ids(values.categories).some((id) => chosen.includes(id))) &&
        (services.length === 0 || ids(values.services).some((id) => services.includes(id)))
      )
    })
    .sort((one, two) => one.position - two.position)
    .filter((record) => text(record.live!.title, locale) !== '')
    .slice(0, limit ?? undefined)
    .map((record) => card(record, locale))

  const used = new Set(items.flatMap((item) => item.nutrients.map((one) => one.id)))

  return {
    items,
    groups: recipeNutrients
      .filter((one) => one.deleted_at === null && one.is_visible && used.has(one.id))
      .map((one) => ({
        id: one.id,
        title: text(one.title, locale),
        items: items.filter((item) => item.nutrients.some((n) => n.id === one.id)).map((i) => i.id),
      })),
    filter: value.filter === true,
  }
}

/** Everything the page of one recipe prints (§5.5), out of its draft — this is a preview. */
export function recipePage(record: RecipeRecord, locale = 'ru') {
  const values = record.values
  const base = card(record, locale, true)

  return {
    ...base,
    ingredients: text(values.ingredients, locale),
    method: text(values.method, locale),
    note: text(values['author-note'], locale),
    categoryLinks: ids(values.categories).flatMap((id) => {
      const found = findTerm('categories', id)

      return found && found.deleted_at === null
        ? [{ title: text(found.title, locale), url: `/${found.path}` }]
        : []
    }),
    nutrition: NUTRITION.map((key) => ({
      key,
      value: text(values[`nutrition.${key}`], locale),
    })).filter((one) => one.value !== ''),
    services: ids(values.services).flatMap((id) => {
      const found = findService(id)

      if (found === null || found.deleted_at !== null || found.live === null) return []

      const drawn = serviceRow(found, locale)

      return [{ title: drawn.title, url: drawn.url ?? '#', lead: drawn.lead }]
    }),
    similar: similar(record, locale),
  }
}

/**
 * §5.6: the ones chosen by hand, visible, in their order — not topped up by the pick; none chosen,
 * the pick: a shared category 2, a shared service 2, a shared nutrient 1, nothing at zero.
 */
export function similar(record: RecipeRecord, locale: string, limit = 3) {
  const values = record.values
  const chosen = ids(values.related)

  if (chosen.length > 0) {
    return chosen
      .map((id) => find(id))
      .filter((one): one is RecipeRecord => one !== null && visible(one))
      .slice(0, limit)
      .map((one) => card(one, locale))
  }

  const mine = {
    categories: ids(values.categories),
    services: ids(values.services),
    nutrients: ids(values.nutrients),
  }

  return recipes
    .filter((one) => one.id !== record.id && visible(one))
    .map((one) => {
      const theirs = one.live!
      const shared = (field: keyof typeof mine) =>
        ids(theirs[field]).filter((id) => mine[field].includes(id)).length

      return {
        one,
        score: shared('categories') * 2 + shared('services') * 2 + shared('nutrients'),
      }
    })
    .filter((scored) => scored.score > 0)
    .sort((a, b) => b.score - a.score || a.one.position - b.one.position)
    .slice(0, limit)
    .map((scored) => card(scored.one, locale))
}

/* --------------------------------------------------------------------------- helpers ----- */

function restate(record: RecipeRecord): void {
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

function named(kind: TermKind, list: number[], locale: string): RecipeTermRef[] {
  return list.flatMap((id) => {
    const found = findTerm(kind, id)

    return found === null ? [] : [{ id, title: text(found.title, locale) || found.name }]
  })
}

/** `45 мин`, `1 ч 15 мин` — the way the Russian page of the preview prints the time. */
export function minutesText(value: unknown): string {
  if (typeof value !== 'number' || value <= 0) return ''

  const hours = Math.floor(value / 60)
  const rest = value % 60

  if (hours === 0) return `${rest} мин`

  return rest === 0 ? `${hours} ч` : `${hours} ч ${rest} мин`
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

function shift(from: string, days: number): string {
  const at = new Date(from)

  at.setUTCDate(at.getUTCDate() + days)

  return at.toISOString().replace(/\.\d+Z$/, '+00:00')
}
