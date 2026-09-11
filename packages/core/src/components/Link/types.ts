import type { Component } from 'vue'
import type { TextSize, TextWeight } from '../Text/types'

export type LinkType = 'default' | 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'muted'
export type LinkUnderline = 'hover' | 'always' | 'never'

export interface LinkProps {
  href?: string
  target?: string
  rel?: string
  /** Colour role. `default` is the link colour from the tokens. */
  type?: LinkType
  /** When the underline shows. */
  underline?: LinkUnderline
  size?: TextSize
  weight?: TextWeight
  disabled?: boolean
  /**
   * Marks the link as leaving the app: opens in a new tab with a safe `rel`, and
   * appends a small arrow icon.
   */
  external?: boolean
  /** Renders through another component — `RouterLink`, say, with `to` in the attrs. */
  as?: string | Component
}

export interface LinkEmits {
  click: [event: MouseEvent]
}
