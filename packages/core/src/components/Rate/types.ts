import type { ControlSize } from '../../composables/useFormField'

export interface RateProps {
  /** How many stars to draw. */
  max?: number
  /** Allow halves, set by clicking the left side of a star. */
  allowHalf?: boolean
  /** Clicking the current value sets the rating back to zero. */
  clearable?: boolean
  /** Show the value next to the stars. */
  showValue?: boolean
  /** Display only — no pointer or keyboard interaction. */
  readonly?: boolean
  disabled?: boolean
  size?: ControlSize
  id?: string
  name?: string
  /** Accessible label used when there is no visible `<label>`. */
  ariaLabel?: string
}

export interface RateEmits {
  change: [value: number]
}
