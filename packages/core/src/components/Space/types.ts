import type { Component } from 'vue'

export type SpaceDirection = 'horizontal' | 'vertical'
export type SpaceSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
export type SpaceAlign = 'start' | 'center' | 'end' | 'baseline' | 'stretch'
export type SpaceJustify = 'start' | 'center' | 'end' | 'between' | 'around' | 'evenly'

export interface SpaceProps {
  /** Along the line or down the page. */
  direction?: SpaceDirection
  /** Gap between children: a keyword from the spacing scale, pixels, or any CSS length. */
  size?: SpaceSize | number | string
  /** Cross-axis alignment. */
  align?: SpaceAlign
  /** Main-axis distribution. */
  justify?: SpaceJustify
  /** Lets a horizontal row wrap onto the next line. */
  wrap?: boolean
  /** Children stretch to fill the row — equal-width buttons in a toolbar. */
  fill?: boolean
  /** Renders as `inline-flex`, so the group sits inside a line of text. */
  inline?: boolean
  /** The element to render. */
  as?: string | Component
}
