// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { codify, slugify } from './slug'

/**
 * The address the field offers while a title is being typed.
 *
 * What it must never do is produce something the server would refuse, or something other than
 * what the server makes of the same title: the panel would then show an address, accept it, and
 * hand back another one. Every expectation here is what `Str::slug($title, $separator, $locale)`
 * answers, which is what `WebxUi\Localization\Slug` calls.
 */
describe('slugify', () => {
  it('lowercases and joins words with hyphens', () => {
    expect(slugify('About Us')).toBe('about-us')
    expect(slugify('  Contact   the team  ')).toBe('contact-the-team')
  })

  it('transliterates by the language the title is written in', () => {
    // Without a language Cyrillic reads the English way: «Хіт» was `xit`.
    expect(slugify('Хіт')).toBe('xit')
    expect(slugify('Хіт', '-', 'uk')).toBe('khit')
    expect(slugify('Хит', '-', 'ru')).toBe('hit')
    expect(slugify('Ціни', '-', 'uk')).toBe('tsiny')
    expect(slugify('Їжак', '-', 'uk')).toBe('yizak')
    expect(slugify('О компании', '-', 'ru')).toBe('o-kompanii')
    expect(slugify('Über uns', '-', 'de')).toBe('ueber-uns')
    expect(slugify('Über uns')).toBe('uber-uns')
    expect(slugify('Sürüş', '-', 'tr')).toBe('surus')
  })

  it('reads a regional code by its language and an unknown language as English', () => {
    expect(slugify('Хіт', '-', 'uk-UA')).toBe('khit')
    expect(slugify('Хіт', '-', 'xx')).toBe('xit')
  })

  it('keeps an accented letter as a letter rather than a separator', () => {
    expect(slugify('Café')).toBe('cafe')
  })

  it('joins a machine name with the separator it is given', () => {
    expect(slugify('Ваш телефон', '_', 'ru')).toBe('vash_telefon')
    expect(slugify('home-page', '_')).toBe('home_page')
  })

  it('leaves nothing that would have to be escaped in an address', () => {
    expect(slugify('Prices & Terms (2026)!')).toBe('prices-terms-2026')
    expect(slugify('Mail me@home_page')).toBe('mail-me-at-home-page')
    expect(slugify('−20%', '-', 'uk')).toBe('20')
    expect(slugify('—')).toBe('')
  })
})

/**
 * A code field's value as it is typed. The expectations are what `Slug::code()` answers for the
 * same text, so the panel never shows a code the server would turn into another one.
 */
describe('codify', () => {
  it('makes a code of anything typed instead of refusing it', () => {
    expect(codify('тормозные колодки', { locale: 'ru' })).toBe('tormoznye-kolodki')
    expect(codify('Тормозные  колодки', { locale: 'ru', separator: '_' })).toBe('tormoznye_kolodki')
    expect(codify('  Brake_Pads!! ')).toBe('brake-pads')
    expect(codify('brake-pads', { separator: '_' })).toBe('brake_pads')
    expect(codify('a 漢 b', { locale: 'ru' })).toBe('a-b')
    expect(codify('!!!')).toBe('')
    expect(codify('漢字')).toBe('')
  })

  it('cuts to the length of the field with no separator left at the end', () => {
    expect(codify('Brake pads', { max: 6 })).toBe('brake')
    expect(codify('тормозные колодки и диски для легковых', { locale: 'ru', max: 32 })).toBe(
      'tormoznye-kolodki-i-diski-dlya-l',
    )
  })

  it('keeps the separator the next word needs while it is being typed', () => {
    expect(codify('тормозные ', { locale: 'ru', typing: true })).toBe('tormoznye-')
    expect(codify('brake_', { typing: true, separator: '_' })).toBe('brake_')
    expect(codify('brake ', { typing: true })).toBe('brake-')
    expect(codify('brake ')).toBe('brake')
    expect(codify(' ', { typing: true })).toBe('')
    // Not past the length: a code that is full takes no separator.
    expect(codify('brake ', { typing: true, max: 5 })).toBe('brake')
  })
})
