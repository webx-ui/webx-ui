import type { ControlSize } from '../../composables/useFormField'

export type TransferValue = string | number

export type TransferSide = 'left' | 'right'

export interface TransferItem {
  value: TransferValue
  /** What is read on the row. Falls back to the value. */
  label?: string
  /** A second line under the label. */
  description?: string
  /** Cannot be moved, from either side. */
  disabled?: boolean
}

export interface TransferProps {
  /** Everything there is to choose from, both panels together. */
  items?: TransferItem[]
  /** Headings of the two panels. */
  titles?: [string, string]
  /** A search field over each panel. */
  searchable?: boolean
  searchPlaceholder?: string
  /** Height of a list: a number in pixels, or any CSS length. */
  height?: number | string
  /** Shown in a panel with nothing in it. */
  emptyText?: string
  size?: ControlSize
  disabled?: boolean
  /** Accessible names for the two buttons. */
  toRightLabel?: string
  toLeftLabel?: string
}

export interface TransferChange {
  /** What moved, in the order it was shown. */
  values: TransferValue[]
  /** Which panel it moved to. */
  to: TransferSide
}

export interface TransferEmits {
  /** Something moved. The model has already been rewritten. */
  change: [change: TransferChange]
}
