import type { ControlSize, ControlStatus } from '../../composables/useFormField'

/**
 * A suggestion. `value` is the text that lands in the input when it is picked, so it
 * is what the user sees after choosing; anything else you attach (an id, a record)
 * comes back untouched in the `select` event and in the `option` slot.
 */
export interface AutocompleteOption {
  value: string
  /** Shown in the list instead of `value`. */
  label?: string
  /** Second line under the label — a hint, a path, a category. */
  description?: string
  disabled?: boolean
  [key: string]: unknown
}

export interface AutocompleteProps {
  /** The suggestions to show. With `remote`, this is whatever the backend returned. */
  options?: AutocompleteOption[]
  /**
   * The list is filtered by the backend: keep every option as given instead of
   * matching it against the typed text again.
   */
  remote?: boolean
  /** Milliseconds of quiet typing before `search` fires. */
  debounce?: number
  /** Shows that a request is in flight. */
  loading?: boolean
  loadingText?: string
  /** Text shown when nothing matches. */
  emptyText?: string
  /** Characters needed before the list opens and `search` fires. */
  minLength?: number
  placeholder?: string
  /** Shows a button that empties the field. */
  clearable?: boolean
  /** Opens the list as soon as the field is focused. */
  openOnFocus?: boolean
  /** Render the list in a portal so it escapes `overflow: hidden`. */
  teleport?: boolean
  disabled?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  name?: string
  /** Accessible label used when there is no visible `<label>`. */
  ariaLabel?: string
}

export interface AutocompleteEmits {
  /** The text changed — typed or picked. */
  change: [value: string]
  /** Debounced search term. Load your options in response to this. */
  search: [term: string]
  /** A suggestion was picked. */
  select: [option: AutocompleteOption]
  clear: []
  open: []
  close: []
}
