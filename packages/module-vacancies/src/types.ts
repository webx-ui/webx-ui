import type { ScreenModel } from '@webx-ui/schema'

/**
 * Never published · on the site · on the site with edits waiting · taken off it — the same four a
 * recipe and an event have (§4.11).
 */
export type VacancyStatus = 'draft' | 'published' | 'modified' | 'unpublished'

/**
 * Which vacancies the list is looking at (decision 13): the open ones — the default, and what the
 * site's lists show — the closed ones, or both.
 */
export type VacancyState = 'open' | 'closed' | 'all'

/**
 * Why a vacancy is closed: somebody closed it (`is_closed`), or its last day is behind us
 * (`valid_through`). When both are true, `manual` is what the server says.
 */
export type VacancyClosedReason = 'manual' | 'expired'

/** Where the work is done (decision 16). */
export type VacancyWorkplace = 'onsite' | 'remote' | 'hybrid'

/** A category as a row of the list names one. */
export interface VacancyTermRef {
  id: number
  title: string
}

/** One vacancy as the section lists it (§4.11). */
export interface VacancyRow {
  id: number
  /** The draft's title in the panel's language, or `#id`. */
  title: string
  slug: string
  /** `null` — no address in the language the panel is open in. */
  path: string | null
  /** Where publishing moves it: a slug renamed in the draft, `null` when publishing moves nothing. */
  next_path?: string | null
  /** The language of `path` when it is the site's main one, shown because this language has none. */
  address_locale?: string | null
  url: string | null
  workplace: VacancyWorkplace
  /** In the panel's language, else the default one, else `''`. */
  city: string
  /** schema.org codes: `FULL_TIME`, `PART_TIME`, … */
  employment_types: string[]
  /** The last day it is open, `YYYY-MM-DD` — a calendar day, not a moment. */
  valid_through: string | null
  /** `datePosted`, `YYYY-MM-DD`; set by the first publication. */
  posted_at: string | null
  closed: boolean
  closed_reason: VacancyClosedReason | null
  status: VacancyStatus
  position: number
  categories: VacancyTermRef[]
  published_at: string | null
  updated_at: string | null
  deleted_at: string | null
  /** What this vacancy was when it was read, so a save can be refused rather than written over. */
  revision?: string
}

/**
 * Every vacancy the query asked for, at once: no paginator, because this is the list where the
 * order is dragged, and a drag cannot cross a page (§4.10).
 */
export interface VacanciesList {
  data: VacancyRow[]
  filters: {
    categories: VacancyTermRef[]
  }
}

export interface VacancyQuery {
  state?: VacancyState
  /** Title or address, in any language the site has. */
  q?: string
  category?: number | null
  status?: VacancyStatus | ''
  /** What was deleted. `state` does not apply there. */
  trashed?: boolean
}

export interface VacancyInput {
  title: string
  slug?: string
}

/** The values of `vacancies.form` keyed by field name, and the revision they were read at. */
export interface VacancySave {
  values: ScreenModel
  revision?: string
}

/** One vacancy as its editor opens it. */
export interface VacancyDetail {
  vacancy: VacancyRow
  values: ScreenModel
  revision: string
  /** The first segment of every vacancy address (`webx-vacancies.prefix`); never empty. */
  prefix: string
  /** `null` without `module-blocks`, which owns the preview by token. */
  preview_url: string | null
}

/** A 409: somebody wrote in between. `data` is the vacancy as it now is. */
export interface VacancyConflict {
  message: string
  data: VacancyDetail
}

/** One publication in the history. */
export interface VacancyVersion {
  number: number
  created_at: string | null
  author: string | null
  source: 'panel' | 'mcp' | 'import' | string
  comment: string | null
  is_pinned: boolean
}
