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
   */
  collapse?: boolean
  /** Accessible name of the group, e.g. "Row actions". */
  ariaLabel?: string
}

export interface ActionsEmits {
  /** The row collapsed into the dropdown, or came back out of it. */
  collapse: [collapsed: boolean]
}
