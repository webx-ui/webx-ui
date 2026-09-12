import type { TreeDropZone, TreeKey } from '../../composables/useTreeNodes'

export type { TreeDropZone, TreeKey }

export type TreeSize = 'sm' | 'md'

/**
 * A node as the tree hopes to find it. Every field is optional and every name is a prop
 * away from being something else, so a record straight out of a Laravel controller —
 * `{ id, title, children }` — is a tree without a `map` over it first.
 */
export interface TreeNode {
  id?: TreeKey
  key?: TreeKey
  label?: string
  children?: TreeNode[]
  disabled?: boolean
  /**
   * Says this node has nothing under it. Only needed with `lazy`, where an empty
   * `children` cannot tell "not loaded yet" from "nothing there".
   */
  leaf?: boolean
  [field: string]: unknown
}

/** What a finished move says: the node, where it went, and how it got there. */
export interface TreeDropEvent<T = TreeNode> {
  node: T
  /** The node it was dropped on. */
  target: T
  zone: TreeDropZone
  /** The node it now hangs from — `null` at the top level. */
  parent: T | null
  /** Its position among its new siblings. */
  index: number
  via: 'pointer' | 'keyboard'
}

export interface TreeProps<T = TreeNode> {
  /** Field holding the identity of a node. */
  nodeKey?: string
  /** Field holding the text of a node. */
  labelKey?: string
  /** Field holding the array of children. */
  childrenKey?: string
  /** Field that, when true, makes a node unselectable and unmovable. */
  disabledKey?: string
  /** Field that says a node has no children to fetch. Only read when `lazy`. */
  leafKey?: string
  /** Expand every branch once, on the first render. */
  defaultExpandAll?: boolean
  /** Opening a branch closes the others at its level. */
  accordion?: boolean
  /** Clicking a label opens the branch as well as selecting it. */
  expandOnClick?: boolean
  /** Adds a checkbox to every node. */
  checkable?: boolean
  /**
   * Checking a branch leaves its children alone. Off — the default — a branch follows
   * its children and its children follow it, which is what a permission tree wants.
   */
  checkStrictly?: boolean
  /** Rows can be picked up and dropped before, after or inside another. */
  draggable?: boolean
  /** Nodes this returns `false` for cannot be picked up. */
  allowDrag?: (node: T) => boolean
  /** Vetoes a landing spot — say, to keep a page out of the root. */
  allowDrop?: (drag: T, drop: T, zone: TreeDropZone) => boolean
  /** Children are fetched when a branch first opens. Requires `load`. */
  lazy?: boolean
  /** Fetches the children of one node. */
  load?: (node: T) => T[] | Promise<T[]>
  /**
   * Shows only the nodes whose label contains this, together with the branches that
   * lead to them — which are opened for as long as the term stands.
   */
  filter?: string
  /** Draws the guide lines that connect a child to its parent. */
  showLines?: boolean
  /** How far one level sits from the next, in pixels. */
  indent?: number
  size?: TreeSize
  /** Shown when there is nothing to draw. */
  emptyText?: string
  /** What the drag handle is called, before the name of the node it holds. */
  dragLabel?: string
  /** Accessible name for the tree. */
  ariaLabel?: string
}

export interface TreeEmits<T = TreeNode> {
  /** A node was clicked — before anything was decided about selection. */
  'node-click': [node: T, event: MouseEvent]
  /** The selected node changed. */
  select: [node: T | null, key: TreeKey | null]
  /** A checkbox changed. The keys are every checked node, branches included. */
  check: [keys: TreeKey[], detail: { node: T; checked: boolean }]
  expand: [node: T]
  collapse: [node: T]
  /** A node was moved. The tree has already been rearranged. */
  drop: [event: TreeDropEvent<T>]
}
