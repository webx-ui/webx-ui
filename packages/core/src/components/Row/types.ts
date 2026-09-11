import type { Component } from 'vue'

export type RowJustify = 'start' | 'center' | 'end' | 'between' | 'around' | 'evenly'
export type RowAlign = 'start' | 'center' | 'end' | 'stretch' | 'baseline'

export interface RowProps {
  /** Gap between columns: pixels as a number, or any CSS length. */
  gutter?: number | string
  /** Gap between rows once the columns wrap. Defaults to `gutter`. */
  gutterY?: number | string
  /** Distribution along the row. */
  justify?: RowJustify
  /** Alignment across it. */
  align?: RowAlign
  /** Lets columns wrap onto the next line. */
  wrap?: boolean
  /** The element to render. */
  as?: string | Component
}
