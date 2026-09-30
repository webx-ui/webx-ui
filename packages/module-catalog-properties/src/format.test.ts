import { describe, expect, it } from 'vitest'
import { exampleNumber, formatNumber, propertyName, wordsIn } from './format'

describe('formatNumber()', () => {
  it('glues the units on as written, spaces included', () => {
    expect(formatNumber(12, { prefix: '⌀', suffix: ' мм' }, 'ru')).toBe('⌀12 мм')
    expect(formatNumber(8, { prefix: 'M' }, 'en')).toBe('M8')
  })

  it('keeps the precision and the decimal mark of the language', () => {
    expect(formatNumber(1.35, { precision: 2, suffix: ' kg' }, 'en')).toBe('1.35 kg')
    expect(formatNumber(1.35, { precision: 2, suffix: ' кг' }, 'ru')).toBe('1,35 кг')
    expect(formatNumber(1.35, { precision: 0 }, 'en')).toBe('1')
  })

  it('shows an example with as many digits as the property keeps', () => {
    expect(exampleNumber(0)).toBe(12)
    expect(formatNumber(exampleNumber(2), { precision: 2 }, 'en')).toBe('12.50')
  })
})

describe('words', () => {
  it('reads the language asked, then any written one, and an empty PHP array as nothing', () => {
    expect(wordsIn({ ru: 'Цвет', en: 'Colour' }, 'en')).toBe('Colour')
    expect(wordsIn({ ru: 'Цвет' }, 'en')).toBe('Цвет')
    expect(wordsIn([], 'en', '—')).toBe('—')
  })

  it('names a property by its title, its code, its number', () => {
    expect(propertyName({ id: 3, title: { en: 'Weight' }, code: { en: 'weight' } }, 'en')).toBe(
      'Weight',
    )
    expect(propertyName({ id: 3, title: [], code: { en: 'weight' } }, 'en')).toBe('weight')
    expect(propertyName({ id: 3, title: null, code: null }, 'en')).toBe('#3')
  })
})
