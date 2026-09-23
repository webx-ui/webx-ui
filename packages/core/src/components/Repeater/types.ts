import type { SortableMove } from '../SortableList/types'

export type RepeaterSize = 'sm' | 'md'

/** One row of the model, plus the ways of writing it back. */
export interface RepeaterItemSlot<T extends object> {
  item: T
  index: number
  /** Replaces the item with a copy carrying these keys — the model stays immutable. */
  update: (patch: Partial<T>) => void
}

export interface RepeaterProps<T extends object = Record<string, unknown>> {
  /** Heading above the rows. */
  title?: string
  /**
   * What a row is called in its header and to a screen reader: a key of the item, or a
   * function. A key is shown after the position (`#2 · …`), in the language being edited when
   * it holds a translated field; a function answers for the whole header. Falls back to the
   * position.
   */
  itemLabel?: string | ((item: T, index: number) => string)
  /** Builds what `Add` appends. Defaults to an empty object. */
  newItem?: () => T
  addLabel?: string
  removeLabel?: string
  /** What the grip is called to a screen reader. */
  dragLabel?: string
  /** Rows fold to their header, so a long form stays readable. */
  collapsible?: boolean
  /** Rows that were already there start folded. Implies `collapsible`. */
  collapsed?: boolean
  /** Fewer rows than this cannot be removed. */
  min?: number
  /** More rows than this cannot be added. */
  max?: number
  /** Rows can be reordered. */
  sortable?: boolean
  disabled?: boolean
  /** Shown when there is nothing yet. */
  emptyText?: string
  size?: RepeaterSize
  /** Drops the frame, for a repeater that already sits in a card. */
  plain?: boolean
  /** Accessible name for the list of rows. */
  ariaLabel?: string
}

export interface RepeaterEmits<T extends object = Record<string, unknown>> {
  /** A row was appended. The model already holds it. */
  add: [item: T, index: number]
  /** A row was dropped. The model no longer holds it. */
  remove: [item: T, index: number]
  /** A row changed position. */
  move: [move: SortableMove<T>]
}
