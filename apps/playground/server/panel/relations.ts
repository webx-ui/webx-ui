import type { RelationCandidate } from '../../../../packages/module-admin/src/relations/api'
import { recipes, row as recipeRow, visible as recipeVisible, type RecipeRecord } from './recipes'
import { row as serviceRow, services, type ServiceRecord } from './services'

/**
 * The two targets of relations the playground has (§3.3 of the recipes spec): the services and
 * the recipes, both out of their own fixtures.
 *
 * Both answer the way `RelationController` does (§3.7): `q` searches, `ids[]` names what a form
 * opened with — the ones the site does not show included, since the field marks them rather than
 * dropping them. A search never offers what is in the bin: a record nobody can see on the site
 * and nobody is working on is not a thing to link to.
 */

function recipeCandidate(record: RecipeRecord, locale: string): RelationCandidate {
  const drawn = recipeRow(record, locale)

  return {
    id: drawn.id,
    title: drawn.title,
    subtitle: drawn.categories[0]?.title ?? null,
    thumb: drawn.cover?.thumb ?? null,
    visible: recipeVisible(record),
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
      [...recipes]
        .sort((one, two) => one.position - two.position)
        .map((record) => ({
          candidate: recipeCandidate(record, locale),
          deleted: record.deleted_at !== null,
        })),
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
