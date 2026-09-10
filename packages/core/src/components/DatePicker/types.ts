import type { ControlSize, ControlStatus } from '../../composables/useFormField'

export type DatePickerType = 'date' | 'datetime' | 'time'
export type DatePickerModelValue = string | Date | null

export interface DatePickerProps {
  /** What the picker asks for. `WxTimePicker` and `WxDateTimePicker` are presets of this. */
  type?: DatePickerType
  /**
   * Format the value is *stored* in. Defaults to what a Laravel column expects:
   * `yyyy-MM-dd`, `yyyy-MM-dd HH:mm:ss` or `HH:mm:ss`. Pass `'date'` to keep the
   * model as a `Date` object instead of a string.
   */
  valueFormat?: string
  /** Format the value is *shown* in. Defaults to `dd.MM.yyyy`, plus the time part. */
  format?: string
  placeholder?: string
  clearable?: boolean
  minDate?: string | Date
  maxDate?: string | Date
  /** Include seconds in the time part. */
  seconds?: boolean
  /** Step of the minutes column. */
  minutesIncrement?: number
  /** 24-hour clock. Off means AM/PM. */
  is24?: boolean
  /** First day of the week: 0 is Sunday, 1 is Monday. */
  weekStart?: number
  /** Apply the value as soon as it is picked, with no confirm button. */
  autoApply?: boolean
  /** Let the value be typed as well as picked. */
  textInput?: boolean
  /** Render the menu in a portal so it escapes `overflow: hidden` containers. */
  teleport?: boolean | string
  disabled?: boolean
  readonly?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  name?: string
  /** Accessible label used when the field has no visible `<label>`. */
  ariaLabel?: string
}

export interface DatePickerEmits {
  change: [value: DatePickerModelValue]
  clear: []
  open: []
  close: []
}
