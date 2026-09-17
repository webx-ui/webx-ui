export interface AsideProps {
  /** Width of the column: pixels as a number, or any CSS length. */
  width?: number | string
  /** Width once `collapsed` is on — the icon rail of a sidebar. */
  collapsedWidth?: number | string
  /** Narrows the column to `collapsedWidth`. Pair it with a collapsed `WxMenu`. */
  collapsed?: boolean
  /** Which edge carries the rule: the sidebar's own side of the page. */
  side?: 'start' | 'end'
  /** Rule between the column and the page. */
  bordered?: boolean
  /**
   * Scrolls the column on its own, independently of the page. With the `top` or
   * `bottom` slots in use it is the middle zone that scrolls, so that the brand and
   * the account stay where they are put.
   */
  scroll?: boolean
  /**
   * Keeps the column in place while the page scrolls past it, at its own height
   * rather than the page's. This is the shape a sidebar has next to a document that
   * scrolls natively; `scroll` is the other one, where the column carries the
   * scrollbar itself.
   *
   * The offset from the top and the height are `--wx-aside-top` and
   * `--wx-aside-height`, so a shell that insets the column can say so.
   */
  sticky?: boolean
  /**
   * Draws the column as a card — its own border, radius and shadow — instead of as
   * a wall between the menu and the page. Pair it with `bordered` off: the rule on
   * one side is what the card replaces.
   */
  floating?: boolean
}
