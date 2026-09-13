export type InputSize = 'sm' | 'md' | 'lg'
export type InputStatus = 'default' | 'success' | 'warning' | 'error'
export type InputNativeType = 'text' | 'password' | 'email' | 'number' | 'search' | 'tel' | 'url'

import type { LocalizedFieldProps, LocalizedValue } from '../../composables/useLocalized'

export interface InputProps extends LocalizedFieldProps {
  /** Overrides the id generated for the control (and used by a WxFormItem label). */
  id?: string
  /** `type` attribute of the underlying `<input>`. */
  type?: InputNativeType
  size?: InputSize
  /** Validation state — usually driven by `WxFormItem`. */
  status?: InputStatus
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  /** Shows a clear button while the field has a value. */
  clearable?: boolean
  maxlength?: number
  /** Renders a `current / max` counter (requires `maxlength`). */
  showCount?: boolean
  autocomplete?: string
  /** Accessible label used when the input has no visible `<label>`. */
  ariaLabel?: string
}

/** A record once `localized` is on — the same field, in every language the site publishes in. */
export type InputModelValue = string | number | LocalizedValue | undefined

export interface InputEmits {
  input: [value: string]
  change: [value: string]
  focus: [event: FocusEvent]
  blur: [event: FocusEvent]
  clear: []
}
