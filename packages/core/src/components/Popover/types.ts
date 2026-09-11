export type PopoverSide = 'top' | 'right' | 'bottom' | 'left'
export type PopoverAlign = 'start' | 'center' | 'end'

export interface PopoverProps {
  /** Which side of the trigger the panel prefers; it flips when there is no room. */
  side?: PopoverSide
  /** How the panel lines up with the trigger along that side. */
  align?: PopoverAlign
  /** Distance from the trigger, in pixels. */
  offset?: number
  /** Shift along the alignment axis, in pixels. */
  alignOffset?: number
  /** Draws the little pointer at the trigger. */
  arrow?: boolean
  /** Heading of the panel. The `title` slot replaces it. */
  title?: string
  /** Width of the panel: a number in pixels, or any CSS length. */
  width?: number | string
  /** Render the panel in a portal so it escapes `overflow: hidden`. */
  teleport?: boolean
  /** Block the page behind the panel and trap focus in it. */
  modal?: boolean
  /** The trigger stops opening anything. */
  disabled?: boolean
  /** Adds a × in the heading. */
  closable?: boolean
  /** Label of that ×, for screen readers. */
  closeLabel?: string
  /** Accessible name for the panel when it has no visible heading. */
  ariaLabel?: string
}

export interface PopoverEmits {
  open: []
  close: []
}
