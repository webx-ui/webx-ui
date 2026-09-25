import type { ScreenModel } from '@webx-ui/schema'

/** The date and the clock a moment is written with, whatever offset follows them. */
const WALL = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/

const pad = (value: number, width = 2) => String(value).padStart(width, '0')

/**
 * The same wall clock in the reader's own zone: `2026-10-12T00:00:00+08:00` read in New York
 * becomes `2026-10-12T00:00:00-04:00`.
 *
 * For an event of days the server keeps whole days in its own zone (§4.10) — midnight of the
 * first, `23:59:59` of the last — and the moment is not the point, the calendar date is. A date
 * picker reads a moment, though, and midnight at +08:00 is still the day before in New York, while
 * `23:59:59` in UTC is already the day after at +08:00: the same event would show a different
 * first or last day in every browser that is not in the site's zone. Moved onto the reader's
 * clock, the picker shows the day the server named, and a save sends it back with the reader's
 * offset — which the server cuts to a day in that same offset (`Moment::day()`), so it stays
 * the same day both ways.
 *
 * Anything that is not such a string comes back as it was.
 */
export function onReadersClock(value: unknown): unknown {
  if (typeof value !== 'string') return value

  const parts = WALL.exec(value)

  if (parts === null) return value

  const [year, month, day, hours, minutes, seconds] = parts.slice(1).map(Number) as [
    number,
    number,
    number,
    number,
    number,
    number,
  ]
  const local = new Date(year, month - 1, day, hours, minutes, seconds)
  // Minutes west of UTC on that day, not today: the offset changes with summer time.
  const west = local.getTimezoneOffset()
  const sign = west > 0 ? '-' : '+'
  const offset = `${sign}${pad(Math.floor(Math.abs(west) / 60))}:${pad(Math.abs(west) % 60)}`

  return (
    `${pad(local.getFullYear(), 4)}-${pad(local.getMonth() + 1)}-${pad(local.getDate())}` +
    `T${pad(local.getHours())}:${pad(local.getMinutes())}:${pad(local.getSeconds())}${offset}`
  )
}

/** The values of `events.form` with the days of an event of days put on the reader's clock. */
export function withDaysAsWritten(values: ScreenModel): ScreenModel {
  if (values.all_day !== true) return values

  return {
    ...values,
    starts_at: onReadersClock(values.starts_at),
    ends_at: onReadersClock(values.ends_at),
  } as ScreenModel
}
