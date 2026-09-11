export type IndicatorType = 'danger' | 'primary' | 'success' | 'warning' | 'info' | 'neutral'
export type IndicatorPlacement = 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left'

export interface IndicatorProps {
  /** The count (or any short text) to show. Numbers above `max` become `99+`. */
  value?: number | string
  /** Largest number shown in full. Only applies to numeric values. */
  max?: number
  /** Draws a plain dot instead of a number — "something happened here". */
  dot?: boolean
  /** Keeps the mark visible at 0; by default a zero count hides it. */
  showZero?: boolean
  /** Corner the mark sits in, relative to the wrapped element. */
  placement?: IndicatorPlacement
  /** Nudge in pixels: `[x, y]`, positive values move right and down. */
  offset?: [number, number]
  type?: IndicatorType
  /** Hides the mark without unmounting what it wraps. */
  hidden?: boolean
  /** Accessible text for the mark — e.g. `2 items in cart`. */
  label?: string
}
