import type { RelationCandidate } from '../../../../packages/module-admin/src/relations/api'
import { recipes, row as recipeRow, visible as recipeVisible, type RecipeRecord } from './recipes'
import { row as serviceRow, services, type ServiceRecord } from './services'
import { forms } from './inbox'
import { notifyCandidates } from './notifications'

/**
 * The targets of relations the playground has (§3.3 of the recipes spec): the services and the
 * recipes, both out of their own fixtures, and the forms of the inbox (§4.3 of the vacancies
 * spec) — what a vacancy is answered with.
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

/** `slug` is the forms' own, as `FormTarget::row()` adds it. */
type Candidate = RelationCandidate & { slug?: string }

interface Target {
  all: (locale: string) => { candidate: Candidate; deleted: boolean }[]
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
  /*
   * `FormTarget` of `module-inbox`: the title, the slug under it and as `slug` — what
   * `wx-inbox-form` keeps — ordered by `position`. A form that is switched off stays
   * choosable-as-chosen and is marked, as a service in the bin is. Searched by the slug too.
   */
  'inbox-form': {
    all: (locale) =>
      [...forms]
        .sort((one, two) => one.position - two.position)
        .map((form) => ({
          candidate: {
            id: form.id,
            title: form.title[locale] || form.title.ru || form.slug,
            subtitle: form.slug,
            slug: form.slug,
            thumb: null,
            visible: form.is_enabled,
          },
          deleted: false,
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
): Candidate[] | null {
  const found: Target | undefined =
    TARGETS[target] ??
    // One target per event of the notifications (`ChannelTarget`): `notify-inbox-submission`.
    (notifyCandidates(target) === null ? undefined : { all: () => notifyCandidates(target) ?? [] })

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

  return (
    all
      .filter((one) => !one.deleted)
      .map((one) => one.candidate)
      .filter(
        (one) =>
          term === '' ||
          one.title.toLowerCase().includes(term) ||
          (one.slug?.includes(term) ?? false),
      )
      // The exact slug first, as `FormTarget` orders it: that is how a stored slug is named.
      .sort((one, two) => Number(two.slug === term) - Number(one.slug === term))
      .slice(0, 20)
  )
}
