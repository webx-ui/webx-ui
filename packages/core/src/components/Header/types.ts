export interface HeaderProps {
  /** Height of the bar: pixels as a number, or any CSS length. */
  height?: number | string
  /** Rule along the bottom edge, separating the bar from the page. */
  bordered?: boolean
  /** Keeps the bar in place while the page scrolls under it. */
  sticky?: boolean
  /** Inner padding. `none` hands the bar over to whatever is inside it. */
  padding?: 'none' | 'sm' | 'md' | 'lg'
}
