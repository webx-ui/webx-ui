import { describe, expect, it } from 'vitest'
import { usageWords } from './schema'

const t = (key: string, params: Record<string, string | number> = {}) =>
  `${key} ${Object.values(params).join(' ')}`.trim()

describe('how many entities a type stands on, in words', () => {
  it('says nothing about a count when there is none', () => {
    expect(usageWords(0, t)).toBe('page.not-used')
  })

  /* The line that used to read "on 1 pages" — and did so exactly when a type had just been
     put on its first page, which is when somebody is most likely looking at it. */
  it('has a line of its own for one', () => {
    expect(usageWords(1, t)).toBe('page.on-page')
  })

  it('counts from two', () => {
    expect(usageWords(2, t)).toBe('page.on-pages 2')
  })
})
