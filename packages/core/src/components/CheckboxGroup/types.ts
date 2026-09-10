import type { ChoiceValue, ControlSize } from '../../composables/useFormField'

export interface CheckboxOption {
  label: string
  value: ChoiceValue
  disabled?: boolean
}

export interface CheckboxGroupProps {
  /** Renders the children for you. Omit it and pass `WxCheckbox`es in the slot instead. */
  options?: CheckboxOption[]
  disabled?: boolean
  size?: ControlSize
  /** Shared `name` for the underlying inputs. */
  name?: string
  /** Lay the boxes out in a row instead of a column. */
  inline?: boolean
  /** Smallest number of checked values the user may end up with. */
  min?: number
  /** Largest number of checked values the user may end up with. */
  max?: number
}

export interface CheckboxGroupEmits {
  change: [value: ChoiceValue[]]
}
