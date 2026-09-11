import type { IconName } from '../Icon/types'

/** Reka's tabs key their panels by string or number; ours do the same. */
export type TabValue = string | number

export type TabsVariant = 'line' | 'pill' | 'card'
export type TabsSize = 'sm' | 'md'
export type TabsOrientation = 'horizontal' | 'vertical'
export type TabsAlign = 'start' | 'center' | 'end' | 'stretch'
export type TabsActivationMode = 'automatic' | 'manual'

export interface TabsProps {
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
