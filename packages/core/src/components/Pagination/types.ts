import type { ControlSize } from '../../composables/useFormField'
import type { Paginated } from '../Table/types'

export interface PaginationProps {
  /**
   * A page as `->paginate()` serialises it. The current page, the page size and the
   * totals are all read from it, so the usual case needs no other prop.
   */
  paginator?: Paginated<unknown> | null
  /** Rows in total. Only needed without a paginator. */
  total?: number
  /** Last page number. Worked out from `total` and the page size when missing. */
  lastPage?: number
  /** Page buttons kept either side of the current one. */
  siblings?: number
  /** Offers a page-size control. Empty leaves it out. */
  perPageOptions?: number[]
  /** Shows the "1–15 of 128" line. */
  showTotal?: boolean
  disabled?: boolean
  size?: ControlSize
  ariaLabel?: string
}

export interface PaginationChange {
  page: number
  perPage: number
}

export interface PaginationEmits {
  change: [value: PaginationChange]
}
