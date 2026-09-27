import type { Paginated } from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'

/**
 * Never published · on the site · on the site with edits waiting · taken off it — the same four a
 * service and a recipe have (§4.10).
 */
export type EventStatus = 'draft' | 'published' | 'modified' | 'unpublished'

/**
 * Which events the list is looking at (decision 6): the ones still ahead — the default, and what
 * the site's lists show — the ones behind, or both.
 */
export type EventWhen = 'upcoming' | 'past' | 'all'

/** A category as a row of the list names one. The first one is the main one. */
export interface EventTermRef {
  id: number
  title: string
}

/**
 * One event as the section lists it (§4.10): the draft's title beside the registry's address,
 * and the date both as the moments it is kept as and as the line the site prints (§4.6).
 */
export interface EventRow {
  id: number
  title: string
  slug: string
  /** `null` — no address in the language the panel is open in. */
  path: string | null
  url: string | null
  /** The first picture of the gallery, which is what every card of the site shows. */
  cover: { thumb: string | null } | null
  /** ISO 8601 with its offset, or `null` for an event whose date is not set (decision 3). */
  starts_at: string | null
  ends_at: string | null
  all_day: boolean
  /** The date the way the site prints it, `date_note` included — `Rendering\When`. */
  when: string
  /** The end, or the start when there is no end, is behind us (decision 7). */
  past: boolean
  status: EventStatus
  /** In the order they were chosen in: the first is the main one. */
  categories: EventTermRef[]
  published_at: string | null
  updated_at: string | null
  deleted_at: string | null
  /** What this event was when it was read, so a save can be refused rather than written over. */
  revision?: string
}

/**
 * A page of events, as Laravel's paginator has it — events pile up for years, and nothing here is
 * dragged into order (decision 4). The filters travel with it; `services` is `null` on a site
 * without the services module, and then there is no such filter.
 */
export interface EventsPage extends Paginated<EventRow> {
  filters: {
    categories: EventTermRef[]
    services: EventTermRef[] | null
  }
}

export interface EventQuery {
  when?: EventWhen
  /** Title or address, in any language the site has. */
  q?: string
  category?: number | null
  service?: number | null
  status?: EventStatus | ''
  /** What was deleted. The only way back to an event in the bin is through this list. */
  trashed?: boolean
  page?: number
  per_page?: number
}

export interface EventInput {
  title: string
  slug?: string
}

/** The values of `events.form` keyed by field name, and the revision they were read at. */
export interface EventSave {
  values: ScreenModel
  revision?: string
}

/** One event as its editor opens it. */
export interface EventDetail {
  event: EventRow
  values: ScreenModel
  revision: string
  /** The first segment of every event address (`webx-events.prefix`); never empty. */
  prefix: string
  /** `null` without `module-blocks`, which owns the preview by token. */
  preview_url: string | null
}

/** A 409: somebody wrote in between. `data` is the event as it now is. */
export interface EventConflict {
  message: string
  data: EventDetail
}

/** One publication in the history. */
export interface EventVersion {
  number: number
  created_at: string | null
  author: string | null
  source: 'panel' | 'mcp' | 'import' | string
  comment: string | null
  is_pinned: boolean
}
