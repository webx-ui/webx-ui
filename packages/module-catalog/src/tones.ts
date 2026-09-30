import type { BadgeType } from '@webx-ui/core'

/**
 * The six tones a record of a reference book is painted in (labels, stock statuses) — the
 * server's `Dictionary::TONES`. A tone and not a colour: the site puts it into a class, the panel
 * into a tag of the same tone, and nobody writes a hex anywhere.
 */
export const TONES = ['neutral', 'primary', 'success', 'warning', 'danger', 'info'] as const

export type Tone = (typeof TONES)[number]

/** The tag the panel draws a tone as. `neutral` is the core's `default`; the rest match by name. */
export function toneBadge(tone: unknown): BadgeType {
  return typeof tone === 'string' &&
    tone !== 'neutral' &&
    (TONES as readonly string[]).includes(tone)
    ? (tone as BadgeType)
    : 'default'
}
