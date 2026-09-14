import { describe, expect, it } from 'vitest'
import { applyPatch, collectIds, findNode } from './patch'
import type { ScreenNode } from './types'

function tree(): ScreenNode[] {
  return [
    {
      id: 'tabs',
      type: 'wx-tabs',
      children: [
        {
          id: 'general',
          type: 'wx-tab',
          label: 'General',
          children: [
            { id: 'name', type: 'wx-input', name: 'name', label: 'Name' },
            { id: 'logo', type: 'wx-media', name: 'logo', props: { aspect: '1/1' } },
          ],
        },
        { id: 'seo', type: 'wx-tab', label: 'SEO', children: [] },
      ],
    },
  ]
}

describe('applyPatch', () => {
  it('leaves the input untouched and returns a new tree', () => {
    const input = tree()
    const { root } = applyPatch(input, [{ op: 'remove', target: 'logo' }])

    expect(findNode(input, 'logo')).not.toBeNull()
    expect(findNode(root, 'logo')).toBeNull()
  })

  it('adds at the end by default, and at first / before / after on request', () => {
    const node = (id: string): ScreenNode => ({ id, type: 'wx-text' })
    const { root, errors } = applyPatch(tree(), [
      { op: 'add', target: 'general', node: node('z') },
      { op: 'add', target: 'general', node: node('a'), position: 'first' },
      { op: 'add', target: 'general', node: node('m'), position: 'before:logo' },
      { op: 'add', target: 'general', node: node('n'), position: 'after:logo' },
    ])

    expect(errors).toEqual([])
    expect(findNode(root, 'general')!.children!.map((c) => c.id)).toEqual([
      'a',
      'name',
      'm',
      'logo',
      'n',
      'z',
    ])
  })

  it('replaces a node, allowing a different id', () => {
    const { root } = applyPatch(tree(), [
      { op: 'replace', target: 'logo', node: { id: 'logo-path', type: 'wx-input', name: 'logo' } },
    ])

    expect(findNode(root, 'logo')).toBeNull()
    expect(findNode(root, 'logo-path')!.type).toBe('wx-input')
  })

  it('moves among siblings and into another parent', () => {
    const { root, errors } = applyPatch(tree(), [
      { op: 'move', target: 'seo', position: 'first' },
      { op: 'move', target: 'logo', to: 'seo' },
    ])

    expect(errors).toEqual([])
    expect(findNode(root, 'tabs')!.children!.map((c) => c.id)).toEqual(['seo', 'general'])
    expect(findNode(root, 'seo')!.children!.map((c) => c.id)).toEqual(['logo'])
    expect(findNode(root, 'general')!.children!.map((c) => c.id)).toEqual(['name'])
  })

  it('sets keys shallowly and merges props', () => {
    const { root } = applyPatch(tree(), [
      { op: 'set', target: 'logo', label: 'Logo', props: { accept: 'image/*' } },
    ])
    const logo = findNode(root, 'logo')!

    expect(logo.label).toBe('Logo')
    expect(logo.props).toEqual({ aspect: '1/1', accept: 'image/*' })
  })

  it('skips an operation whose target is missing and keeps going', () => {
    const { root, errors } = applyPatch(tree(), [
      { op: 'remove', target: 'nope' },
      { op: 'remove', target: 'logo' },
    ])

    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ index: 0, message: 'target "nope" not found' })
    expect(findNode(root, 'logo')).toBeNull()
  })

  it('refuses an anchor that does not exist, and a duplicate id', () => {
    const { root, errors } = applyPatch(tree(), [
      { op: 'add', target: 'general', node: { id: 'x', type: 'wx-text' }, position: 'after:ghost' },
      { op: 'add', target: 'seo', node: { id: 'name', type: 'wx-text' } },
      { op: 'move', target: 'name', position: 'before:ghost' },
    ])

    expect(errors.map((e) => e.message)).toEqual([
      'no sibling "ghost" to insert after',
      'id "name" already exists in the screen',
      'no sibling "ghost" to insert before',
    ])
    // A refused move puts the node back where it was.
    expect(findNode(root, 'general')!.children!.map((c) => c.id)).toEqual(['name', 'logo'])
  })

  it('will not move a node into its own subtree', () => {
    const { errors } = applyPatch(tree(), [{ op: 'move', target: 'tabs', to: 'general' }])

    expect(errors[0]?.message).toBe('cannot move "tabs" into itself')
  })

  it('collects ids in document order', () => {
    expect(collectIds(tree())).toEqual(['tabs', 'general', 'name', 'logo', 'seo'])
  })
})
