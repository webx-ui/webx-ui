import type { IconName } from '../Icon/types'

/**
 * What happened. The three numbers are the HTTP replies an admin panel actually
 * shows a page for; the rest are outcomes of something the user just did.
 */
export type ResultStatus = 'success' | 'warning' | 'danger' | 'info' | '403' | '404' | '500'

export interface ResultProps {
  status?: ResultStatus
  /** The outcome in a few words: `Order placed`, `Page not found`. */
  title?: string
  /** What it means, or what to do next. */
  subtitle?: string
  /** Icon to draw instead of the one the status picks. */
  icon?: IconName
}
