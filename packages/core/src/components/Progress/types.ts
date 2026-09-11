export type ProgressType = 'line' | 'circle'
export type ProgressStatus = 'default' | 'success' | 'warning' | 'danger'
export type ProgressSize = 'sm' | 'md' | 'lg'

export interface ProgressProps {
  /** How far along, from `0` to `max`. */
  value?: number
  /** What finished looks like. */
  max?: number
  type?: ProgressType
  /** Colour of the bar. `success` and `danger` are outcomes, not decorations. */
  status?: ProgressStatus
  size?: ProgressSize
  /** Thickness of the track. Overrides what `size` would give it. */
  thickness?: number
  /** Shows the figure beside the bar, or inside the ring. */
  showValue?: boolean
  /** Turns the figure into whatever the reader should see: `3 of 8`, `1.2 MB`. */
  formatter?: (value: number, max: number) => string
  /**
   * Work with no end in sight: the bar travels rather than fills, and reports
   * nothing to a screen reader beyond the fact that it is busy.
   */
  indeterminate?: boolean
  /** Accessible name. Give one whenever there is no visible label beside it. */
  ariaLabel?: string
}
