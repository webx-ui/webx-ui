export type TooltipSide = 'top' | 'right' | 'bottom' | 'left'
export type TooltipAlign = 'start' | 'center' | 'end'

export interface TooltipProps {
  /** The text. Use the `content` slot for anything richer. */
  content?: string
  /** Which side of the trigger it prefers; it flips when there is no room. */
  side?: TooltipSide
  align?: TooltipAlign
  /** Distance from the trigger, in pixels. */
  offset?: number
  /**
   * How long the pointer has to rest before the tip appears, in milliseconds. The
   * first tip in a group waits; the ones after it come at once, because by then the
   * reader has said what they are doing.
   */
  delay?: number
  /** Draws the little pointer at the trigger. */
  arrow?: boolean
  /** Largest width the tip takes before it wraps. */
  maxWidth?: number | string
  /** Nothing opens. For a tip whose trigger is temporarily meaningless. */
  disabled?: boolean
  /** Render the tip in a portal so it escapes `overflow: hidden`. */
  teleport?: boolean
}
