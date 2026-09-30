import { localizedValue } from '@webx-ui/core'
import type { PropertyRow, Words } from './types'

/**
 * A map of languages in one of them, the first written one where it is empty. The server sends an
 * empty map as `[]` — PHP's empty array — which reads as no words at all.
 */
export function wordsIn(words: Words | string | undefined, locale: string, fallback = ''): string {
  if (words === null || words === undefined || Array.isArray(words)) return fallback

  return localizedValue(words, locale, fallback)
}

/** What a property is called in the panel: its name, its code, its number — in that order. */
export function propertyName(property: Pick<PropertyRow, 'id' | 'title' | 'code'>, locale: string) {
  return wordsIn(property.title, locale) || wordsIn(property.code, locale) || `#${property.id}`
}

export interface NumberShape {
  precision?: number | null
  prefix?: string | null
  suffix?: string | null
}

/**
 * A number as the site shows it (§3.1): the property's precision, the language's decimal mark,
 * the prefix and the suffix glued on as written — `M8`, `⌀12 мм`, `1,35 кг`. The same rule as
 * `Property::formatNumber()` on the server, so the example in the form is what the card will say.
 */
export function formatNumber(value: number, shape: NumberShape, locale: string): string {
  const digits = Math.max(0, Math.min(6, Math.trunc(shape.precision ?? 0)))
  let number: string

  try {
    number = new Intl.NumberFormat(locale || undefined, {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits,
    }).format(value)
  } catch {
    number = value.toFixed(digits)
  }

  return `${shape.prefix ?? ''}${number}${shape.suffix ?? ''}`
}

/** The number a live example shows: twelve, with as many digits as the property keeps. */
export function exampleNumber(precision: number | null | undefined): number {
  const digits = Math.max(0, Math.min(6, Math.trunc(precision ?? 0)))

  return digits === 0 ? 12 : Number((12.5).toFixed(Math.min(digits, 1)))
}
