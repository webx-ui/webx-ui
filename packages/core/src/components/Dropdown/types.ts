export type DropdownSide = 'top' | 'right' | 'bottom' | 'left'
export type DropdownAlign = 'start' | 'center' | 'end'

export interface DropdownProps {
  /** Which side of the trigger the panel opens on. */
  side?: DropdownSide
  /** How the panel lines up with the trigger along that side. */
  align?: DropdownAlign
  /** Gap between the trigger and the panel, in pixels. */
  offset?: number
  /** Shift along the trigger's edge, in pixels. */
  alignOffset?: number
  /**
   * Closes the panel when something inside it is clicked — menu behaviour. Turn it
   * off for a panel of filters or checkboxes the user works in before leaving.
   */
  closeOnClick?: boolean
  /** Makes the panel at least as wide as the trigger. */
  matchTriggerWidth?: boolean
  /** Render the panel in a portal so it escapes `overflow: hidden`. */
  teleport?: boolean
  /** Blocks the rest of the page while the panel is open. */
  modal?: boolean
  disabled?: boolean
}

export interface DropdownEmits {
  open: []
  close: []
}
