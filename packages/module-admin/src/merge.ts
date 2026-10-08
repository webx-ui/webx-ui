/**
 * Two edits of one record, merged against the version both started from.
 *
 * An editor holds three versions when its save is refused: what it opened with (base), what is
 * in the form now (mine) and what the record is now (theirs). A field only one side changed is
 * not a question — it goes in from whichever side changed it. Only a field both sides changed,
 * to different values, is a conflict, and only a conflict is put to the person.
 *
 * The values are the form's model, so the walk knows three shapes:
 *
 * - a plain object — the fields of a screen, the values of a block, the languages of a localized
 *   field — merged key by key, so that two people writing two languages of one title do not
 *   collide;
 * - a list of blocks (objects with a string `key`) — merged block by block: one side adding,
 *   removing or moving a block is applied by key, and a block one side removed while the other
 *   edited it is a conflict of its own;
 * - anything else — a string, a number, a list of ids — as a whole value.
 *
 * Nothing here knows what a page is: the same walk merges a service, a recipe or a region.
 */

export type MergeChoice = 'mine' | 'theirs'

/** One step of the way to a value: a field (or a language) by name, or a block by key. */
export type MergeSegment = { field: string } | { block: string; type: string }

export interface MergeConflict {
  /** Stable for one place in the tree, so a choice survives a merge being run again. */
  id: string
  path: MergeSegment[]
  /**
   * `changed` — both sides wrote the value; `removed-mine` — this editor removed a block the
   * other side edited; `removed-theirs` — the other side removed a block this editor edited.
   */
  kind: 'changed' | 'removed-mine' | 'removed-theirs'
  base: unknown
  mine: unknown
  theirs: unknown
}

export interface MergeResult<T> {
  /** The merge, with every conflict settled by its choice — this editor's side when there is none. */
  value: T
  conflicts: MergeConflict[]
}

type Plain = Record<string, unknown>

interface Node extends Plain {
  key: string
  type?: unknown
}

interface Walk {
  choices: Record<string, MergeChoice>
  conflicts: MergeConflict[]
}

/** Deleted on one side: what a walk answers to drop the key or the block. */
const GONE = undefined

/**
 * Merge `mine` and `theirs` against `base`.
 *
 * `choices` settles conflicts by id; one with no choice keeps this editor's side in `value` and
 * is listed in `conflicts` all the same, so the caller can tell a clean merge from a settled one.
 */
export function mergeThreeWay<T>(
  base: T,
  mine: T,
  theirs: T,
  choices: Record<string, MergeChoice> = {},
): MergeResult<T> {
  const walk: Walk = { choices, conflicts: [] }

  return { value: merge(base, mine, theirs, [], walk) as T, conflicts: walk.conflicts }
}

/**
 * Where `after` differs from `before`, as paths — what the other side changed, said to the
 * person before they pull it in. A block added or removed is one path, not one per field.
 */
export function changedPaths(before: unknown, after: unknown): MergeSegment[][] {
  const found: MergeSegment[][] = []

  diff(before, after, [], found)

  return found
}

/** Equal as values: key order, and `null` against a missing key, do not count as a difference. */
export function sameValue(a: unknown, b: unknown): boolean {
  if (a === b) return true
  if (a == null || b == null) return a == null && b == null

  if (Array.isArray(a) || Array.isArray(b)) {
    if (!Array.isArray(a) || !Array.isArray(b) || a.length !== b.length) return false

    return a.every((item, index) => sameValue(item, b[index]))
  }

  if (isPlain(a) && isPlain(b)) {
    const keys = new Set([...Object.keys(a), ...Object.keys(b)])

    for (const key of keys) {
      if (!sameValue(a[key], b[key])) return false
    }

    return true
  }

  return false
}

export function conflictId(path: MergeSegment[]): string {
  return path.map((step) => ('block' in step ? `#${step.block}` : step.field)).join('/')
}

function merge(
  base: unknown,
  mine: unknown,
  theirs: unknown,
  path: MergeSegment[],
  walk: Walk,
): unknown {
  if (sameValue(mine, theirs)) return mine
  if (sameValue(base, mine)) return theirs
  if (sameValue(base, theirs)) return mine

  if (isNodeList(mine) && isNodeList(theirs) && (base == null || isNodeList(base))) {
    return mergeList(base ?? [], mine, theirs, path, walk)
  }

  if (isPlain(mine) && isPlain(theirs) && (base == null || isPlain(base))) {
    return mergeObject(base ?? {}, mine, theirs, path, walk)
  }

  return settle(walk, path, 'changed', base, mine, theirs, mine, theirs)
}

function mergeObject(
  base: Plain,
  mine: Plain,
  theirs: Plain,
  path: MergeSegment[],
  walk: Walk,
): Plain {
  const merged: Plain = {}
  const keys = [...Object.keys(mine), ...Object.keys(theirs).filter((key) => !(key in mine))]

  for (const key of keys) {
    const value = merge(base[key], mine[key], theirs[key], [...path, { field: key }], walk)

    if (value !== GONE) merged[key] = value
  }

  return merged
}

