import type { IconName } from '../Icon/types'

export type AlertType = 'info' | 'success' | 'warning' | 'danger'
export type AlertVariant = 'soft' | 'solid' | 'outline'

export interface AlertProps {
  /** Semantic role of the message. */
  type?: AlertType
  /** Visual weight. `soft` is the tinted panel an admin page usually wants. */
  variant?: AlertVariant
  /** Headline above the body. Without it the body carries the message alone. */
  title?: string
  /** The message, when a slot would be overkill. */
  description?: string
  /**
   * The leading icon. A name overrides the one implied by `type`; `false` drops it.
   */
  icon?: IconName | false
  /** Adds a × that closes the alert. */
  closable?: boolean
  /** Accessible name of the close button. */
  closeLabel?: string
  /**
   * Announces the alert when it appears. Leave it off for a message that is part of
   * the page from the start — a screen reader would read it out of nowhere.
   */
  live?: boolean
}

export interface AlertEmits {
  close: [event: MouseEvent]
}
