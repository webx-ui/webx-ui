import type { ChoiceValue, ControlSize } from '../../composables/useFormField'

export interface RadioProps {
  /** Value this radio stands for. */
  value: ChoiceValue
  /** Label text. Use the default slot for anything richer. */
  label?: string
  disabled?: boolean
  size?: ControlSize
  id?: string
  name?: string
  /** Accessible label used when there is no visible one. */
  ariaLabel?: string
}

export interface RadioEmits {
  change: [value: ChoiceValue]
}
