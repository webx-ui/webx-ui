import type { ControlSize, ControlStatus } from '../../composables/useFormField'

export interface ColorPickerProps {
  /** Swatches offered under the picker, as hex strings. */
  presets?: string[]
  /** Show a button that empties the field. */
  clearable?: boolean
  placeholder?: string
  /** Render the panel in a portal so it escapes `overflow: hidden`. */
  teleport?: boolean
  disabled?: boolean
  readonly?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  name?: string
  /** Accessible label used when the field has no visible `<label>`. */
  ariaLabel?: string
}

export interface ColorPickerEmits {
  change: [value: string | null]
}
