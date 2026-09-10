import type { ControlSize } from '../../composables/useFormField'

/**
 * A page as Laravel's `->paginate()` serialises it. Taken as it arrives, with the
 * snake_case keys intact — renaming them in every controller is work the table can
 * spare the caller.
 */
export interface Paginated<T = TableRow> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export type TableRow = Record<string, unknown>

export type SortOrder = 'asc' | 'desc'

export interface TableSort {
  /** Column key. Sent to the backend as it is. */
  key: string
  order: SortOrder
}

export type RowKey = string | number

export type TableAlign = 'left' | 'center' | 'right'

export interface TableColumn<T = TableRow> {
  /**
   * Identifies the column and reads the value out of the row. A dotted path walks
   * into nested objects, so an eager-loaded relation works: `user.name`.
   */
  key: string
  /** Heading text. Use the `header-<key>` slot for anything richer. */
  label?: string
  width?: string | number
  minWidth?: string | number
  align?: TableAlign
  /** Adds a sort control to the heading. The table reports, it does not reorder. */
  sortable?: boolean
  /** Turns the raw value into the text of the cell. */
  formatter?: (value: unknown, row: T, index: number) => string
  headerClass?: string
  cellClass?: string
  /** Leaves the column out without changing the array. */
  hidden?: boolean
}

export interface TableProps<T = TableRow> {
  /** Rows, or a whole paginator — the table reads `data` out of it. */
  data?: T[] | Paginated<T> | null
  columns: TableColumn<T>[]
  /**
   * Where a stable row id comes from. A missing key falls back to the row's position,
   * which is enough to render but too weak to survive a reorder — name one when rows
   * can be selected.
   */
  rowKey?: string | ((row: T, index: number) => RowKey)
  loading?: boolean
  /** Shown in place of the rows when there are none. */
  emptyText?: string
  stripe?: boolean
  bordered?: boolean
  /** Highlight the row under the pointer. */
  hover?: boolean
  size?: ControlSize
  /** Adds the checkbox column. */
  selectable?: boolean
  /** Rows this returns `false` for cannot be selected. */
  selectableIf?: (row: T) => boolean
  /** Turns the header sticky and scrolls the body under it. */
  maxHeight?: string | number
  /** Extra class per row, for status colouring and the like. */
  rowClass?: (row: T, index: number) => string | undefined
  /** Fixes the column widths instead of letting the content decide. */
  layout?: 'auto' | 'fixed'
  ariaLabel?: string
}

export interface TableEmits<T = TableRow> {
  'row-click': [row: T, index: number, event: MouseEvent]
  'sort-change': [sort: TableSort | null]
  'selection-change': [keys: RowKey[], rows: T[]]
}
