import type { Component } from 'vue'
import type { IconName } from '../Icon/types'

/**
 * What the action does. It picks the icon, the colour and the accessible name, so a
 * row of actions stays consistent across screens — `icon`, `tone` and `label`
 * override any of those when a screen needs something else.
 */
export type ActionType =
  | 'add'
  | 'edit'
  | 'remove'
  | 'copy'
  | 'link'
  | 'goto'
  | 'details'
  | 'view'
  | 'hide'
  | 'upload'
  | 'download'
  | 'sort'
  | 'search'
  | 'restore'
  | 'settings'
  | 'send'
  | 'more'

export type ActionTone = 'primary' | 'danger' | 'success' | 'warning' | 'neutral'
export type ActionSize = 'sm' | 'md' | 'lg'

export interface ActionProps {
  type?: ActionType
  /** Icon to draw instead of the one the type picks. */
  icon?: IconName
  /** Colour to use instead of the one the type picks. */
  tone?: ActionTone
  /** Native tooltip; also the accessible name when `label` is not given. */
  title?: string
  /** Accessible name. Needed whenever `title` is absent and the type is not enough. */
  label?: string
  /** Renders an `<a>` instead of a `<button>`. */
  href?: string
  target?: string
  /** Renders through another component — `RouterLink`, say, with `to` in the attrs. */
  as?: string | Component
  disabled?: boolean
  /**
   * Keeps the space but draws nothing. A row where one record may not be deleted
   * stays lined up with the rows where it may.
   */
  hidden?: boolean
  /** Defaults to the size of the enclosing `WxActions`. */
  size?: ActionSize
}

export interface ActionEmits {
  click: [event: MouseEvent]
}
