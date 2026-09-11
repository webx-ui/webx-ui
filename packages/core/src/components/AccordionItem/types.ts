import type { IconName } from '../Icon/types'

export interface AccordionItemProps {
  /** Unique within the accordion; this is what `v-model` holds. */
  value: string
  /** Text of the header. The `title` slot replaces it. */
  title?: string
  /** A second line under the title — a hint, a count, a status. */
  subtitle?: string
  /** Icon before the title, after the chevron when that sits first. */
  icon?: IconName
  disabled?: boolean
}
