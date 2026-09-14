import type { Patch, PatchError, PatchOperation, PatchPosition, ScreenNode } from './types'

interface Located {
  /** The array the node sits in: the root, or a parent's `children`. */
  list: ScreenNode[]
  index: number
  node: ScreenNode
}

/** Depth-first search by `id`, remembering the array the match lives in. */
export function locate(root: ScreenNode[], id: string): Located | null {
  for (let index = 0; index < root.length; index += 1) {
    const node = root[index]!
    if (node.id === id) return { list: root, index, node }
    if (node.children) {
      const found = locate(node.children, id)
      if (found) return found
    }
  }
  return null
}

export function findNode(root: ScreenNode[], id: string): ScreenNode | null {
  return locate(root, id)?.node ?? null
}

/** Every `id` in the tree, in document order. Duplicates are kept, so callers can spot them. */
export function collectIds(root: ScreenNode[]): string[] {
  const ids: string[] = []
  const walk = (nodes: ScreenNode[]) => {
    for (const node of nodes) {
      ids.push(node.id)
      if (node.children) walk(node.children)
    }
  }
  walk(root)
  return ids
}

/** Index a node should be inserted at in `list`, or a message when the anchor is missing. */
function indexFor(list: ScreenNode[], position: PatchPosition | undefined): number | string {
  if (!position || position === 'last') return list.length
  if (position === 'first') return 0
  const [where, anchor] = position.split(':', 2) as ['before' | 'after', string]
  const at = list.findIndex((node) => node.id === anchor)
  if (at === -1) return `no sibling "${anchor}" to insert ${where}`
  return where === 'before' ? at : at + 1
}

/**
 * Deep copy of a JSON value. `structuredClone` refuses a Vue reactive proxy, and a
 * tree that came out of a `ref` is exactly that; reading through the proxy is fine.
 */
export function clone<T>(value: T): T {
  if (Array.isArray(value)) return value.map(clone) as T
  if (value && typeof value === 'object') {
    const out: Record<string, unknown> = {}
    for (const [key, item] of Object.entries(value)) out[key] = clone(item)
    return out as T
  }
  return value
}

/**
 * Applies a patch to a tree and returns the result as a new tree; the input is not
 * touched. An operation that cannot be applied — a `target` that does not exist, an
 * anchor that is missing — is skipped and reported, and the ones after it still run:
 * a project patch that survived a rename in the module must not silence the rest.
 */
export function applyPatch(
  root: ScreenNode[],
  patch: Patch,
): { root: ScreenNode[]; errors: PatchError[] } {
  const tree = clone(root)
  const errors: PatchError[] = []

  patch.forEach((op, index) => {
    const message = apply(tree, op)
    if (message) errors.push({ index, op, message })
  })

  return { root: tree, errors }
}

/** Mutates `tree`; returns a message when the operation was refused. */
function apply(tree: ScreenNode[], op: PatchOperation): string | null {
  const found = locate(tree, op.target)
  if (!found) return `target "${op.target}" not found`

  switch (op.op) {
    case 'add': {
      const clash = duplicateIn(tree, op.node)
      if (clash) return `id "${clash}" already exists in the screen`
      const list = (found.node.children ??= [])
      const at = indexFor(list, op.position)
      if (typeof at === 'string') return at
      list.splice(at, 0, clone(op.node))
      return null
    }
    case 'remove':
      found.list.splice(found.index, 1)
      return null
    case 'replace': {
      // The replaced node's own ids are gone, so only the rest of the tree can clash.
      const others = tree.filter((node) => node !== found.node)
      const clash = duplicateIn(others, op.node, found.node)
      if (clash) return `id "${clash}" already exists in the screen`
      found.list.splice(found.index, 1, clone(op.node))
      return null
    }
    case 'move': {
      let list = found.list
      if (op.to !== undefined) {
        const parent = locate(tree, op.to)
        if (!parent) return `destination "${op.to}" not found`
        if (parent.node === found.node || contains(found.node, parent.node)) {
          return `cannot move "${op.target}" into itself`
        }
        list = parent.node.children ??= []
      }
      found.list.splice(found.index, 1)
      const at = indexFor(list, op.position)
      if (typeof at === 'string') {
        // Put it back where it was: a refused move leaves the tree untouched.
        found.list.splice(found.index, 0, found.node)
        return at
      }
      list.splice(at, 0, found.node)
      return null
    }
    case 'set': {
      for (const [key, value] of Object.entries(op)) {
        if (key === 'op' || key === 'target') continue
        if (key === 'props') {
          found.node.props = { ...found.node.props, ...(value as Record<string, unknown>) }
        } else {
          ;(found.node as unknown as Record<string, unknown>)[key] = clone(value)
        }
      }
      return null
    }
  }
}

function contains(ancestor: ScreenNode, node: ScreenNode): boolean {
  return (ancestor.children ?? []).some((child) => child === node || contains(child, node))
}

/** First id of `incoming` that already exists in `tree`, ignoring `except`'s own subtree. */
function duplicateIn(tree: ScreenNode[], incoming: ScreenNode, except?: ScreenNode): string | null {
  const existing = new Set(collectIds(tree))
  if (except) for (const id of collectIds([except])) existing.delete(id)
  return collectIds([incoming]).find((id) => existing.has(id)) ?? null
}
