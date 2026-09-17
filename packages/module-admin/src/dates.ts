import { inject } from 'vue'
import { createI18n, i18nKey, type I18n } from './i18n'
import { adminMessages } from './messages'

/** A moment as it arrives: ISO-8601 from the server, a timestamp, a `Date`, or nothing. */
export type DateLike = string | number | Date | null | undefined

export interface Dates {
  /**
   * The line a reader sees: "today at 08:10", "yesterday at 14:03", "16 September at 14:03",
   * "16 September 2025", "never".
   */
  short(value: DateLike): string
  /**
   * Everything there is, down to the second — what the tip holds when the short line is not
   * precise enough to settle an argument. Empty when there is no date.
   */
  exact(value: DateLike): string
  /** The machine value for `<time datetime>`. Empty when there is no date. */
  iso(value: DateLike): string
}

/**
 * How the panel says when something happened — one answer for every screen in it.
 *
 * Three rules it would be easy to lose, and each of them was a bug before this existed:
 *
 * - the language is the panel's, not the browser's. `toLocaleString()` with no locale reads
 *   the browser's settings, so a panel drawn in Russian was dating its rows in American
 *   order. The locale comes from `i18n`, which is what the administrator chose.
 * - "today" is counted in the administrator's own day. The server sends UTC; comparing UTC
 *   dates would write "yesterday" over anything that happened before 03:00 in Moscow. The
 *   comparison is made on local calendar days, which is what the reader means by today.
 * - the shown string is never what sorts. Nothing here touches ordering: a column sorts on
 *   the value the server sent, or "yesterday" lands between the sixteenth and the seventeenth.
 */
export function createDates(i18n: I18n): Dates {
  const t = i18n.scope('webx-admin')

  // A table asks for the same two or three shapes once per row, and building an
  // `Intl.DateTimeFormat` is the expensive part of formatting. Keyed by locale as well as by
  // shape, so switching the panel's language does not hand back the old language's months.
  const formatters = new Map<string, Intl.DateTimeFormat>()

  function format(shape: string, options: Intl.DateTimeFormatOptions, at: Date): string {
    const locale = i18n.state.locale
    const key = `${locale}:${shape}`
    let formatter = formatters.get(key)

    if (formatter === undefined) {
      formatter = new Intl.DateTimeFormat(locale, options)
      formatters.set(key, formatter)
    }

    return formatter.format(at)
  }

  /* 24 hours everywhere, whatever the locale would have chosen: a panel is read in a hurry and
   * "08:10" is one glance where "8:10 AM" is two. Seconds are left to `exact()`. */
  const time = (at: Date) =>
    format('time', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }, at)

  return {
    short(value) {
      const at = toDate(value)

      if (at === null) {
        return t('dates.never')
      }

      const now = new Date()
      const days = daysApart(at, now)

      if (days === 0) return t('dates.today', { time: time(at) })
      if (days === -1) return t('dates.yesterday', { time: time(at) })

      // Within this year the year itself says nothing — it is the time of day that tells two
      // entries apart. Further back it is the other way round, and a time nobody will compare
      // is noise in the column.
      return at.getFullYear() === now.getFullYear()
        ? format(
            'this-year',
            {
              day: 'numeric',
              month: 'long',
              hour: '2-digit',
              minute: '2-digit',
              hourCycle: 'h23',
            },
            at,
          )
        : format('earlier', { day: 'numeric', month: 'long', year: 'numeric' }, at)
    },
    exact(value) {
      const at = toDate(value)

      return at === null
        ? ''
        : format('exact', { dateStyle: 'long', timeStyle: 'medium', hourCycle: 'h23' }, at)
    },
    iso(value) {
      const at = toDate(value)

      return at === null ? '' : at.toISOString()
    },
  }
}

/**
 * The formatter, inside a panel or outside one.
 *
 * Outside a panel — a screen mounted by hand, a story, a test — there is no dictionary to read,
 * so one is made once with this package's own English in it. A component that may be used
 * either way should not have to know which it is.
 */
export function useDates(): Dates {
  const i18n = inject(i18nKey, null)

  return createDates(i18n ?? standalone())
}

let loose: I18n | null = null

function standalone(): I18n {
  if (loose === null) {
    loose = createI18n()
    loose.defaults('webx-admin', adminMessages)
  }

  return loose
}

function toDate(value: DateLike): Date | null {
  if (value === null || value === undefined || value === '') {
    return null
  }

  const at = value instanceof Date ? value : new Date(value)

  // An unparseable string is a broken date, and `Invalid Date` on a screen says less than the
  // word the empty column already has.
  return Number.isNaN(at.getTime()) ? null : at
}

/**
 * Whole calendar days between two moments, in the reader's own zone.
 *
 * Built from the date parts rather than from a difference in milliseconds: an hour lost to
 * daylight saving makes "24 hours ago" and "yesterday" different questions, and it is the
 * second one a reader is asking.
 */
function daysApart(at: Date, now: Date): number {
  const day = (d: Date) => Date.UTC(d.getFullYear(), d.getMonth(), d.getDate())

  return Math.round((day(at) - day(now)) / 86_400_000)
}
