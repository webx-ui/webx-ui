import type { ControlSize } from '../../composables/useFormField'

export interface FormItemProps {
  /** Visible label. Use the `label` slot for anything richer than text. */
  label?: string
  /**
   * Field name. When the enclosing `WxForm` has `errors`, messages for this name
   * are shown automatically.
   */
  name?: string
  /** Marks the label with an asterisk. Does not validate anything by itself. */
  required?: boolean
  /** Error message(s) shown under the control; overrides the form's errors. */
  error?: string | string[]
  /** Hint shown under the control while there is no error. */
  help?: string
  /** Disables every control inside this item. */
  disabled?: boolean
  /** Size for controls inside this item. */
  size?: ControlSize
  /** Label column width when the form uses `labelPosition="left"`. */
  labelWidth?: string
  /** Renders the error slot area even when empty, so rows do not jump. */
  reserveErrorSpace?: boolean
}
