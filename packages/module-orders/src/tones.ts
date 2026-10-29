import type { BadgeType } from '@webx-ui/core'
import type { StatusColor } from './types'

/**
 * The palette of a status (spec §3) as the panel's tones: a name, never a colour, so the dark
 * theme gets its own. «Accent» is the brand's, which the badge calls primary.
 */
export const STATUS_TONES: Record<StatusColor, BadgeType> = {
  neutral: 'default',
  info: 'info',
  success: 'success',
  warning: 'warning',
  danger: 'danger',
  accent: 'primary',
}

export function toneOf(color: StatusColor | null | undefined): BadgeType {
  return color ? STATUS_TONES[color] : 'default'
}
