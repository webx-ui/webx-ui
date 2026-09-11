export interface ListDetailProps {
  /**
   * Width of the column that narrows the list: saved views, filters, folders.
   * The column is only rendered when the `filters` slot is used.
   */
  filtersWidth?: number | string
  /** Width of the list of records. */
  listWidth?: number | string
  /**
   * The narrowest the detail pane may be. It is what the two thresholds are made
   * of: the filters column folds away when it would push the pane under this, and
   * the pane stops standing beside the list when it no longer fits either.
   */
  detailMin?: number
  /** Heading of the panel the filters move into once the column does not fit. */
  filtersTitle?: string
  /** Accessible name of the panel the detail opens in on a narrow screen. */
  detailLabel?: string
}
