import type { Component } from 'vue'

export type ContainerDirection = 'vertical' | 'horizontal'

export interface ContainerProps {
  /**
   * How the children stack. A page is `vertical` — header, body, footer; the body
   * itself is `horizontal` — sidebar beside the main column.
   */
  direction?: ContainerDirection
  /** Makes the container at least as tall as the viewport. For the outermost one. */
  fullHeight?: boolean
  /**
   * Makes the container exactly as tall as the viewport and stops it growing, so
   * that what scrolls is a pane inside it rather than the page. This is the shape an
   * admin shell has: a header that stays put and a main column that scrolls under
   * it. Pair it with `scroll` on the `WxMain` inside.
   *
   * `fullHeight` is the other answer — at least the viewport, with the page itself
   * scrolling. Use that for a document, this for an application.
   */
  viewport?: boolean
  /** The element to render. */
  as?: string | Component
}
