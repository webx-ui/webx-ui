export type ScrollbarAxis = 'y' | 'x' | 'both'
export type ScrollbarSize = 'sm' | 'md'

export interface ScrollbarProps {
  /** Fixed height: pixels as a number, or any CSS length. */
  height?: number | string
  /** Height the box grows to before it starts scrolling. */
  maxHeight?: number | string
  /** Which way the content scrolls. */
  axis?: ScrollbarAxis
  /** Thickness of the bar. */
  size?: ScrollbarSize
  /** Keeps the bar visible instead of fading it in on hover. */
  always?: boolean
  /**
   * Lets a scroll that reaches the end carry on to the page behind it. Off by
   * default: a panel inside a dialog should not scroll the page under it.
   */
  chainScroll?: boolean
}

export interface ScrollbarEmits {
  scroll: [event: Event]
}

/** How a programmatic scroll moves — the native `ScrollBehavior` values. */
export type ScrollMotion = 'auto' | 'smooth' | 'instant'

/** Where to scroll to, in the shape the native `scrollTo` takes. */
export interface ScrollTarget {
  top?: number
  left?: number
  behavior?: ScrollMotion
}
