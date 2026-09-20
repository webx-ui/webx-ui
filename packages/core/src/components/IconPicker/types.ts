import type { ControlSize, ControlStatus } from '../../composables/useFormField'

export interface IconPickerProps {
  /** Show a button that empties the field. */
  clearable?: boolean
  placeholder?: string
  /** What the panel says when nothing matches what was typed. */
  emptyText?: string
  /** Label of the button that empties the field, for screen readers. */
  clearLabel?: string
  /** Render the panel in a portal so it escapes `overflow: hidden`. */
  teleport?: boolean
  disabled?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  name?: string
  /** Accessible label used when the field has no visible `<label>`. */
  ariaLabel?: string
}

export interface IconPickerEmits {
  change: [value: string | null]
}
