import { describe, expect, it } from 'vitest'
import { changedPaths, conflictId, mergeThreeWay, sameValue } from './merge'

const hero = (heading: string, eyebrow = '') => ({
  key: 'hero1',
  type: 'hero',
  values: { heading: { en: heading }, eyebrow: { en: eyebrow } },
})

const text = (key: string, body: string) => ({ key, type: 'text', values: { body: { en: body } } })

describe('mergeThreeWay', () => {
  it('takes each side’s change when they touched different fields of one block', () => {
    const base = { title: { en: 'Home' }, blocks: [hero('Deeply heard')] }
    const mine = { title: { en: 'Home' }, blocks: [hero('TEST Deeply heard')] }
    const theirs = { title: { en: 'Home' }, blocks: [hero('Deeply heard', 'Since 2010')] }

    const { value, conflicts } = mergeThreeWay(base, mine, theirs)

    expect(conflicts).toEqual([])
    expect(value.blocks[0]!.values).toEqual({
      heading: { en: 'TEST Deeply heard' },
      eyebrow: { en: 'Since 2010' },
    })
  })

  it('merges two languages of one field as two fields', () => {
    const { value, conflicts } = mergeThreeWay(
      { title: { en: 'About', de: 'Über' } },
      { title: { en: 'About us', de: 'Über' } },
      { title: { en: 'About', de: 'Über uns' } },
    )

    expect(conflicts).toEqual([])
    expect(value.title).toEqual({ en: 'About us', de: 'Über uns' })
  })

  it('names a field both sides changed, and keeps this side until told otherwise', () => {
    const base = { blocks: [hero('Deeply heard')] }
    const mine = { blocks: [hero('Mine')] }
    const theirs = { blocks: [hero('Theirs')] }

    const { value, conflicts } = mergeThreeWay(base, mine, theirs)

    expect(conflicts).toHaveLength(1)
    expect(conflicts[0]).toMatchObject({ kind: 'changed', base: 'Deeply heard', mine: 'Mine', theirs: 'Theirs' })
    expect(conflicts[0]!.path).toEqual([
      { field: 'blocks' },
      { block: 'hero1', type: 'hero' },
      { field: 'values' },
      { field: 'heading' },
      { field: 'en' },
    ])
    expect(value.blocks[0]!.values.heading.en).toBe('Mine')
  })

  it('settles a conflict by its choice and still keeps the other side’s other changes', () => {
    const base = { title: { en: 'Home' }, blocks: [hero('Deeply heard')] }
    const mine = { title: { en: 'Home' }, blocks: [hero('Mine')] }
    const theirs = { title: { en: 'Welcome' }, blocks: [hero('Theirs', 'Since 2010')] }

    const id = conflictId([{ field: 'blocks' }, { block: 'hero1', type: 'hero' }, { field: 'values' }, { field: 'heading' }, { field: 'en' }])

    // «Keep mine» on the one conflict: their title and eyebrow are not collateral.
    const kept = mergeThreeWay(base, mine, theirs, { [id]: 'mine' }).value

    expect(kept.title).toEqual({ en: 'Welcome' })
    expect(kept.blocks[0]!.values).toEqual({ heading: { en: 'Mine' }, eyebrow: { en: 'Since 2010' } })

    const taken = mergeThreeWay(base, mine, theirs, { [id]: 'theirs' }).value

    expect(taken.blocks[0]!.values.heading.en).toBe('Theirs')
  })

  it('adds the blocks each side added, each after its neighbour', () => {
    const base = { blocks: [text('a', 'A'), text('b', 'B')] }
    const mine = { blocks: [text('a', 'A'), text('m', 'M'), text('b', 'B')] }
    const theirs = { blocks: [text('a', 'A'), text('b', 'B'), text('t', 'T')] }

    const { value, conflicts } = mergeThreeWay(base, mine, theirs)

    expect(conflicts).toEqual([])
    expect(value.blocks.map((node) => node.key)).toEqual(['a', 'm', 'b', 't'])
  })

  it('removes a block one side removed and the other left alone', () => {
    const base = { blocks: [text('a', 'A'), text('b', 'B')] }
    const mine = { blocks: [text('a', 'A edited'), text('b', 'B')] }
    const theirs = { blocks: [text('a', 'A')] }

    const { value, conflicts } = mergeThreeWay(base, mine, theirs)

    expect(conflicts).toEqual([])
    expect(value.blocks).toEqual([text('a', 'A edited')])
  })

  it('asks about a block one side removed while the other edited it', () => {
    const base = { blocks: [text('a', 'A'), text('b', 'B')] }
    const mine = { blocks: [text('a', 'A')] }
    const theirs = { blocks: [text('a', 'A'), text('b', 'B edited')] }

    const { value, conflicts } = mergeThreeWay(base, mine, theirs)

    expect(conflicts).toHaveLength(1)
    expect(conflicts[0]!.kind).toBe('removed-mine')
    expect(value.blocks.map((node) => node.key)).toEqual(['a'])

    const kept = mergeThreeWay(base, mine, theirs, { [conflicts[0]!.id]: 'theirs' }).value

    expect(kept.blocks.map((node) => node.key)).toEqual(['a', 'b'])
  })

  it('follows the side that moved blocks', () => {
    const base = { blocks: [text('a', 'A'), text('b', 'B'), text('c', 'C')] }
    const mine = { blocks: [text('a', 'A mine'), text('b', 'B'), text('c', 'C')] }
    const theirs = { blocks: [text('c', 'C'), text('a', 'A'), text('b', 'B')] }

    const { value } = mergeThreeWay(base, mine, theirs)

    expect(value.blocks.map((node) => node.key)).toEqual(['c', 'a', 'b'])
    expect(value.blocks[1]!.values.body.en).toBe('A mine')
  })

  it('merges the blocks nested inside a block', () => {
    const columns = (children: unknown[]) => ({ key: 'cols', type: 'columns', values: { items: children } })
    const base = [columns([text('x', 'X'), text('y', 'Y')])]
    const mine = [columns([text('x', 'X mine'), text('y', 'Y')])]
    const theirs = [columns([text('x', 'X'), text('y', 'Y theirs')])]

    const { value, conflicts } = mergeThreeWay(base, mine, theirs)

    expect(conflicts).toEqual([])
    expect(value).toEqual([columns([text('x', 'X mine'), text('y', 'Y theirs')])])
  })

  it('treats a list of ids as one value', () => {
    const { conflicts } = mergeThreeWay({ categories: [1] }, { categories: [1, 2] }, { categories: [1, 3] })

    expect(conflicts).toHaveLength(1)
    expect(conflicts[0]!.path).toEqual([{ field: 'categories' }])
  })
})

describe('changedPaths', () => {
  it('names the place the other side changed, and a new block as one place', () => {
    const before = { blocks: [hero('Deeply heard')] }
    const after = { blocks: [hero('Deeply heard', 'Since 2010'), text('n', 'New')] }

    expect(changedPaths(before, after).map(conflictId)).toEqual([
      'blocks/#hero1/values/eyebrow/en',
      'blocks/#n',
    ])
  })
})

describe('sameValue', () => {
  it('ignores key order and a null standing for a missing key', () => {
    expect(sameValue({ a: 1, b: null }, { a: 1 })).toBe(true)
    expect(sameValue({ en: 'x', de: 'y' }, { de: 'y', en: 'x' })).toBe(true)
    expect(sameValue([1, 2], [2, 1])).toBe(false)
  })
})
