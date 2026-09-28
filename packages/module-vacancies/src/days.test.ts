import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { WxDatePicker } from '@webx-ui/core'
import { dayOf, formatDay, longDay } from './days'

/*
 * The zone is the point of these tests, so each one sets it: Node reads `TZ` again the moment it
 * changes. A test run in UTC would pass on the bug (CLAUDE.md §4 — in UTC everything adds up):
 * `new Date('2026-11-30')` is the 29th in Honolulu and still the 30th in Kiritimati.
 */
const before = process.env.TZ

const ZONES = ['Pacific/Honolulu', 'America/New_York', 'Pacific/Kiritimati', 'Asia/Hong_Kong']

afterEach(() => {
  process.env.TZ = before
})

describe('a day of a vacancy is the same day in every zone', () => {
  it.each(ZONES)('%s: read as the day it names', (zone) => {
    process.env.TZ = zone

    const at = dayOf('2026-11-30')!

    expect([at.getFullYear(), at.getMonth() + 1, at.getDate()]).toEqual([2026, 11, 30])
    expect(formatDay('2026-11-30', 'en-GB', new Date(2026, 0, 1))).toBe('30 Nov')
    expect(longDay('2026-11-30', 'en-GB')).toBe('30 November 2026')
  })

  it('says the year of a day that is not this year', () => {
    expect(formatDay('2027-01-15', 'en-GB', new Date(2026, 8, 28))).toBe('15 Jan 2027')
  })

  it('draws nothing for no day, and for something that is not one', () => {
    expect(formatDay(null, 'en-GB')).toBe('')
    expect(formatDay('soon', 'en-GB')).toBe('')
  })

  /*
   * The picker of `vacancies.form` holds the day as the string it was given — `valueFormat:
   * "yyyy-MM-dd"` is parsed and printed in the browser's zone, so there is nothing to shift. Checked
   * on the field the editor sees, in a zone on each side of the date line.
   */
  it.each(ZONES)('%s: the picker shows the day it was given', async (zone) => {
    process.env.TZ = zone

    const wrapper = mount(WxDatePicker, {
      props: { modelValue: '2026-11-30', type: 'date', valueFormat: 'yyyy-MM-dd' },
    })

    await flushPromises()

    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('30.11.2026')

    wrapper.unmount()
  })
})
