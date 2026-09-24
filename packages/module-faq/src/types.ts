import type { ScreenModel } from '@webx-ui/schema'

/** A category as a row of the list names one: the filter, and the chips on the row. */
export interface QuestionCategoryRef {
  id: number
  title: string
}

/**
 * One question as the section lists it (`QuestionResource`).
 *
 * `locales` is where it can be seen — the languages it has both a question and an answer in —
 * and it is a separate thing from `published` on purpose: "published, and seen nowhere" is the
 * row the list has to point out (decision 9).
 */
export interface QuestionRow {
  id: number
  /** In the language the panel is open in, in another where there is none, `#id` where neither. */
  question: string
  /** Made once, from the question, and never changed (decision 10). */
  anchor: string
  published: boolean
  /** Its place in the whole list. */
  position: number
  locales: string[]
  categories: QuestionCategoryRef[]
  updated_at: string | null
  deleted_at: string | null
}

/** The whole FAQ — no pages: the list is where questions are put in order (§4.6). */
export interface QuestionsList {
  data: QuestionRow[]
  filters: { categories: QuestionCategoryRef[] }
}

export interface QuestionQuery {
  /** The question in any language the site has, or the anchor. */
  search?: string
  /** One category: the list comes in its own order, and a drag writes that order. */
  category?: number | null
  /** What was deleted. The only way back to a question in the bin is through this list. */
  trashed?: boolean
}

/** One question as its form opens it: the row, and the values of `faq.form` by field name. */
export interface QuestionDetail {
  question: QuestionRow
  values: ScreenModel
}
