import type { ActionSize } from '../Action/types'

export type ActionsAlign = 'start' | 'center' | 'end'

export interface ActionsProps {
  /** Where the row sits when it has room to spare. */
  align?: ActionsAlign
  /** Size handed to every action that does not set its own. */
  size?: ActionSize
  /**
   * Collapses the row into a dropdown as soon as it no longer fits the container.
   * The container has to have a width of its own — a table cell, a card, a column —
   * otherwise there is nothing to measure against.
   *
   * `'always'` never draws the row at all: the actions are a menu whatever the width,
   * which is what a list wants when every row in the panel has to open the same way
   * whether it holds one action or seven.
   */
  collapse?: boolean | 'always'
  /** Accessible name of the group, e.g. "Row actions". */
  ariaLabel?: string
}

export interface ActionsEmits {
  /** The row collapsed into the dropdown, or came back out of it. */
  collapse: [collapsed: boolean]
}
