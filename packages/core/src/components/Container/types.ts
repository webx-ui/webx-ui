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
  /** The element to render. */
  as?: string | Component
}
