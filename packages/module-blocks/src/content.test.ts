import { describe, expect, it } from 'vitest'
import {
  cloneNode,
  countType,
  insertNode,
  locate,
  makeNode,
  nestedFields,
  removeNode,
  replaceList,
  typesIn,
  updateValues,
} from './content'
import type { BlockNode } from './types'

function tree(): BlockNode[] {
  return [
    { key: 'a', type: 'hero', values: { title: 'Hi' } },
    {
      key: 'b',
      type: 'section',
      values: {
        tone: 'muted',
        content: [
          { key: 'c', type: 'text', values: { body: 'One' } },
          { key: 'd', type: 'gallery', values: { images: ['x.jpg', 'y.jpg'] } },
        ],
      },
    },
  ]
}

describe('the tree of blocks', () => {
  it('finds a nested node with where it sits', () => {
    const found = locate(tree(), 'd')

    expect(found?.parent?.key).toBe('b')
    expect(found?.field).toBe('content')
    expect(found?.index).toBe(1)
    expect(locate(tree(), 'nope')).toBeNull()
  })

  it('does not mistake a list of strings for nested blocks', () => {
    // `images` is a list too, and walking into it would find no nodes but might throw.
    expect(typesIn(tree())).toEqual(['hero', 'section', 'text', 'gallery'])
    expect(countType(tree(), 'text')).toBe(1)
  })

  it('inserts, removes and updates without touching the original', () => {
    const before = tree()
    const withOne = insertNode(before, 'b', 'content', 1, makeNode('text', { body: 'Two' }))

    expect((withOne[1]!.values.content as BlockNode[]).map((node) => node.type)).toEqual([
      'text',
      'text',
      'gallery',
    ])
    expect((before[1]!.values.content as BlockNode[]).length).toBe(2)

    const without = removeNode(withOne, 'c')
    expect((without[1]!.values.content as BlockNode[]).map((node) => node.key)).not.toContain('c')

    const changed = updateValues(without, 'a', { title: 'Hello' })
    expect(changed[0]!.values.title).toBe('Hello')
    expect(before[0]!.values.title).toBe('Hi')
  })

  it('creates the field when a container has nothing in it yet', () => {
    const next = insertNode(
      [{ key: 's', type: 'section', values: {} }],
      's',
      'content',
      0,
      makeNode('text'),
    )

    expect((next[0]!.values.content as BlockNode[])[0]!.type).toBe('text')
  })

  it('replaces one list wholesale, at the root or under a parent', () => {
    const reordered = replaceList(tree(), null, null, [tree()[1]!, tree()[0]!])
    expect(reordered.map((node) => node.key)).toEqual(['b', 'a'])

    const inner = replaceList(tree(), 'b', 'content', [locate(tree(), 'd')!.node])
    expect((inner[1]!.values.content as BlockNode[]).map((node) => node.key)).toEqual(['d'])
  })

  it('gives a duplicate fresh keys all the way down', () => {
    const copy = cloneNode(tree()[1]!)

    expect(copy.key).not.toBe('b')
    expect((copy.values.content as BlockNode[])[0]!.key).not.toBe('c')
    expect((copy.values.content as BlockNode[])[0]!.values.body).toBe('One')
  })

  it('finds the nested constructors of a schema through its layout', () => {
    const fields = nestedFields([
      { id: 'tone', type: 'wx-select' },
      {
        id: 'card',
        type: 'wx-card',
        children: [{ id: 'content', type: 'wx-blocks', props: { allow: ['text'] } }],
      },
    ])

    expect(fields.map((node) => node.id)).toEqual(['content'])
  })
})
