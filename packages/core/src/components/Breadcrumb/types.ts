import type { IconName } from '../Icon/types'
import type { BreadcrumbSize } from '../../composables/useBreadcrumb'

export type { BreadcrumbSize }

export interface BreadcrumbProps {
  /** Character drawn between items. */
  separator?: string
  /** Icon drawn between items instead of the character — `chevron-right`, usually. */
  separatorIcon?: IconName
  /** Text size of the trail. */
  size?: BreadcrumbSize
  /** Accessible name of the navigation landmark. */
  label?: string
}
