export type InputSize = 'sm' | 'md' | 'lg'
export type InputStatus = 'default' | 'success' | 'warning' | 'error'
export type InputNativeType = 'text' | 'password' | 'email' | 'number' | 'search' | 'tel' | 'url'

export interface InputProps {
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

export type InputModelValue = string | number | undefined

export interface InputEmits {
  input: [value: string]
  change: [value: string]
  focus: [event: FocusEvent]
  blur: [event: FocusEvent]
  clear: []
}
