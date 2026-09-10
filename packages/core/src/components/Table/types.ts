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
  /**
   * Pins the column to an edge while the rest scrolls sideways. A pinned column needs
   * a `width`: the offsets of the ones behind it are worked out from the declared
   * widths, and a column of unknown width cannot say where the next one starts.
   */
  fixed?: 'left' | 'right'
  /** Turns the raw value into the text of the cell. */
  formatter?: (value: unknown, row: T, index: number) => string
  headerClass?: string
  cellClass?: string
  /** Leaves the column out without changing the array. */
  hidden?: boolean
}

/**
 * One line of the summary under the table. `label` occupies every column ahead of the
 * first one carrying a value, which is what a total wants: a caption on the left and a
 * figure under its column.
 */
export interface TableSummaryRow {
  /** Caption on the left. */
  label?: string
  /** Values by column key. */
  cells?: Record<string, unknown>
  /** Draws the line as the one that matters — the total rather than its parts. */
  strong?: boolean
  class?: string
}

/** Everything the table asks the backend for, in one object. */
export interface TableState {
  page: number
  perPage: number
  sort: TableSort | null
  search: string
}

export interface TableProps<T = TableRow> {
  /** Rows, or a whole paginator — the table reads `data` out of it. */
  data?: T[] | Paginated<T> | null
  columns: TableColumn<T>[]
  /**
   * Where a stable row id comes from. A missing key falls back to the row's position,
   * which is enough to render but too weak to survive a reorder — name one when rows
   * can be selected or expanded.
   */
  rowKey?: string | ((row: T, index: number) => RowKey)
  /** Heading above the table. */
  title?: string
  /** Adds the search field to the header. */
  searchable?: boolean
  searchPlaceholder?: string
  /** How long typing settles before `search` fires. Zero reports every keystroke. */
  searchDebounce?: number
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
  /** Adds the disclosure column and renders the `expanded` slot underneath a row. */
  expandable?: boolean
  /** Rows this returns `false` for have nothing to open. */
  expandableIf?: (row: T) => boolean
  /** Lines under the table: totals, discounts, whatever the figures are. */
  summary?: TableSummaryRow[]
  /**
   * Puts the pagination in the footer. On by default as soon as `data` is a paginator,
   * since a paginated response is a promise that there are more pages to reach.
   */
  pagination?: boolean
  /** Page sizes offered by the built-in pagination. Empty leaves the control out. */
  perPageOptions?: number[]
  /**
   * Remembers the page, the sort, the page size and the search term under this key, so
   * a reload lands where the user left off. One key per table per application.
   */
  persist?: string
  /**
   * Caps the height and scrolls the rows between a stuck header and a stuck footer.
   * A number is pixels; a string is any CSS length, so `60vh` follows the window.
   */
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
  'expand-change': [keys: RowKey[], rows: T[]]
  search: [term: string]
  /**
   * Everything the backend needs, together. Fires once on mount — with whatever
   * `persist` restored — and again whenever any part of it changes, which makes it the
   * single place to hang the fetch on.
   */
  'state-change': [state: TableState]
}
