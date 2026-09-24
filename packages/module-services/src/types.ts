import type { ScreenModel } from '@webx-ui/schema'

/**
 * Never published · on the site · on the site with edits waiting · taken off it (§4.6).
 *
 * No "scheduled": a service has no date to be published under. "Taken off" and "draft" both mean
 * it is not on the site, and only its history tells them apart.
 */
export type ServiceStatus = 'draft' | 'published' | 'modified' | 'unpublished'

/** A category as a row of the list names one. The first on a service is the main one. */
export interface ServiceCategoryRef {
  id: number
  title: string
  slug?: string
}

/** The cover, with the two addresses `module-media` works out on every read and never stores. */
export interface ServiceCover {
  id: number
  path: string
  url: string
  thumb: string | null
}

/**
 * One service as the section lists it: the draft's title beside the registry's address, which is
 * why a renamed service shows its new name at its old address until it is published.
 */
export interface ServiceRow {
  id: number
  title: string
  slug: string
  lead: string
  /** `null` — no address in the language the panel is open in. */
  path: string | null
  url: string | null
  status: ServiceStatus
  /** Its place in the whole list. */
  position: number
  published_at: string | null
  updated_at: string | null
  deleted_at: string | null
  cover: ServiceCover | null
  /** In the order they were dragged into: the first is the main one. */
  categories: ServiceCategoryRef[]
  /** What this service was when it was read, so a save can be refused rather than written over. */
  revision: string
}

/**
 * The whole catalogue — no pages: the list is where services are put in order, and a drag cannot
 * cross a page boundary (§4.6). The categories travel with it for the filter.
 */
export interface ServicesList {
  data: ServiceRow[]
  filters: { categories: ServiceCategoryRef[] }
}

export interface ServiceQuery {
  /** Title or address, in any language the site has. */
  q?: string
  /** One category: the list comes in its own order, and a drag writes that order. */
  category?: number | null
  status?: ServiceStatus | ''
  /** What was deleted. The only way back to a service in the bin is through this list. */
  trashed?: boolean
}

export interface ServiceInput {
  title: string
  slug?: string
}

/** The values of `services.form` keyed by field name, and the revision they were read at. */
export interface ServiceSave {
  values: ScreenModel
  revision?: string
}

/** One service as its editor opens it. */
export interface ServiceDetail {
  service: ServiceRow
  values: ScreenModel
  revision: string
  /** The first segment of every services address; `''` when the catalogue is at the root. */
  prefix: string
  preview_url: string | null
}

/** A 409: somebody wrote in between. `data` is the service as it now is. */
export interface ServiceConflict {
  message: string
  data: ServiceDetail
}

/** One publication in the history. */
export interface ServiceVersion {
  number: number
  created_at: string | null
  author: string | null
  source: 'panel' | 'mcp' | 'import' | string
  comment: string | null
  is_pinned: boolean
}
