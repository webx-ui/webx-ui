import type { ChoiceValue, ControlSize } from '../../composables/useFormField'

export interface CheckboxProps {
  /** Value contributed to a `WxCheckboxGroup`. Ignored when used standalone. */
  value?: ChoiceValue
  /** Label text. Use the default slot for anything richer. */
  label?: string
  /** Third visual state: neither checked nor unchecked. */
  indeterminate?: boolean
  disabled?: boolean
  size?: ControlSize
  id?: string
  name?: string
  /** Accessible label used when there is no visible one. */
  ariaLabel?: string
}

export interface CheckboxEmits {
  change: [checked: boolean]
}
