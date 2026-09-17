import type { ScreenNode } from '@webx-ui/schema'
import type { BlockNode } from './types'

/**
 * Walking and rewriting a tree of blocks.
 *
 * A node is `{ key, type, values }`, and a value that is itself a list of such nodes is a
 * nested constructor — recognised by shape rather than by schema, so a tree can be walked
 * for a type nobody can look up any more. Every rewrite returns a new tree and leaves the
 * one it was given alone: the field's model is replaced, never mutated, which is what keeps
 * the renderer's `update` and the preview's swap in step.
 */

export function isNode(value: unknown): value is BlockNode {
  return (
    typeof value === 'object' &&
    value !== null &&
    typeof (value as { type?: unknown }).type === 'string' &&
    (value as { type: string }).type !== ''
  )
}

/** A list of nodes — a nested constructor's value — as opposed to a list of anything else. */
export function isNodeList(value: unknown): value is BlockNode[] {
  return Array.isArray(value) && value.length > 0 && value.every(isNode)
}

/** An id that survives a drag: random, not positional. */
export function newKey(): string {
  const random =
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
      ? crypto.randomUUID().replace(/-/g, '')
      : Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)

  return random.slice(0, 12)
}

export function makeNode(type: string, values: Record<string, unknown> = {}): BlockNode {
  return { key: newKey(), type, values }
}

/** Where a node sits: the list it is in, its index there, and the field of the parent. */
export interface Located {
  node: BlockNode
  parent: BlockNode | null
  field: string | null
  list: BlockNode[]
  index: number
}

export function locate(tree: BlockNode[], key: string): Located | null {
  return find(tree, key, null, null)
}

function find(
  list: BlockNode[],
  key: string,
  parent: BlockNode | null,
  field: string | null,
): Located | null {
  for (let index = 0; index < list.length; index++) {
    const node = list[index]!

    if (node.key === key) return { node, parent, field, list, index }

    for (const [name, value] of Object.entries(node.values ?? {})) {
      if (isNodeList(value)) {
        const found = find(value, key, node, name)
        if (found) return found
      }
    }
  }

  return null
}

/** Every node, parents before children. */
export function walk(
  tree: BlockNode[],
  visit: (node: BlockNode, depth: number, parent: BlockNode | null) => void,
  depth = 0,
  parent: BlockNode | null = null,
): void {
  for (const node of tree) {
    if (!isNode(node)) continue

    visit(node, depth, parent)

    for (const value of Object.values(node.values ?? {})) {
      if (isNodeList(value)) walk(value, visit, depth + 1, node)
    }
  }
}

/** How many instances of a type the tree holds, nested ones included. */
export function countType(tree: BlockNode[], slug: string): number {
  let count = 0
  walk(tree, (node) => {
    if (node.type === slug) count++
  })

  return count
}

/** How many blocks sit inside a node, at any depth — what removing it takes with it. */
export function countInside(node: BlockNode): number {
  let count = 0

  for (const value of Object.values(node.values ?? {})) {
    if (isNodeList(value)) walk(value, () => count++)
  }

  return count
}

/** The distinct types a tree uses, in first-seen order. */
export function typesIn(tree: BlockNode[]): string[] {
  const seen: string[] = []
  walk(tree, (node) => {
    if (!seen.includes(node.type)) seen.push(node.type)
  })

  return seen
}

/** The `wx-blocks` nodes of a schema, through cards and tabs. */
export function nestedFields(schema: ScreenNode[]): ScreenNode[] {
  const found: ScreenNode[] = []

  for (const node of schema) {
    if (node.type === 'wx-blocks') found.push(node)
    if (node.children) found.push(...nestedFields(node.children))
  }

  return found
}

/** A copy with fresh keys all the way down — what a duplicate is. */
export function cloneNode(node: BlockNode): BlockNode {
  const values: Record<string, unknown> = {}

  for (const [name, value] of Object.entries(node.values ?? {})) {
    values[name] = isNodeList(value) ? value.map(cloneNode) : clone(value)
  }

  return { key: newKey(), type: node.type, values }
}

/** The list a parent holds in a field, or the root when there is no parent. */
function listAt(
  tree: BlockNode[],
  parentKey: string | null,
  field: string | null,
): BlockNode[] | null {
  if (parentKey === null) return tree

  const parent = locate(tree, parentKey)
  if (!parent || field === null) return null

  const existing = parent.node.values[field]

  if (!Array.isArray(existing)) {
    parent.node.values[field] = []
  }

  return parent.node.values[field] as BlockNode[]
}

export function insertNode(
  tree: BlockNode[],
  parentKey: string | null,
  field: string | null,
  index: number,
  node: BlockNode,
): BlockNode[] {
  const next = clone(tree)
  const list = listAt(next, parentKey, field)

  if (!list) return tree

  list.splice(Math.max(0, Math.min(index, list.length)), 0, node)

  return next
}

export function removeNode(tree: BlockNode[], key: string): BlockNode[] {
  const next = clone(tree)
  const found = locate(next, key)

  if (!found) return tree

  found.list.splice(found.index, 1)

  return next
}

export function updateValues(
  tree: BlockNode[],
  key: string,
  values: Record<string, unknown>,
): BlockNode[] {
  const next = clone(tree)
  const found = locate(next, key)

  if (!found) return tree

  found.node.values = values

  return next
}

/** The list under one parent replaced wholesale — what a sortable list hands back. */
export function replaceList(
  tree: BlockNode[],
  parentKey: string | null,
  field: string | null,
  list: BlockNode[],
): BlockNode[] {
  if (parentKey === null) return clone(list)

  const next = clone(tree)
  const parent = locate(next, parentKey)

  if (!parent || field === null) return tree

  parent.node.values[field] = clone(list)

  return next
}

/** Values are JSON — that is the contract with the server — so a JSON round trip is a clone. */
export function clone<T>(value: T): T {
  return value === undefined ? value : (JSON.parse(JSON.stringify(value)) as T)
}
