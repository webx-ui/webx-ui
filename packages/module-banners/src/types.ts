import type { LocalizedValue } from '@webx-ui/core'
import type { LinkValue } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'

/**
 * One place as the list of places draws it (§5.6).
 *
 * A declared place exists before anything is saved into it — `id` is `null` until its first
 * banner makes the row (decision 1) — so a place is named by its key everywhere, never by id.
 */
export interface PlaceRow {
  id: number | null
  /** What a template asks for: `banners('hero')`. */
  key: string
  /** In the panel's language: a declared place's name from the config, an own one's translation. */
  title: string
  /**
   * An own place's name in every language, for the rename dialog: renaming from the one string
   * above would send one language back and lose the rest. Absent on a declared place.
   */
  titles?: LocalizedValue
  /** In the config: cannot be renamed or deleted here, a template asks for it by key. */
  declared: boolean
  /** The layout after the config is merged — `single`, `random` or `slider`. */
  layout: string
  /** Banners outside the bin. */
  count: number
}

/** What makes or renames a place of somebody's own. The title is required in the default language. */
export interface PlaceInput {
  key: string
  title: LocalizedValue
}

/** One banner as the list of a place draws it (§5.6). */
export interface BannerRow {
  id: number
  /** In the panel's language, the default one where there is none, `#id` where neither. */
  title: string
  /** The thumbnail of the picture, or `null` when the library no longer has it. */
  thumb: string | null
  /** Whether a video plays over the picture on the site. */
  video: boolean
  enabled: boolean
  position: number
  updated_at: string | null
  deleted_at: string | null
}

/** The record half of the form's answer — what the head of the form needs. */
export interface BannerSummary {
  id: number
  /** The key of the place the banner stands in. */
  place: string
  title: string
  enabled: boolean
  deleted_at: string | null
}

/** One banner as its form opens it: the record, and the values of `banners.form` by field name. */
export interface BannerDetail {
  banner: BannerSummary
  values: ScreenModel
}

/** One row of `buttons` in the form's values (§3): a word, a link, a look from the config. */
export interface BannerButton {
  label: LocalizedValue
  link: LinkValue | null
  variant: string | null
}
