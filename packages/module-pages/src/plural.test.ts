import { describe, expect, it } from 'vitest'
import { pluralForm } from './plural'

describe('pluralForm', () => {
  it('picks the Russian forms by the last digits', () => {
    expect([1, 2, 5, 11, 21, 22, 25].map((n) => pluralForm(n, 'ru'))).toEqual([
      'one',
      'few',
      'many',
      'many',
      'one',
      'few',
      'many',
    ])
  })

  it('has one and other in English', () => {
    expect(pluralForm(1, 'en')).toBe('one')
    expect(pluralForm(5, 'en')).toBe('other')
  })

  it('falls back on English rules for a locale nobody knows', () => {
    expect(pluralForm(3, 'not a locale')).toBe('other')
  })
})
