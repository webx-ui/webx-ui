import type { Component } from 'vue'
import type { IconName } from '../Icon/types'

export type DropdownItemTone = 'default' | 'primary' | 'danger' | 'success' | 'warning'

export interface DropdownItemProps {
  /** Icon before the label. */
  icon?: IconName
  /** Renders an `<a>` instead of a `<button>`. */
  href?: string
  target?: string
  /** Renders through another component — `RouterLink`, say, with `to` in the attrs. */
  as?: string | Component
  /** Colour role. `danger` for the destructive row at the bottom of a menu. */
  tone?: DropdownItemTone
  /** Marks the row as the current one. */
  active?: boolean
  disabled?: boolean
}

export interface DropdownItemEmits {
  click: [event: MouseEvent]
}
