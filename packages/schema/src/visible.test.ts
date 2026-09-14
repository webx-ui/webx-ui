import { describe, expect, it } from 'vitest'
import { evaluateCondition, isVisible } from './visible'

describe('visibility', () => {
  const model = { indexing: true, kind: 'page', tags: ['a'], count: 2 }

  it('absent or true shows, false hides', () => {
    expect(isVisible({ id: 'a', type: 't' }, model)).toBe(true)
    expect(isVisible({ id: 'a', type: 't', visible: true }, model)).toBe(true)
    expect(isVisible({ id: 'a', type: 't', visible: false }, model)).toBe(false)
  })

  it('compares with is, in and not', () => {
    expect(evaluateCondition({ when: 'indexing', is: true }, model)).toBe(true)
    expect(evaluateCondition({ when: 'indexing', is: 'true' }, model)).toBe(false)
    expect(evaluateCondition({ when: 'kind', in: ['post', 'page'] }, model)).toBe(true)
    expect(evaluateCondition({ when: 'kind', not: 'page' }, model)).toBe(false)
    expect(evaluateCondition({ when: 'missing', is: undefined }, model)).toBe(true)
  })

  it('compares arrays and objects by value', () => {
    expect(evaluateCondition({ when: 'tags', is: ['a'] }, model)).toBe(true)
    expect(evaluateCondition({ when: 'tags', in: [['b'], ['a']] }, model)).toBe(true)
  })

  it('combines with all and any', () => {
    expect(
      evaluateCondition(
        {
          all: [
            { when: 'indexing', is: true },
            { when: 'count', is: 2 },
          ],
        },
        model,
      ),
    ).toBe(true)
    expect(
      evaluateCondition(
        {
          any: [
            { when: 'indexing', is: false },
            { when: 'count', is: 2 },
          ],
        },
        model,
      ),
    ).toBe(true)
    expect(
      evaluateCondition(
        { all: [{ when: 'indexing', is: true }, { any: [{ when: 'count', is: 3 }] }] },
        model,
      ),
    ).toBe(false)
  })
})
