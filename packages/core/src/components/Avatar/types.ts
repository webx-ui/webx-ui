import type { IconName } from '../Icon/types'

export type AvatarSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
export type AvatarShape = 'circle' | 'square'

/**
 * Colour of the fallback. `auto` picks a tint from the name, so the same person is
 * the same colour on every screen and a list of them is readable at a glance.
 */
export type AvatarTone = 'auto' | 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info'

export interface AvatarProps {
  /** Picture to show. Anything that fails to load falls through to the initials. */
  src?: string
  /** Alternative text for the picture. Defaults to `name`. */
  alt?: string
  /**
   * Who this is. It supplies the initials, the tooltip-less title, and — with
   * `tone="auto"` — the colour.
   */
  name?: string
  /** Shown instead of initials when there is no name. */
  icon?: IconName
  size?: AvatarSize
  shape?: AvatarShape
  tone?: AvatarTone
  /** How the picture fills its box. */
  fit?: 'cover' | 'contain' | 'fill' | 'none' | 'scale-down'
}

export interface AvatarEmits {
  /** The picture could not be loaded, so the fallback is showing. */
  error: [event: Event]
}
