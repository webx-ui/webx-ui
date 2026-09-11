export type KanbanId = string | number

/** Colour of the line above a column's heading. */
export type KanbanTone = 'default' | 'primary' | 'success' | 'warning' | 'danger' | 'info'

export type KanbanSize = 'sm' | 'md'

/** How something was moved — worth knowing when you animate or undo. */
export type KanbanVia = 'pointer' | 'keyboard'

/** The least a card has to carry: something to key it by. */
export interface KanbanCard {
  id: KanbanId
  [key: string]: unknown
}

export interface KanbanColumn<T extends KanbanCard = KanbanCard> {
  id: KanbanId
  title?: string
  /** The cards, in the order they are shown. The board writes moves back into it. */
  items: T[]
  /**
   * How many cards the column is meant to hold. At the limit the count turns red and
   * nothing more can be dropped in — a work-in-progress limit, the point of a board.
   */
  limit?: number
  /** Colour of the rule above the heading. */
  tone?: KanbanTone
  /** Cards can be neither taken from this column nor put into it. */
  disabled?: boolean
}

/** Where a card came from and where it went. */
export interface KanbanMove<T extends KanbanCard = KanbanCard> {
  card: T
  from: { column: KanbanId; index: number }
  to: { column: KanbanId; index: number }
  via: KanbanVia
}

/** Where a column came from and where it went. */
export interface KanbanColumnMove<T extends KanbanCard = KanbanCard> {
  column: KanbanColumn<T>
  from: number
  to: number
  via: KanbanVia
}

export interface KanbanProps<T extends KanbanCard = KanbanCard> {
  /** The columns, each carrying its own cards. */
  columns: KanbanColumn<T>[]
  /** Boards that share a name exchange cards. Left alone, a board keeps to itself. */
  group?: string
  size?: KanbanSize
  /** Width of a column: a number is pixels, a string is any CSS length. */
  columnWidth?: number | string
  /** Nothing on the board can be moved. */
  disabled?: boolean
  /** CSS selector for the part of a card that starts a drag. */
  handle?: string
  /** Shows an add button under every column, which emits `add`. */
  addable?: boolean
  addLabel?: string
  /** Lets a column be folded down to a strip. Use `v-model:collapsed` to remember it. */
  collapsible?: boolean
  /** Columns can be dragged into another order, by their heading. */
  reorderColumns?: boolean
  /** Shows an add button after the last column, which emits `add-column`. */
  columnAddable?: boolean
  addColumnLabel?: string
  /** Shown in a column with no cards. */
  emptyText?: string
  /** Accessible name for the board. */
  ariaLabel?: string
}

export interface KanbanEmits<T extends KanbanCard = KanbanCard> {
  /** A card changed position or column. The arrays have already been updated. */
  move: [move: KanbanMove<T>]
  /** A column changed position. */
  'column-move': [move: KanbanColumnMove<T>]
  /** The add button under a column was pressed. */
  add: [column: KanbanColumn<T>]
  /** The add button after the last column was pressed. */
  'add-column': []
}
