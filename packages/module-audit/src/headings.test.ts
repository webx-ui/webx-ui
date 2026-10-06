import { describe, expect, it } from 'vitest'
import { outlineHints, outlineRows } from './headings'

const t = (key: string, params?: Record<string, string | number>) =>
  params ? `${key} ${JSON.stringify(params)}` : key

describe('the heading map', () => {
  it('draws a skipped level where it should have been', () => {
    expect(
      outlineRows([
        [1, 'A'],
        [3, 'B'],
      ]),
    ).toEqual([
      { level: 1, text: 'A', missing: false },
      { level: 2, text: '', missing: true, after: 1 },
      { level: 3, text: 'B', missing: false },
    ])
  })

  it('says nothing about an outline in order', () => {
    expect(
      outlineHints(
        [
          [1, 'A'],
          [2, 'B'],
          [3, 'C'],
          [2, 'D'],
        ],
        t,
      ),
    ).toEqual([])
  })

  it('names every rule the order breaks', () => {
    expect(
      outlineHints(
        [
          [2, 'News'],
          [1, 'A'],
          [1, 'B'],
          [3, ''],
          [5, 'C'],
        ],
        t,
      ),
    ).toEqual([
      'page.headings-many-h1 {"count":2}',
      'page.headings-h1-not-first {"level":"H2"}',
      'page.headings-skipped {"count":2}',
      'page.headings-empty {"count":1}',
    ])
    expect(outlineHints([[2, 'A']], t)).toEqual(['page.headings-no-h1'])
  })
})
