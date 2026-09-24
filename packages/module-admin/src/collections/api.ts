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
}

/** What a `wx-collection` field keeps: the choice, never the records. */
export interface CollectionValue {
  /** Empty — all of them. */
  categories: number[]
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
      .then((body) => body.data)

    // A failed answer is not kept: the next field to ask gets a real one rather than the same error.
    sources.catch(() => asked.delete(admin))
    asked.set(admin, sources)
  }

  return sources
}

/** Whatever arrived, as the four keys the server writes — the same `Selection::normalise()` does. */
export function normaliseCollection(value: unknown): CollectionValue {
  const fields =
    typeof value === 'object' && value !== null && !Array.isArray(value)
      ? (value as Record<string, unknown>)
      : {}

  const categories = Array.isArray(fields.categories)
    ? [...new Set(fields.categories.map(Number).filter((id) => Number.isInteger(id) && id > 0))]
    : []

  const limit =
    typeof fields.limit === 'number' && Number.isInteger(fields.limit) ? fields.limit : null

  return {
    categories: categories.sort((a, b) => a - b),
    limit: limit !== null && limit >= 1 ? Math.min(limit, COLLECTION_MAX_LIMIT) : null,
    filter: fields.filter === true,
    markup: typeof fields.markup === 'boolean' ? fields.markup : null,
  }
}

/** What `markup: null` means for this choice: on for the whole collection, off for a part of it. */
export function defaultMarkup(value: CollectionValue): boolean {
  return value.categories.length === 0
}
