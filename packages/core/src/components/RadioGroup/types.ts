import type { ChoiceValue, ControlSize } from '../../composables/useFormField'

export interface RadioOption {
  label: string
  value: ChoiceValue
  disabled?: boolean
}

export interface RadioGroupProps {
  /** Renders the children for you. Omit it and pass `WxRadio`s in the slot instead. */
  options?: RadioOption[]
  disabled?: boolean
  size?: ControlSize
  /** Shared `name` for the underlying inputs. Generated when omitted. */
  name?: string
  /** Lay the radios out in a row instead of a column. */
  inline?: boolean
}

export interface RadioGroupEmits {
  change: [value: ChoiceValue]
}
