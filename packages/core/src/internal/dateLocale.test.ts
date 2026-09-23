import { describe, expect, it } from 'vitest'
import { dateFnsLocale } from './dateLocale'

describe('dateFnsLocale', () => {
  it('names the months in the asked-for language', () => {
    const { localize } = dateFnsLocale('ru')

    expect(localize.month(8, { width: 'wide', context: 'standalone' })).toBe('сентябрь')
    expect(localize.month(0, { width: 'wide', context: 'standalone' })).toBe('январь')
  })

  /** A calendar header is a month on its own; "15 сентября" is the same month in a sentence. */
  it('keeps the standalone and the sentence form apart', () => {
    const { localize } = dateFnsLocale('ru')

    expect(localize.month(8, { width: 'wide', context: 'standalone' })).toBe('сентябрь')
    expect(localize.month(8, { width: 'wide', context: 'formatting' })).toBe('сентября')
  })

  it('names the weekdays, starting at Sunday the way date-fns counts them', () => {
    const { localize } = dateFnsLocale('ru')

    expect(localize.day(0, { width: 'wide' })).toBe('воскресенье')
    expect(localize.day(1, { width: 'wide' })).toBe('понедельник')
  })

  /** The picker's own header format asks for the two-letter width `Intl` does not offer. */
  it('cuts the abbreviated weekday down to the two-letter width', () => {
    expect(dateFnsLocale('en-US').localize.day(0, { width: 'short' })).toBe('Su')
    expect(dateFnsLocale('ru').localize.day(1, { width: 'short' })).toBe('пн')
    expect(dateFnsLocale('fr').localize.day(2, { width: 'short' })).toBe('ma')
  })

  it('reads a month name back, longest form first', () => {
    const { match } = dateFnsLocale('ru')

    expect(match.month('сентябрь 2026')).toEqual({ value: 8, rest: ' 2026' })
    expect(match.month('сент. 2026')?.value).toBe(8)
    expect(match.month('Movember')).toBeNull()
  })

  it('reads an ordinal number back', () => {
    expect(dateFnsLocale('en-US').match.ordinalNumber('14th of March')).toMatchObject({ value: 14 })
  })

  it('spells English ordinals and leaves other languages as digits', () => {
    expect(dateFnsLocale('en-US').localize.ordinalNumber(1, { unit: 'date' })).toBe('1st')
    expect(dateFnsLocale('en-US').localize.ordinalNumber(11, { unit: 'date' })).toBe('11th')
    expect(dateFnsLocale('ru').localize.ordinalNumber(1, { unit: 'date' })).toBe('1')
  })

  it('carries the language it was asked for', () => {
    expect(dateFnsLocale('pt-BR').code).toBe('pt-BR')
  })

  /* `Intl` throws a RangeError on a malformed tag instead of falling back to anything. */
  it('falls back to English rather than throwing on a tag Intl refuses', () => {
    expect(dateFnsLocale('not a tag').localize.month(8, { width: 'wide' })).toBe('September')
  })
})
