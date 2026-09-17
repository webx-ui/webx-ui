import type { IconName } from '../Icon/types'

/** Reka's tabs key their panels by string or number; ours do the same. */
export type TabValue = string | number

export type TabsVariant = 'line' | 'pill' | 'card'
export type TabsSize = 'sm' | 'md'
export type TabsOrientation = 'horizontal' | 'vertical'
export type TabsAlign = 'start' | 'center' | 'end' | 'stretch'
export type TabsActivationMode = 'automatic' | 'manual'

/** One tab of a strip that has no panels of its own. */
export interface TabItem {
  value: TabValue
  label?: string
  icon?: IconName
  badge?: string | number
  disabled?: boolean
}

export interface TabsProps {
  /**
   * Builds the strip from a list instead of from the `WxTab`s in the slot, and treats the
   * default slot as the one panel under it — the panel does not change with the tab, what
   * is inside it does.
   *
   * This is what a list screen switches views with: the table stays mounted, keeping its
   * search, its page and its scroll, and the tab only says which rows it is asking for.
   */
  items?: TabItem[]
  /**
   * Below this container width the strip folds into a single switch labelled with the view
   * that is open. Only in `items` mode; `0` — the default — never folds.
   *
   * A strip is worth scrolling while it is navigation somebody reads along. Five views of a
   * list on a phone are not that: they are one question with one answer showing.
   */
  collapseBelow?: number
  /** Underlined strip, a segmented control, or folder tabs. */
  variant?: TabsVariant
  size?: TabsSize
  /** A column of tabs beside the panel. It falls back to a strip in a narrow container. */
  orientation?: TabsOrientation
  /** Where the tabs sit in the strip; `stretch` splits the width between them. */
  align?: TabsAlign
  /** Whether arrow keys switch the panel as they move, or only move focus. */
  activationMode?: TabsActivationMode
  /** Keep hidden panels in the DOM, so form state and scroll position survive a switch. */
  keepAlive?: boolean
  /** Arrow keys wrap around from the last tab to the first. */
  loop?: boolean
  /** Accessible name for the tab strip. */
  ariaLabel?: string
}

export interface TabsEmits {
  change: [value: TabValue]
}

/** What `WxTabs` reads off each `WxTab` in its slot to build the strip. */
export interface TabDescriptor {
  value: TabValue
  label?: string
  icon?: IconName
  badge?: string | number
  disabled: boolean
  labelSlot?: () => unknown
}
