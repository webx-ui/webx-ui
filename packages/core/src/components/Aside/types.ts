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
  /** Scrolls the column on its own, independently of the page. */
  scroll?: boolean
}
