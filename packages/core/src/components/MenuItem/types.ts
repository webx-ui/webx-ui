import type { Component } from 'vue'
import type { IconName } from '../Icon/types'
import type { MenuValue } from '../../composables/useMenu'

export interface MenuItemProps {
  /** What identifies the entry — the value the menu reports as selected. */
  value?: MenuValue
  /** Icon before the label. The only thing left of the entry on a collapsed rail. */
  icon?: IconName
  /**
   * The label, when a slot would be overkill. It is also what a collapsed rail shows
   * as the entry's title on hover.
   */
  label?: string
  /** Where the entry points. Without it the entry is a button. */
  href?: string
  target?: string
  /** Renders through another component — `RouterLink`, say, with `to` in the attrs. */
  as?: string | Component
  disabled?: boolean
}

export interface MenuItemEmits {
  click: [event: MouseEvent]
}
