import type { LocaleOption } from '../../composables/useLocalized'

export type LocalesVariant = 'inline' | 'tabs'

export interface LocalesProps {
  /**
   * Languages to offer. Left out, the list provided by the panel is used — which is what a
   * field does, so that a form does not repeat the site's settings on every control.
   */
  locales?: LocaleOption[]
  /**
   * `inline` tucks the selector into the top-right corner of what it wraps and folds down to
   * the current language until it is pointed at — for a single field, where a row of tabs
   * above every input would be louder than the inputs.
   *
   * `tabs` puts them in a row above, for a whole section edited one language at a time.
   */
  variant?: LocalesVariant
}

export interface LocalesEmits {
  change: [code: string]
}
