import type { ControlSize, ControlStatus } from '../../composables/useFormField'

export interface InputNumberProps {
  min?: number
  max?: number
  /** Amount added or removed by the buttons and the arrow keys. */
  step?: number
  /** Decimal places kept when rounding. Defaults to the precision of `step`. */
  precision?: number
  /** Show the increment and decrement buttons. */
  controls?: boolean
  /** `sides` puts them left and right of the field; `right` stacks them at the end. */
  controlsPosition?: 'sides' | 'right'
  /**
   * Let the mouse wheel change the value while the field is focused.
   * Off by default: scrolling a long form past a focused field would otherwise
   * edit it silently, which is how these fields lose data.
   */
  wheel?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  name?: string
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  /** Accessible label used when the field has no visible `<label>`. */
  ariaLabel?: string
}

export type InputNumberModelValue = number | null | undefined

export interface InputNumberEmits {
  change: [value: number | null]
  focus: [event: FocusEvent]
  blur: [event: FocusEvent]
}
