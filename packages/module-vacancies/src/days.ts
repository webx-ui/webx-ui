/**
 * A vacancy's dates are calendar days (decision 13): `valid_through` is the last day it is open,
 * `posted_at` the day it was first put up, both `YYYY-MM-DD` with no time and no zone.
 *
 * Nothing here may turn such a day into a moment. `new Date('2026-11-30')` is midnight *UTC*, and
 * west of Greenwich that is still the 29th — the column would print the day before the one the
 * editor picked, and the site (which reads the day as written) would disagree with the panel. So
 * the day is built from its parts, at local midnight, and formatted from there: the same three
 * numbers in every zone.
 *
 * The pickers of `vacancies.form` do the same by themselves: `valueFormat: "yyyy-MM-dd"` parses
 * and formats in the browser's own zone, so a day goes in and comes out unchanged.
 */

const DAY = /^(\d{4})-(\d{2})-(\d{2})$/

/** The day as a local date, or `null` for anything that is not a `YYYY-MM-DD`. */
export function dayOf(value: string | null | undefined): Date | null {
  const parts = typeof value === 'string' ? DAY.exec(value.slice(0, 10)) : null

  if (parts === null) return null

  const [, year, month, day] = parts
  const at = new Date(Number(year), Number(month) - 1, Number(day))

  return Number.isNaN(at.getTime()) ? null : at
}

/**
 * The day in the panel's language — "30 Nov", and with the year when it is not this one. Empty
 * for no day at all.
 */
export function formatDay(
  value: string | null | undefined,
  locale: string,
  now = new Date(),
): string {
  const at = dayOf(value)

  if (at === null) return ''

  return new Intl.DateTimeFormat(locale, {
    day: 'numeric',
    month: 'short',
    ...(at.getFullYear() === now.getFullYear() ? {} : { year: 'numeric' }),
  }).format(at)
}

/** The same day, in full — for a tip and for the head of the editor. */
export function longDay(value: string | null | undefined, locale: string): string {
  const at = dayOf(value)

  return at === null
    ? ''
    : new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long', year: 'numeric' }).format(at)
}
