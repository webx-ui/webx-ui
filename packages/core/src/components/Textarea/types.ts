import type { ControlSize, ControlStatus } from '../../composables/useFormField'

export interface AutosizeOptions {
  minRows?: number
  maxRows?: number
}

import type { LocalizedFieldProps, LocalizedValue } from '../../composables/useLocalized'

export interface TextareaProps extends LocalizedFieldProps {
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

/** A record once `localized` is on — the same field, in every language the site publishes in. */
export type TextareaModelValue = string | LocalizedValue | undefined

export interface TextareaEmits {
  input: [value: string]
  change: [value: string]
  focus: [event: FocusEvent]
  blur: [event: FocusEvent]
}
