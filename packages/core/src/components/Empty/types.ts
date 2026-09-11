import type { IconName } from '../Icon/types'

export type EmptySize = 'sm' | 'md' | 'lg'

export interface EmptyProps {
  /**
   * The glyph above the message. Pick one that says what is missing — `search` for
   * a filter that matched nothing, `inbox` for a list nobody has written to yet.
   */
  icon?: IconName
  /** What is missing, in a few words. */
  title?: string
  /** Why, or what to do about it. */
  description?: string
  /**
   * How much room to take. `sm` is a panel inside a screen; `lg` is a whole page
   * with nothing on it.
   */
  size?: EmptySize
  /** Drops the icon, for a message that stands on its own. */
  plain?: boolean
}
