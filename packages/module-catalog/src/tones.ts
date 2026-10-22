import type { BadgeType } from '@webx-ui/core'

/**
 * The six tones a record of a reference book is painted in (labels, stock statuses) — the
 * server's `Dictionary::TONES`. A tone and not a colour: the site puts it into a class, the panel
 * into a tag of the same tone. A kind that allows it (labels) keeps a colour of its own instead,
 * `#rrggbb` in the same field — see `isOwnColor`.
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

/** A colour of a record's own, where its kind allows one (labels): `#rrggbb`, the server's `Dictionary::HEX`. */
const OWN = /^#[0-9a-f]{6}$/i

export function isOwnColor(value: unknown): value is string {
  return typeof value === 'string' && OWN.test(value)
}

/**
 * The text that reads on a background, by the contrast of WCAG 2: black or white, whichever is
 * further from it — the server's `Dictionary::contrastText`, so the preview and the site agree.
 */
export function contrastText(background: string): '#000000' | '#ffffff' {
  const channel = (at: number): number => {
    const value = parseInt(background.slice(at, at + 2), 16) / 255

    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
  }

  const luminance = 0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5)

  return 1.05 / (luminance + 0.05) >= (luminance + 0.05) / 0.05 ? '#ffffff' : '#000000'
}

/**
 * A solid tag in a colour of its own: the badge's own variables, set on the element, over the
 * ones its default type gave it. The text is the chosen one, or black or white by contrast.
 */
export function ownBadgeStyle(background: string, text?: string | null): Record<string, string> {
  const ink = isOwnColor(text) ? text : contrastText(background)

  return {
    '--wx-badge-solid': background,
    '--wx-badge-contrast': ink,
    '--wx-badge-border': background,
  }
}
