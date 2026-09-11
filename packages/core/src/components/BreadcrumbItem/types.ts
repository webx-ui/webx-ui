import type { Component } from 'vue'
import type { IconName } from '../Icon/types'

export interface BreadcrumbItemProps {
  /** Where the crumb points. Without it the crumb is plain text. */
  href?: string
  target?: string
  /** Renders through another component — `RouterLink`, say, with `to` in the attrs. */
  as?: string | Component
  /** Icon before the label. */
  icon?: IconName
  /**
   * Marks the crumb as the page you are on. Left alone, a crumb that links nowhere
   * counts as the current page, which is how the last crumb is normally written.
   */
  current?: boolean
}

export interface BreadcrumbItemEmits {
  click: [event: MouseEvent]
}
