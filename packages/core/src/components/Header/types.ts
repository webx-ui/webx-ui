export interface HeaderProps {
  /** Height of the bar: pixels as a number, or any CSS length. */
  height?: number | string
  /** Rule along the bottom edge, separating the bar from the page. */
  bordered?: boolean
  /**
   * Keeps the bar in place while the page scrolls under it. How far from the top it
   * stops is `--wx-header-top`, so a shell that insets the bar can say so.
   */
  sticky?: boolean
  /**
   * Draws the bar as a card — its own border, radius and shadow — with the page
   * showing all around it. Pair it with `bordered` off: the rule along the bottom
   * edge is what the card replaces.
   */
  floating?: boolean
  /** Inner padding. `none` hands the bar over to whatever is inside it. */
  padding?: 'none' | 'sm' | 'md' | 'lg'
}
