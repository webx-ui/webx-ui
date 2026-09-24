import type { AdminContext } from '../admin'

/**
 * One section a block can show records from, as `GET /api/cms/collections` describes it
 * (`WebxUi\Admin\Collections\CollectionSource` on the server).
 */
export interface CollectionSourceInfo {
  /** What a block schema writes in `props.source`: `faq`. */
  key: string
  /** Already in the panel's language. */
  title: string
  /** The path its categories answer at (`faq/categories`); `null` — a source without them. */
  categories: string | null
  /** Whether it can mark its records up for search engines. */
  markup: boolean
  /**
   * The targets its records can be related to (`CollectionSource::relations()`, §3.6 of the
   * recipes spec) — empty for a source without relations, which has no "only related to".
   */
  relations: CollectionRelationTarget[]
}

/** A target a source's records point at, named for the choice "only related to …". */
export interface CollectionRelationTarget {
  /** Its key in the server's registry of targets: `service`. */
  key: string
  /** Already in the panel's language. */
  title: string
}

/** "Only the records related to these": one target, and the ones of it that were chosen. */
export interface CollectionRelated {
  type: string
  ids: number[]
  /**
   * "Related to the record of the page the block stands on" — written only when on, and then
   * `ids` is always empty: the record is the page's, not a choice.
   */
  current?: true
}

/** What a `wx-collection` field keeps: the choice, never the records. */
export interface CollectionValue {
  /** Empty — all of them. */
  categories: number[]
  /** `null` — not narrowed by relations. */
  related: CollectionRelated | null
  /** `null` — all of them. */
  limit: number | null
  filter: boolean
  /** `null` — the default: on when no category is chosen (decision 8 of the FAQ spec). */
  markup: boolean | null
}

/** The server's ceiling for `limit` (`Selection::MAX_LIMIT`). */
export const COLLECTION_MAX_LIMIT = 100

/**
 * The list is the same for every field on every block of a session, and a page of ten FAQ blocks
 * asking ten times would be ten answers to one question. Kept per panel rather than per module,
 * so a test's fresh panel asks afresh.
 */
const asked = new WeakMap<AdminContext, Promise<CollectionSourceInfo[]>>()

export function collectionSources(admin: AdminContext): Promise<CollectionSourceInfo[]> {
  let sources = asked.get(admin)

  if (sources === undefined) {
    sources = admin.http
      .get<{ data: CollectionSourceInfo[] }>(`${admin.apiPath}/collections`)
      .then((body) => body.data.map((one) => ({ ...one, relations: relationTargets(one) })))

    // A failed answer is not kept: the next field to ask gets a real one rather than the same error.
    sources.catch(() => asked.delete(admin))
    asked.set(admin, sources)
  }

  return sources
}

/**
 * A server from before relations says nothing about them, and a list of bare keys names nothing:
 * both come to the same shape, a key without a title standing for itself.
 */
function relationTargets(source: { relations?: unknown }): CollectionRelationTarget[] {
  if (!Array.isArray(source.relations)) return []

  return source.relations.flatMap((one: unknown): CollectionRelationTarget[] => {
    if (typeof one === 'string') return one === '' ? [] : [{ key: one, title: one }]

    if (
      typeof one === 'object' &&
      one !== null &&
      typeof (one as { key?: unknown }).key === 'string'
    ) {
      const { key, title } = one as { key: string; title?: unknown }

      return [{ key, title: typeof title === 'string' && title !== '' ? title : key }]
    }

    return []
  })
}

function ids(value: unknown): number[] {
  return Array.isArray(value)
    ? [...new Set(value.map(Number).filter((id) => Number.isInteger(id) && id > 0))].sort(
        (a, b) => a - b,
      )
    : []
}

/**
 * A relation filter without a target or without anything chosen of it narrows nothing, and is
 * written as none at all — so "not narrowed" has one spelling in the database.
 */
function related(value: unknown): CollectionRelated | null {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return null

  const {
    type,
    ids: chosen,
    current,
  } = value as { type?: unknown; ids?: unknown; current?: unknown }

  if (current === true && typeof type === 'string' && type !== '') {
    return { type, ids: [], current: true }
  }

  const picked = ids(chosen)

  return typeof type === 'string' && type !== '' && picked.length > 0 ? { type, ids: picked } : null
}

/** Whatever arrived, as the five keys the server writes — the same `Selection::normalise()` does. */
export function normaliseCollection(value: unknown): CollectionValue {
  const fields =
    typeof value === 'object' && value !== null && !Array.isArray(value)
      ? (value as Record<string, unknown>)
      : {}

  const limit =
    typeof fields.limit === 'number' && Number.isInteger(fields.limit) ? fields.limit : null

  return {
    categories: ids(fields.categories),
    related: related(fields.related),
    limit: limit !== null && limit >= 1 ? Math.min(limit, COLLECTION_MAX_LIMIT) : null,
    filter: fields.filter === true,
    markup: typeof fields.markup === 'boolean' ? fields.markup : null,
  }
}

/**
 * What `markup: null` means for this choice: on for the whole collection, off for a part of it —
 * and "the recipes of this service" is a part as much as a category is.
 */
export function defaultMarkup(value: CollectionValue): boolean {
  return value.categories.length === 0 && value.related === null
}
