import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createDates } from './dates'
import { createI18n, type I18n } from './i18n'
import { adminMessages } from './messages'

/**
 * A panel, in one language.
 *
 * Built by hand rather than through `createAdmin()` so that a test can say what the dictionary
 * holds — the point of most of these is that the line comes from the panel's language and not
 * from the machine running the test.
 */
function panel(locale: string, lines?: Record<string, string>): I18n {
  const i18n = createI18n({ locale })

  i18n.defaults('webx-admin', adminMessages)

  if (lines !== undefined) {
    i18n.load({ 'webx-admin': { dates: lines } }, locale)
  }

  return i18n
}

const russian = {
  today: 'сегодня в :time',
  yesterday: 'вчера в :time',
  never: 'ни разу',
}

beforeEach(() => {
  // Local time, not UTC: everything here is about the day the reader is having.
  vi.useFakeTimers({ now: new Date(2026, 8, 17, 9, 30) })
})

afterEach(() => {
  vi.useRealTimers()
})

describe('the short line', () => {
  it('says today with the time', () => {
    expect(createDates(panel('en')).short(new Date(2026, 8, 17, 8, 10))).toBe('today at 08:10')
  })

  it('says yesterday with the time', () => {
    expect(createDates(panel('en')).short(new Date(2026, 8, 16, 14, 3))).toBe('yesterday at 14:03')
  })

  it('keeps the time of day for this year, and drops it for earlier years', () => {
    const dates = createDates(panel('en'))

    expect(dates.short(new Date(2026, 8, 10, 14, 3))).toBe('September 10 at 14:03')
    expect(dates.short(new Date(2025, 8, 16, 14, 3))).toBe('September 16, 2025')
  })

  it('answers a date that never happened in words', () => {
    const dates = createDates(panel('en'))

    expect(dates.short(null)).toBe('never')
    expect(dates.short(undefined)).toBe('never')
    // Not `Invalid Date` on somebody's screen.
    expect(dates.short('not a date')).toBe('never')
  })

  it('counts 24 hours, whatever the language would have chosen', () => {
    // An American panel would otherwise be told "8:10 PM"; the column is read at a glance.
    expect(createDates(panel('en-US')).short(new Date(2026, 8, 17, 20, 10))).toBe('today at 20:10')
  })
})

describe('the language', () => {
  it('is the panel’s and not the browser’s', () => {
    const dates = createDates(panel('ru', russian))

    expect(dates.short(new Date(2026, 8, 17, 8, 10))).toBe('сегодня в 08:10')
    expect(dates.short(new Date(2026, 8, 16, 14, 3))).toBe('вчера в 14:03')
    expect(dates.short(null)).toBe('ни разу')
  })

  it('names the month in it', () => {
    expect(createDates(panel('ru', russian)).short(new Date(2026, 8, 10, 14, 3))).toContain(
      'сентября',
    )
  })

  it('follows the panel when the panel is switched', () => {
    const i18n = panel('en')
    const dates = createDates(i18n)

    expect(dates.short(new Date(2026, 8, 17, 8, 10))).toBe('today at 08:10')

    i18n.load({ 'webx-admin': { dates: russian } }, 'ru')

    expect(dates.short(new Date(2026, 8, 17, 8, 10))).toBe('сегодня в 08:10')
  })
})

describe('the tip', () => {
  it('keeps the second the short line drops', () => {
    expect(createDates(panel('en')).exact(new Date(2026, 8, 17, 8, 10, 54))).toBe(
      'September 17, 2026 at 08:10:54',
    )
  })

  it('has nothing to say about a date that never happened', () => {
    expect(createDates(panel('en')).exact(null)).toBe('')
  })
})

describe('the machine value', () => {
  it('is the moment itself, for `<time datetime>` and for sorting on the server', () => {
    const dates = createDates(panel('en'))

    expect(dates.iso('2026-09-17T05:10:54.000000Z')).toBe('2026-09-17T05:10:54.000Z')
    expect(dates.iso(null)).toBe('')
  })
})

describe('today', () => {
  // The server sends UTC. Read in Moscow, the small hours of a morning belong to the day
  // before as far as UTC is concerned — which is exactly the mistake this is here to catch.
  const was = process.env.TZ

  beforeAll(() => {
    process.env.TZ = 'Europe/Moscow'
  })

  afterAll(() => {
    process.env.TZ = was
  })

  it('is the administrator’s day, not the server’s', () => {
    vi.setSystemTime(new Date('2026-09-17T05:30:00Z'))

    // 22:30 UTC on the sixteenth is half past one in the morning of the seventeenth in Moscow.
    // On UTC dates this is yesterday; to the person reading it, it happened tonight.
    expect(createDates(panel('en')).short('2026-09-16T22:30:00Z')).toBe('today at 01:30')
  })

  it('still ends where the reader’s day ends', () => {
    vi.setSystemTime(new Date('2026-09-17T05:30:00Z'))

    // 20:30 UTC on the sixteenth is 23:30 Moscow — the same evening, and yesterday by morning.
    expect(createDates(panel('en')).short('2026-09-16T20:30:00Z')).toBe('yesterday at 23:30')
  })
})
