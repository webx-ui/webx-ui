import type { ScreenModel } from '@webx-ui/schema'

/** A category as a row of the list names one: the filter, and the chips on the row. */
export interface ReviewCategoryRef {
  id: number
  title: string
}

/**
 * One review as the section lists it (`ReviewResource`, §4.7).
 *
 * `locales` is where it can be seen — the languages it has a text in — and it is a separate
 * thing from `published` on purpose: "published, and seen nowhere" is the row the list has to
 * point out.
 */
export interface ReviewRow {
  id: number
  /**
   * In the language the panel is open in, in the default one where there is none, `#id` where
   * neither — a person's name is usually the same in every language (decision 7).
   */
  name: string
  /** The same way; `''` or `null` when nobody wrote one. */
  job_title: string | null
  /** 1…5, or nothing: a review without stars is a review. */
  rating: number | null
  photo: { thumb: string | null } | null
  published: boolean
  /** Its place in the whole list. */
  position: number
  locales: string[]
  categories: ReviewCategoryRef[]
  updated_at: string | null
  deleted_at: string | null
}

/** Every review — no pages: the list is where reviews are put in order (§4.6). */
export interface ReviewsList {
  data: ReviewRow[]
  filters: { categories: ReviewCategoryRef[] }
}

export interface ReviewQuery {
  /** The name, the job title or the text, in any language the site has. */
  search?: string
  /** One category: the list comes in its own order, and a drag writes that order. */
  category?: number | null
  /** What was deleted. The only way back to a review in the bin is through this list. */
  trashed?: boolean
}

/** The record half of the form's answer — what the head of the form needs. */
export interface ReviewSummary {
  id: number
  name: string
  published: boolean
  deleted_at: string | null
}

/** One review as its form opens it: the record, and the values of `reviews.form` by field name. */
export interface ReviewDetail {
  review: ReviewSummary
  values: ScreenModel
}
