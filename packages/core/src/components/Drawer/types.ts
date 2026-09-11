export type DrawerSide = 'right' | 'left' | 'top' | 'bottom'

/** Where the panel was left: its size along the axis it slides on, in pixels. */
export interface DrawerLayout {
  size?: number
}

export interface DrawerProps {
  /** Heading of the panel. The `title` slot replaces it. */
  title?: string
  /** Edge the panel slides in from. */
  side?: DrawerSide
  /**
   * How far the panel reaches into the screen: its width on the left and right, its
   * height at the top and bottom. A number in pixels, or any CSS length — `380`, `'40%'`.
   */
  size?: number | string
  /** Smallest size a resize may reach, in pixels. */
  minSize?: number
  /** Width of the `sidebar` column: a number in pixels, or any CSS length. */
  sidebarWidth?: number | string
  /** Adds a × to the heading. */
  closable?: boolean
  /** Label of that ×, for screen readers. */
  closeLabel?: string
  /** A click outside the panel closes it. */
  closeOnOverlay?: boolean
  /** Escape closes it. */
  closeOnEscape?: boolean
  /** Dim the page behind the panel. */
  overlay?: boolean
  /** Block the page behind the panel, trap focus inside it and lock the page's scroll. */
  modal?: boolean
  /** The panel can be resized by the edge it faces the page with. */
  resizable?: boolean
  /** Remember the size under this key, in `localStorage`. */
  persist?: string
  /** Accessible name for the panel when it has no visible heading. */
  ariaLabel?: string
  /** Label of the resize handle, for screen readers. */
  resizeLabel?: string
}

export interface DrawerEmits {
  open: []
  close: []
  /** The panel was resized. Carries what `persist` would store. */
  layout: [layout: DrawerLayout]
}
