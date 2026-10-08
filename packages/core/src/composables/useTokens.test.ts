import { describe, expect, it } from 'vitest'
import { tokenQuery, tokenSegments, tokenSpans } from './useTokens'

const known = new Set(['phone', 'year'])

describe('tokenSpans', () => {
  it('finds known names, with or without arguments', () => {
    const text = 'Call [phone] or [phone format=intl link="no"] in [year]'

    expect(tokenSpans(text, known).map((span) => text.slice(span.from, span.to))).toEqual([
      '[phone]',
      '[phone format=intl link="no"]',
      '[year]',
    ])
  })

  it('leaves unknown names and the escaped form as text', () => {
    expect(tokenSpans('[fax] [[phone]] [Phone]', known)).toEqual([])
  })

  it('draws the inner one of a half-escaped pair, as the site prints it', () => {
    const text = '[[phone]'

    expect(tokenSpans(text, known)).toEqual([{ from: 1, to: 8, name: 'phone' }])
  })
})

describe('tokenSegments', () => {
  it('cuts the text at its placeholders', () => {
    expect(tokenSegments('a [year] b', known)).toEqual([
      { text: 'a ', token: null },
      { text: '[year]', token: 'year' },
      { text: ' b', token: null },
    ])
  })

  it('keeps an empty text as one empty segment', () => {
    expect(tokenSegments('', known)).toEqual([{ text: '', token: null }])
  })
})

describe('tokenQuery', () => {
  it('reads a bracket and the start of a name before the caret', () => {
    expect(tokenQuery('Call [')).toEqual({ from: 5, query: '' })
    expect(tokenQuery('Call [ph')).toEqual({ from: 5, query: 'ph' })
    expect(tokenQuery('[ph')).toEqual({ from: 0, query: 'ph' })
  })

  it('is nothing after a space, a closed bracket or a second bracket', () => {
    expect(tokenQuery('[phone format=')).toBeNull()
    expect(tokenQuery('[phone]')).toBeNull()
    expect(tokenQuery('[[ph')).toBeNull()
    expect(tokenQuery('Call')).toBeNull()
  })
})
