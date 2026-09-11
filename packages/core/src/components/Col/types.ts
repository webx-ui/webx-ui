import type { Component } from 'vue'

/** A width at one breakpoint: the span alone, or the span with an offset. */
export type ColBreakpoint = number | { span?: number; offset?: number }

export interface ColProps {
  /** Columns out of 24 the cell takes. This is the base — it holds at every width. */
  span?: number
  /** Empty columns before the cell, out of 24. */
  offset?: number
  /** From 640px up. */
  sm?: ColBreakpoint
  /** From 768px up. */
  md?: ColBreakpoint
  /** From 1024px up. */
  lg?: ColBreakpoint
  /** From 1280px up. */
  xl?: ColBreakpoint
  /** Visual order within the row, without touching the source order. */
  order?: number
  /** The element to render. */
  as?: string | Component
}
