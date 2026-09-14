import { describe, expect, it } from 'vitest'
import { validatePatch, validateScreen } from './validate'

describe('validateScreen', () => {
  it('accepts a sound tree', () => {
    expect(
      validateScreen([
        {
          id: 'card',
          type: 'wx-card',
          label: 'Card',
          children: [
            { id: 'name', type: 'wx-input', name: 'name', localized: true, props: {} },
            {
              id: 'x',
              type: 'wx-input',
              visible: { when: 'name', is: 'a' },
              slot: null,
              can: null,
            },
          ],
        },
      ]),
    ).toEqual([])
  })

  it('reports missing keys, unknown keys and duplicate ids with a path', () => {
    const errors = validateScreen([
      { id: 'a', type: 'wx-card', childrens: [] },
      { id: 'a', type: '' },
      { type: 'wx-text' },
      { id: 'b', type: 'wx-card', children: [{ id: 'c', type: 'wx-text', label: 3 }] },
    ])

    expect(errors).toEqual([
      { path: 'root[0]', message: 'unknown key "childrens"' },
      { path: 'root[1]', message: 'duplicate id "a"' },
      { path: 'root[1]', message: '"type" is required and must be a non-empty string' },
      { path: 'root[2]', message: '"id" is required and must be a non-empty string' },
      { path: 'root[3].children[0]', message: '"label" must be a string' },
    ])
  })

  it('checks the shape of a condition', () => {
    const errors = validateScreen([
      { id: 'a', type: 't', visible: { when: 'x' } },
      { id: 'b', type: 't', visible: { all: 'nope' } },
      { id: 'c', type: 't', visible: 'yes' },
    ])

    expect(errors.map((e) => e.message)).toEqual([
      'a condition needs exactly one of "is", "in", "not"',
      '"all" / "any" must be an array of conditions',
      'visible must be a boolean or a condition object',
    ])
  })

  it('rejects something that is not a tree', () => {
    expect(validateScreen({})).toEqual([
      { path: 'root', message: 'root must be an array of nodes' },
    ])
  })
})

describe('validatePatch', () => {
  it('accepts every operation in its proper shape', () => {
    expect(
      validatePatch([
        { op: 'add', target: 'a', node: { id: 'n', type: 't' }, position: 'after:x' },
        { op: 'remove', target: 'a' },
        { op: 'replace', target: 'a', node: { id: 'n', type: 't' } },
        { op: 'move', target: 'a', to: 'b', position: 'first' },
        { op: 'set', target: 'a', props: { rows: 4 }, label: 'L' },
      ]),
    ).toEqual([])
  })

  it('reports an unknown op, a missing node, a bad position and a set of id', () => {
    const errors = validatePatch([
      { op: 'swap', target: 'a' },
      { op: 'add', target: 'a' },
      { op: 'move', target: 'a', position: 'middle' },
      { op: 'set', target: 'a', id: 'b' },
      { op: 'remove' },
    ])

    expect(errors.map((e) => `${e.path} ${e.message}`)).toEqual([
      'patch[0] "op" must be one of add, remove, replace, move, set',
      'patch[1] "add" needs a "node"',
      'patch[2] "position" must be first, last, before:<id> or after:<id>',
      'patch[3] "set" cannot change "id"',
      'patch[4] "target" is required: the id of a node',
    ])
  })
})
