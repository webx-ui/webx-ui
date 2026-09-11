import type { ControlSize } from '../../composables/useFormField'
import type { IconName } from '../Icon/types'

export type SegmentedValue = string | number

export interface SegmentedOption {
  label?: string
  value: SegmentedValue
  icon?: IconName
  disabled?: boolean
  /** Accessible name when the segment shows only an icon. */
  ariaLabel?: string
}

export interface SegmentedProps {
  options?: SegmentedOption[]
  size?: ControlSize
  /** Stretches to the width it is given, the segments sharing it equally. */
  block?: boolean
  disabled?: boolean
  /** Accessible name for the group. */
  ariaLabel?: string
}

export interface SegmentedEmits {
  change: [value: SegmentedValue]
}
