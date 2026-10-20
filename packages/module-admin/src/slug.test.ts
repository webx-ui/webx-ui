import { describe, expect, it } from 'vitest'
import { slugify } from './slug'

/**
 * The address the field offers while a title is being typed.
 *
 * What it must never do is produce something the server would refuse: the panel would then show
 * an address, accept it, and hand back a validation error on a field nobody touched.
 */
describe('slugify', () => {
  it('lowercases and joins words with hyphens', () => {
    expect(slugify('About Us')).toBe('about-us')
    expect(slugify('  Contact   the team  ')).toBe('contact-the-team')
  })

  it('transliterates the alphabets the panel is translated into', () => {
    expect(slugify('О компании')).toBe('o-kompanii')
    expect(slugify('Ціни')).toBe('tsini')
    expect(slugify('Über uns')).toBe('uber-uns')
    expect(slugify('Sürüş')).toBe('surus')
  })

  it('keeps an accented letter as a letter rather than a separator', () => {
    expect(slugify('Café')).toBe('cafe')
  })

  it('leaves nothing that would have to be escaped in an address', () => {
    expect(slugify('Prices & Terms (2026)!')).toBe('prices-terms-2026')
    expect(slugify('—')).toBe('')
  })
})
