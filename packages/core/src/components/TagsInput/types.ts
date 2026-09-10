import type { ControlSize, ControlStatus } from '../../composables/useFormField'

export interface TagsInputProps {
  /** Options offered while typing. Refresh them from the `search` event for a backend. */
  suggestions?: string[]
  placeholder?: string
  /** Shown when nothing matches and new tags are not allowed. */
  emptyText?: string
  /** Largest number of tags the user may end up with. */
  max?: number
  /** Allow the same tag twice. */
  duplicates?: boolean
  /** Let Enter add a tag that is not in the suggestions. */
  allowCreate?: boolean
  disabled?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  name?: string
  /** Accessible label used when there is no visible `<label>`. */
  ariaLabel?: string
}

export interface TagsInputEmits {
  change: [value: string[]]
  /** What the user typed, on every keystroke — use it to fetch suggestions. */
  search: [term: string]
}