function mergeList(
  base: Node[],
  mine: Node[],
  theirs: Node[],
  path: MergeSegment[],
  walk: Walk,
): Node[] {
  const before = byKey(base)
  const ours = byKey(mine)
  const other = byKey(theirs)
  const kept = new Map<string, Node>()

  for (const key of new Set([...ours.keys(), ...other.keys()])) {
    const b = before.get(key)
    const m = ours.get(key)
    const t = other.get(key)
    const at: MergeSegment[] = [...path, { block: key, type: typeOf(m ?? t) }]
    let value: unknown

    if (m && t) {
      value = merge(b, m, t, at, walk)
    } else if (m) {
      // Theirs has no such block: this editor added it, they removed it untouched, or they
      // removed a block this editor was editing — the one case that is a question.
      if (b === undefined) value = m
      else if (sameValue(b, m)) value = GONE
      else value = settle(walk, at, 'removed-theirs', b, m, GONE, m, GONE)
    } else if (t) {
      if (b === undefined) value = t
      else if (sameValue(b, t)) value = GONE
      else value = settle(walk, at, 'removed-mine', b, GONE, t, GONE, t)
    }

    if (value !== GONE) kept.set(key, value as Node)
  }

  // The order of whichever side moved something; this editor's when both did, or neither.
  const theirsMoved = !sameOrder(base, theirs)
  const mineMoved = !sameOrder(base, mine)
  const [lead, follow] = theirsMoved && !mineMoved ? [theirs, mine] : [mine, theirs]

  return order(lead, follow, kept)
}

/** Lay the kept blocks out in the lead's order, and slot the others in after their neighbour. */
function order(lead: Node[], follow: Node[], kept: Map<string, Node>): Node[] {
  const keys = lead.map((node) => node.key).filter((key) => kept.has(key))
  const placed = new Set(keys)

  follow.forEach((node, index) => {
    if (placed.has(node.key) || !kept.has(node.key)) return

    let at = 0

    for (let back = index - 1; back >= 0; back--) {
      const position = keys.indexOf(follow[back]!.key)

      if (position !== -1) {
        at = position + 1
        break
      }
    }

    keys.splice(at, 0, node.key)
    placed.add(node.key)
  })

  return keys.map((key) => kept.get(key)!)
}

/** Record a conflict and answer the side its choice names — this editor's when there is none. */
function settle(
  walk: Walk,
  path: MergeSegment[],
  kind: MergeConflict['kind'],
  base: unknown,
  mine: unknown,
  theirs: unknown,
  keepMine: unknown,
  keepTheirs: unknown,
): unknown {
  const id = conflictId(path)

  walk.conflicts.push({ id, path, kind, base, mine, theirs })

  return walk.choices[id] === 'theirs' ? keepTheirs : keepMine
}

function diff(
  before: unknown,
  after: unknown,
  path: MergeSegment[],
  found: MergeSegment[][],
): void {
  if (sameValue(before, after)) return

  if (isNodeList(before) && isNodeList(after)) {
    const old = byKey(before)
    const now = byKey(after)

    for (const key of new Set([...old.keys(), ...now.keys()])) {
      const at: MergeSegment[] = [
        ...path,
        { block: key, type: typeOf(now.get(key) ?? old.get(key)) },
      ]

      if (old.has(key) && now.has(key)) diff(old.get(key), now.get(key), at, found)
      else found.push(at)
    }

    // Moved without anything else changing: the list itself is what changed.
    if (!sameOrder(before, after)) found.push(path)

    return
  }

  if (isPlain(before) && isPlain(after)) {
    for (const key of new Set([...Object.keys(before), ...Object.keys(after)])) {
      diff(before[key], after[key], [...path, { field: key }], found)
    }

    return
  }

  found.push(path)
}

/** Whether the blocks both sides still have stand in the same order. */
function sameOrder(a: Node[], b: Node[]): boolean {
  const inB = new Set(b.map((node) => node.key))
  const inA = new Set(a.map((node) => node.key))
  const left = a.map((node) => node.key).filter((key) => inB.has(key))
  const right = b.map((node) => node.key).filter((key) => inA.has(key))

  return left.every((key, index) => key === right[index])
}

function byKey(list: Node[]): Map<string, Node> {
  return new Map(list.map((node) => [node.key, node]))
}

function typeOf(node: Node | undefined): string {
  return typeof node?.type === 'string' ? node.type : ''
}

function isPlain(value: unknown): value is Plain {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function isNodeList(value: unknown): value is Node[] {
  return (
    Array.isArray(value) && value.every((item) => isPlain(item) && typeof item.key === 'string')
  )
}
