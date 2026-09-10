import type { ControlSize, ControlStatus } from '../../composables/useFormField'

/** `[start, end]`, or `null` while nothing is picked. */
export type DateRangePickerModelValue = string[] | Date[] | null

export interface DateRangePickerProps {
  /** Format each end is stored in. Defaults to `yyyy-MM-dd`; `'date'` keeps `Date` objects. */
  valueFormat?: string
  /** Format the field shows. */
  format?: string
  placeholder?: string
  clearable?: boolean
  minDate?: string | Date
  maxDate?: string | Date
  /** How many months to show side by side. Two makes picking across a boundary easy. */
  months?: number
  /** First day of the week: 0 is Sunday, 1 is Monday. */
  weekStart?: number
  /** Apply as soon as the second date is picked, with no confirm button. */
  autoApply?: boolean
  /** Let the range be typed as well as picked. */
  textInput?: boolean
  /** Render the calendar in a portal so it escapes `overflow: hidden`. */
  teleport?: boolean
  disabled?: boolean
  readonly?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  name?: string
  /** Accessible label used when the field has no visible `<label>`. */
  ariaLabel?: string
}

export interface DateRangePickerEmits {
  change: [value: DateRangePickerModelValue]
  clear: []
  open: []
  close: []
}
