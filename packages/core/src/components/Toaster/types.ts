/** Which corner — or edge — the toasts stack in. */
export type ToasterPlacement =
  'top-start' | 'top-center' | 'top-end' | 'bottom-start' | 'bottom-center' | 'bottom-end'

export interface ToasterProps {
  placement?: ToasterPlacement
  /** Width of a toast: a number in pixels, or any CSS length. */
  width?: number | string
  /** Accessible name of the region the toasts land in. */
  label?: string
  /**
   * How many are shown at once. The rest wait their turn rather than covering the
   * screen — a hundred failed requests is one problem, not a hundred notifications.
   */
  max?: number
  /** Key that dismisses everything on screen, e.g. `F8`. */
  hotkey?: string[]
}
