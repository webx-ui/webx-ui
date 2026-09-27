import { afterEach, describe, expect, it } from 'vitest'
import { onReadersClock, withDaysAsWritten } from './days'

/*
 * The zone is the point of these tests, so each one sets it: Node reads `TZ` again the moment it
 * changes. A test run in the site's own zone would pass on the bug (CLAUDE.md §4 — in UTC
 * everything adds up).
 */
const before = process.env.TZ

function inZone(zone: string): void {
  process.env.TZ = zone
}

/** The calendar day a date picker would show for this value in the current zone. */
function shownDay(value: unknown): string {
  const at = new Date(String(value))

  return `${at.getFullYear()}-${String(at.getMonth() + 1).padStart(2, '0')}-${String(at.getDate()).padStart(2, '0')}`
}

describe('an event of days shows the same days in every zone', () => {
  afterEach(() => {
    process.env.TZ = before
  })

  /* What the server answers for 12–14 October with the site in Hong Kong (§4.10). */
  const server = {
    all_day: true,
    starts_at: '2026-10-12T00:00:00+08:00',
    ends_at: '2026-10-14T23:59:59+08:00',
  }

  it('west of the site: the first day is not the day before', () => {
    inZone('America/New_York')

    // The bug, as it was: the moment is still 11 October in New York.
    expect(shownDay(server.starts_at)).toBe('2026-10-11')

    const values = withDaysAsWritten(server)

    expect(values.starts_at).toBe('2026-10-12T00:00:00-04:00')
    expect(values.ends_at).toBe('2026-10-14T23:59:59-04:00')
    expect(shownDay(values.starts_at)).toBe('2026-10-12')
    expect(shownDay(values.ends_at)).toBe('2026-10-14')
  })

  it('east of the site: the last day is not the day after', () => {
    inZone('Pacific/Auckland')

    const utc = {
      all_day: true,
      starts_at: '2026-10-12T00:00:00+00:00',
      ends_at: '2026-10-14T23:59:59+00:00',
    }

    expect(shownDay(utc.ends_at)).toBe('2026-10-15')

    const values = withDaysAsWritten(utc)

    // Summer time has begun in New Zealand by October: +13:00, not +12:00.
    expect(values.starts_at).toBe('2026-10-12T00:00:00+13:00')
    expect(shownDay(values.starts_at)).toBe('2026-10-12')
    expect(shownDay(values.ends_at)).toBe('2026-10-14')
  })

  it('leaves an event with hours, and the empty dates, alone', () => {
    inZone('America/New_York')

    const timed = { all_day: false, starts_at: '2026-10-12T10:00:00+08:00', ends_at: null }

    expect(withDaysAsWritten(timed)).toBe(timed)
    expect(withDaysAsWritten({ all_day: true, starts_at: null, ends_at: null })).toEqual({
      all_day: true,
      starts_at: null,
      ends_at: null,
    })
    expect(onReadersClock('soon')).toBe('soon')
  })
})
