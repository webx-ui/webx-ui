import type { ControlSize, ControlStatus } from '../../composables/useFormField'

/**
 * Narrower than `ChoiceValue`: the underlying combobox accepts strings, numbers and
 * objects but not booleans, and a boolean has no place as a select value anyway.
 */
export type SelectValue = string | number

export interface SelectOption {
  label: string
  value: SelectValue
  disabled?: boolean
}

export type SelectModelValue = SelectValue | SelectValue[] | null

export interface SelectProps {
  options?: SelectOption[]
  /** Allow more than one value. The model becomes an array. */
  multiple?: boolean
  /** Show a search field that filters the options. */
  filterable?: boolean
  /** Show a button that empties the selection. */
  clearable?: boolean
  placeholder?: string
  /** Text shown when filtering matches nothing. */
  emptyText?: string
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

export interface SelectEmits {
  change: [value: SelectModelValue]
  clear: []
  open: []
  close: []
  /** The search term, on every keystroke. Use it to load options from a backend. */
  search: [term: string]
}
