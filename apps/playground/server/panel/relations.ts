import type { RelationCandidate } from '../../../../packages/module-admin/src/relations/api'
import { row as serviceRow, services, type ServiceRecord } from './services'

/**
 * The two targets of relations the playground has (§3.3 of the recipes spec): the services, which
 * are real here, and the recipes, which are a list of names until the recipes module has a fixture
 * of its own — enough for the "similar recipes" field to have something to pick from.
 *
 * Both answer the way `RelationController` does (§3.7): `q` searches, `ids[]` names what a form
 * opened with — the ones the site does not show included, since the field marks them rather than
 * dropping them. A search never offers what is in the bin: a record nobody can see on the site
 * and nobody is working on is not a thing to link to.
 */

interface Recipe {
  id: number
  title: Record<string, string>
  category: Record<string, string>
  visible: boolean
  deleted: boolean
}

const RECIPES: Recipe[] = [
  recipe(1, ['Овсянка с ягодами', 'Porridge with berries'], ['Завтраки', 'Breakfasts']),
  recipe(2, ['Чечевичный суп', 'Lentil soup'], ['Супы', 'Soups']),
  recipe(3, ['Салат с киноа', 'Quinoa salad'], ['Салаты', 'Salads']),
  // Not published yet: chosen, it is marked; the site skips it.
  recipe(4, ['Смузи со шпинатом', 'Spinach smoothie'], ['Напитки', 'Drinks'], false),
  recipe(5, ['Запечённая рыба', 'Baked fish'], ['Основные блюда', 'Mains']),
  // In the bin: named when it is already chosen, never offered.
  recipe(6, ['Гранола', 'Granola'], ['Завтраки', 'Breakfasts'], false, true),
]

function recipe(
  id: number,
  title: [string, string],
  category: [string, string],
  visible = true,
  deleted = false,
): Recipe {
  return {
    id,
    title: { ru: title[0], en: title[1] },
    category: { ru: category[0], en: category[1] },
    visible,
    deleted,
  }
}

const say = (map: Record<string, string>, locale: string): string =>
  map[locale] || map.ru || Object.values(map)[0] || ''

function recipeCandidate(one: Recipe, locale: string): RelationCandidate {
  return {
    id: one.id,
    title: say(one.title, locale),
    subtitle: say(one.category, locale),
    thumb: null,
    visible: one.visible && !one.deleted,
  }
}

function serviceCandidate(record: ServiceRecord, locale: string): RelationCandidate {
  const drawn = serviceRow(record, locale)

  return {
    id: drawn.id,
    title: drawn.title,
    subtitle: drawn.categories[0]?.title ?? null,
    thumb: drawn.cover?.thumb ?? drawn.cover?.url ?? null,
    // What the site shows is what was published and is not in the bin — a draft never is.
    visible: record.deleted_at === null && record.live !== null && record.status !== 'unpublished',
  }
}

interface Target {
  all: (locale: string) => { candidate: RelationCandidate; deleted: boolean }[]
}

const TARGETS: Record<string, Target> = {
  service: {
    all: (locale) =>
      [...services]
        .sort((one, two) => one.position - two.position)
        .map((record) => ({
          candidate: serviceCandidate(record, locale),
          deleted: record.deleted_at !== null,
        })),
  },
  recipe: {
    all: (locale) =>
      RECIPES.map((one) => ({ candidate: recipeCandidate(one, locale), deleted: one.deleted })),
  },
}

/** `null` for a target nobody registered — the real server answers 404 there. */
export function relationCandidates(
  target: string,
  query: URLSearchParams,
  locale: string,
): RelationCandidate[] | null {
  const found = TARGETS[target]

  if (found === undefined) return null

  const all = found.all(locale)
  const ids = query
    .getAll('ids[]')
    .map(Number)
    .filter((id) => Number.isInteger(id) && id > 0)

  if (ids.length > 0) {
    return all.filter((one) => ids.includes(one.candidate.id)).map((one) => one.candidate)
  }

  const term = (query.get('q') ?? '').trim().toLowerCase()

  return all
    .filter((one) => !one.deleted)
    .map((one) => one.candidate)
    .filter((one) => term === '' || one.title.toLowerCase().includes(term))
    .slice(0, 20)
}
