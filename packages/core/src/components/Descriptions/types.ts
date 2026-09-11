import type { ControlSize } from '../../composables/useFormField'

/** Where the label sits: beside its value, or above it. */
export type DescriptionsLayout = 'horizontal' | 'vertical'

export interface DescriptionsProps {
  /** Heading above the list. */
  title?: string
  /**
   * How many label-and-value pairs stand side by side. The list drops to one column
   * on its own when the room runs out, so this is the most it will ever show.
   */
  columns?: number
  /** Rules around every cell, which is what turns a list into a table of facts. */
  bordered?: boolean
  size?: ControlSize
  layout?: DescriptionsLayout
  /** Width of the label column when the layout is `horizontal`, e.g. `"140px"`. */
  labelWidth?: string
}

export interface DescriptionsItemProps {
  /** The name of the fact. Use the `label` slot for anything richer than text. */
  label?: string
  /** How many columns the pair takes up. */
  span?: number
}

/** What `WxDescriptions` hands down to the pairs inside it. */
export interface DescriptionsContext {
  bordered: boolean
  layout: DescriptionsLayout
  labelWidth: string | undefined
}
