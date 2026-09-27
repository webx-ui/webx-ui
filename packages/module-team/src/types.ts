import type { ScreenModel } from '@webx-ui/schema'

/**
 * One person as the section lists them (§5.7).
 *
 * No `locales` beside `published`, unlike a review: a person is seen in every language once
 * published (decision 8) — an untranslated text leaves the text out, not the person.
 */
export interface MemberRow {
  id: number
  /**
   * In the language the panel is open in, in the default one where there is none, `#id` where
   * neither — a name is usually the same in every language.
   */
  name: string
  /** The same way; `''` or `null` when nobody wrote one. */
  job_title: string | null
  /** The first letters of the name, for a row without a photo. */
  initials: string
  photo: { thumb: string | null } | null
  published: boolean
  /** Their place in the one order (decision 5). */
  position: number
  updated_at: string | null
  deleted_at: string | null
}

/** Everybody — no pages, no filters: the list is where the order is dragged (decision 5). */
export interface MembersList {
  data: MemberRow[]
}

export interface MemberQuery {
  /** The name, the job title or the text, in any language the site has. */
  search?: string
  /** What was deleted. The only way back to a person in the bin is through this list. */
  trashed?: boolean
}

/** The record half of the form's answer — what the head of the form needs. */
export interface MemberSummary {
  id: number
  name: string
  published: boolean
  deleted_at: string | null
}

/** One person as their form opens them: the record, and the values of `team.form` by field name. */
export interface MemberDetail {
  member: MemberSummary
  values: ScreenModel
}

/** One row of `socials` in the form's values (§5.4): a network of the site's list and an address. */
export interface SocialLink {
  network: string | null
  url: string | null
}
