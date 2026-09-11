import type { IconName } from '../Icon/types'
import type { MenuValue } from '../../composables/useMenu'

export interface SubmenuProps {
  /** What identifies the branch — the key the menu opens and closes it by. */
  value: MenuValue
  /** Icon before the title. */
  icon?: IconName
  /** The title, when a slot would be overkill. */
  title?: string
  disabled?: boolean
}

export interface SubmenuEmits {
  /** The branch was opened or closed. */
  toggle: [open: boolean]
}
