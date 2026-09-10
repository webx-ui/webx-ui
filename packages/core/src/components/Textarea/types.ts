import type { ControlSize, ControlStatus } from '../../composables/useFormField'

export interface AutosizeOptions {
  minRows?: number
  maxRows?: number
}

export interface TextareaProps {
  size?: ControlSize
  status?: ControlStatus
  id?: string
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  /** Visible rows when autosize is off. */
  rows?: number
  /** Grows with the content. Pass an object to bound the growth. */
  autosize?: boolean | AutosizeOptions
  maxlength?: number
  /** Renders a `current / max` counter (requires `maxlength`). */
  showCount?: boolean
  /** Which way the user may resize the field by hand. */
  resize?: 'none' | 'vertical' | 'both'
  /** Accessible label used when the field has no visible `<label>`. */
  ariaLabel?: string
}

export type TextareaModelValue = string | undefined

export interface TextareaEmits {
  input: [value: string]
  change: [value: string]
  focus: [event: FocusEvent]
  blur: [event: FocusEvent]
}
