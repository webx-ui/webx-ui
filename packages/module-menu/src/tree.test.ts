import { describe, expect, it } from 'vitest'
import { countBranch, findItem, movedId } from './tree'
import type { MenuItemRow } from './types'

function item(id: number, children: MenuItemRow[] = []): MenuItemRow {
  return {
    id,
    parent_id: null,
    depth: 0,
    title: {},
    label: `Item ${id}`,
    target: 'none',
    entity_type: null,
    entity_id: null,
    url: null,
    hash: null,
    href: null,
    variant: 'link',
    is_heading: false,
    new_tab: false,
    rel: [],
    locales: [],
    visible: true,
    available: true,
    resolved: null,
    children,
  }
}

/**
 * What a drag means, read out of the two arrays it leaves behind.
 *
 * The gesture itself is a browser's business — jsdom has no pointer and no layout — so what is
 * checked here is the arithmetic the screen does afterwards, which is where a move that lands in
 * the wrong place comes from.
 */
describe('movedId', () => {
  it('finds the row that arrived from another level', () => {
    expect(movedId([1, 2], [1, 7, 2])).toBe(7)
  })

  it('says nothing about the level a row left', () => {
    expect(movedId([1, 7, 2], [1, 2])).toBeNull()
  })

  it('finds the row that changed places within its level', () => {
    expect(movedId([1, 2, 3], [2, 3, 1])).toBe(1)
    expect(movedId([1, 2, 3], [1, 3, 2])).toBe(3)
  })

  it('says nothing when a drag ended where it began', () => {
    expect(movedId([1, 2, 3], [1, 2, 3])).toBeNull()
  })

  it('handles the first row of an empty level', () => {
    expect(movedId([], [4])).toBe(4)
  })
})

describe('the tree itself', () => {
  it('finds an item at any depth', () => {
    const tree = [item(1, [item(2, [item(3)])]), item(4)]

    expect(findItem(tree, 3)?.id).toBe(3)
    expect(findItem(tree, 9)).toBeNull()
  })

  it('counts what a delete takes with it', () => {
    expect(countBranch(item(1, [item(2, [item(3)])]))).toBe(2)
    expect(countBranch(item(1))).toBe(0)
  })
})
