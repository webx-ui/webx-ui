import type { ChoiceValue, ControlSize } from '../../composables/useFormField'

export interface SwitchProps {
  /** Value written when the switch is on. */
  activeValue?: ChoiceValue
  /** Value written when the switch is off. */
  inactiveValue?: ChoiceValue
  /** Label text shown next to the track. */
  label?: string
  disabled?: boolean
  size?: ControlSize
  id?: string
  name?: string
  /** Accessible label used when there is no visible one. */
  ariaLabel?: string
}

export interface SwitchEmits {
  change: [value: ChoiceValue]
}
