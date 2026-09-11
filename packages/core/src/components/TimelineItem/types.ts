import type { IconName } from '../Icon/types'

export type TimelineItemType = 'default' | 'primary' | 'success' | 'warning' | 'danger' | 'info'
export type TimestampPlacement = 'top' | 'bottom'

export interface TimelineItemProps {
  /** When it happened — any pre-formatted string. */
  timestamp?: string
  /** Machine-readable value for the `<time datetime>`, e.g. `2026-03-12T09:30:00Z`. */
  datetime?: string
  /** Above the entry (the usual log order) or under it. */
  timestampPlacement?: TimestampPlacement
  /** Headline of the entry. */
  title?: string
  /** Colour of the dot. */
  type?: TimelineItemType
  /** Draws the dot as a ring — a step that has not happened yet. */
  hollow?: boolean
  /** Puts an icon inside the dot instead of filling it. */
  icon?: IconName
  /** Hides the line under this entry — for the last one when the list continues elsewhere. */
  hideLine?: boolean
}
