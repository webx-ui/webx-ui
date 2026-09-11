export interface BacktopProps {
  /**
   * What scrolls. A CSS selector, an element, or nothing for the window.
   *
   * Name it in an admin: the page there rarely scrolls — the main column does — and
   * a button watching the window would appear when nothing has happened.
   */
  target?: string | HTMLElement | null
  /** How far down before the button appears, in pixels. */
  visibilityHeight?: number
  /** Distance from the trailing edge, in pixels. */
  right?: number
  /** Distance from the bottom, in pixels. */
  bottom?: number
  /** Glides back up rather than jumping. */
  smooth?: boolean
  /** Accessible name of the button. */
  ariaLabel?: string
}
