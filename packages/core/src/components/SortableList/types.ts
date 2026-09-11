export type SortableListSize = 'sm' | 'md'

/** How something was moved — worth knowing when you animate or undo. */
export type SortableVia = 'pointer' | 'keyboard'

/** Where a row came from and where it went. */
export interface SortableMove<T = unknown> {
  item: T
  from: number
  to: number
  via: SortableVia
}

export interface SortableListProps<T = unknown> {
  /** Heading above the list. */
  title?: string
  /**
   * What a drag starts from: the grip at the start of every row (`grip`), anywhere on
   * the row (`row`), or a CSS selector for a handle of your own.
   */
  handle?: 'grip' | 'row' | string
  /** Field to key a row by, or a function. Falls back to the position. */
  itemKey?: string | ((item: T, index: number) => string | number)
  /** Lists that share a name pass rows between them. */
  group?: string
  /** Nothing can be moved. */
  disabled?: boolean
  size?: SortableListSize
  /** Drops the frame, for a list that already sits in a card. */
  plain?: boolean
  /** Shown when there is nothing in the list. */
  emptyText?: string
  /** What the grip is called, before the name of the row it holds. */
  dragLabel?: string
  /** Accessible name for the list. */
  ariaLabel?: string
}

export interface SortableListEmits<T = unknown> {
  /** A row changed position. The list has already been reordered. */
  move: [move: SortableMove<T>]
}
