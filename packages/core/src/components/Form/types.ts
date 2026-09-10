import type { ControlSize, ValidationErrors } from '../../composables/useFormField'

export interface FormProps {
  /**
   * Server-side validation errors, keyed by field name — the shape Laravel's 422
   * responses use. `WxFormItem` picks up the messages for its own `name`.
   */
  errors?: ValidationErrors
  /** Disables every control inside the form. */
  disabled?: boolean
  /** Default size for controls that do not set their own. */
  size?: ControlSize
  /** Where labels sit relative to their control. */
  labelPosition?: 'top' | 'left'
  /** Label column width when `labelPosition` is `left`, e.g. `"160px"`. */
  labelWidth?: string
  /** Space between form items. */
  gap?: 'sm' | 'md' | 'lg'
}

export interface FormEmits {
  submit: [event: SubmitEvent]
  reset: [event: Event]
}
