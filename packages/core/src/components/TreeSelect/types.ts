import type { ControlSize, ControlStatus } from '../../composables/useFormField'
import type { TreeKey, TreeNode } from '../Tree/types'

export type { TreeKey, TreeNode }

/** One key, several of them with `multiple`, or nothing chosen. */
export type TreeSelectValue = TreeKey | TreeKey[] | null

export interface TreeSelectProps<T = TreeNode> {
  /** The tree to choose from — the same shape `WxTree` takes. */
  nodes?: T[]
  /** Adds checkboxes and turns the model into an array. */
  multiple?: boolean
  /**
   * With `multiple`, a tick stays where it was made instead of running down into the
   * children and up into the parent. The model then holds exactly what was ticked,
   * which is usually what a form wants to send.
   */
  checkStrictly?: boolean
  /** Field holding the identity of a node. */
  nodeKey?: string
  /** Field holding the text of a node. */
  labelKey?: string
  /** Field holding the array of children. */
  childrenKey?: string
  /** Field that, when true, makes a node unchoosable. */
  disabledKey?: string
  /** Field that says a node has no children to fetch. Only read when `lazy`. */
  leafKey?: string
  /** Open every branch when the panel is first built. */
  defaultExpandAll?: boolean
  /** Children are fetched when a branch first opens. Requires `load`. */
  lazy?: boolean
  /** Fetches the children of one node. */
  load?: (node: T) => T[] | Promise<T[]>
  /** Adds the search field above the tree. */
  filterable?: boolean
  filterPlaceholder?: string
  /**
   * Shows the whole path in the field — `Engine / Pistons` — rather than the label
   * alone. Worth it wherever the same name appears under two parents.
   */
  showPath?: boolean
  /** What goes between the steps of a path. */
  separator?: string
  /** How tall the tree inside the panel may get before it scrolls. */
  panelHeight?: number | string
  /** Shows a button that empties the selection. */
  clearable?: boolean
  placeholder?: string
  /** Shown in the panel when there is nothing to choose from. */
  emptyText?: string
  /** Render the panel in a portal so it escapes `overflow: hidden`. */
  teleport?: boolean
  disabled?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  name?: string
  /** Accessible label used when there is no visible `<label>`. */
  ariaLabel?: string
}

export interface TreeSelectEmits<T = TreeNode> {
  /** The selection changed. The nodes come with it, since an id alone rarely is it. */
  change: [value: TreeSelectValue, nodes: T[]]
  clear: []
  open: []
  close: []
}
