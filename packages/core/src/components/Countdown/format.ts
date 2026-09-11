import type { CountdownTarget } from './types'

/** Largest unit first: each token takes its share of what the previous ones left. */
const units: [token: string, ms: number][] = [
  ['DD', 86_400_000],
  ['HH', 3_600_000],
  ['mm', 60_000],
  ['ss', 1_000],
  ['SSS', 1],
]

/**
 * Renders a span of milliseconds against a pattern of `DD`, `HH`, `mm`, `ss`, `SSS`.
 *
 * Only the tokens the pattern actually contains consume time, so `mm:ss` on two hours
 * prints `120:00` rather than silently dropping the hours. Everything else in the
 * pattern is copied through — mind that a literal word containing a token (`ss` in
 * "passed") would be replaced.
 */
export function formatCountdown(ms: number, format = 'HH:mm:ss'): string {
  let remaining = Math.max(0, Math.floor(ms))
  let out = format

  for (const [token, unit] of units) {
    if (!out.includes(token)) continue
    const value = Math.floor(remaining / unit)
    remaining -= value * unit
    out = out.replace(token, String(value).padStart(token.length, '0'))
  }

  return out
}

/** Normalises whatever the caller passed as a target into a timestamp. */
export function toTimestamp(value: CountdownTarget): number {
  if (typeof value === 'number') return value
  if (value instanceof Date) return value.getTime()
  const parsed = Date.parse(value)
  return Number.isNaN(parsed) ? 0 : parsed
}
