/** Where the panel was left: pixels, and the offset from the middle of the screen. */
export interface DialogLayout {
  width?: number
  height?: number
  x?: number
  y?: number
}

export interface DialogProps {
  /** Heading of the panel. The `title` slot replaces it. */
  title?: string
  /** Width of the panel: a number in pixels, or any CSS length — `520`, `'60%'`. */
  width?: number | string
  /** Height of the panel. Without it the panel grows with its content, up to the screen. */
  height?: number | string
  /** Smallest width a resize may reach, in pixels. */
  minWidth?: number
  /** Smallest height a resize may reach, in pixels. */
  minHeight?: number
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
  /** The panel can be dragged by its heading. */
  draggable?: boolean
  /** The panel can be resized by the grip in its bottom-right corner. */
  resizable?: boolean
  /** Remember size and position under this key, in `localStorage`. */
  persist?: string
  /** Accessible name for the panel when it has no visible heading. */
  ariaLabel?: string
}

export interface DialogEmits {
  open: []
  close: []
  /** The panel was dragged or resized. Carries what `persist` would store. */
  layout: [layout: DialogLayout]
}
