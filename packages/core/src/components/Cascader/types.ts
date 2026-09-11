import type { ControlSize, ControlStatus } from '../../composables/useFormField'

export type CascaderValue = string | number

export interface CascaderOption {
  label: string
  value: CascaderValue
  children?: CascaderOption[]
  disabled?: boolean
  /**
   * Says this node has nothing under it. Only needed with `lazy`, where an empty
   * `children` cannot tell "not loaded yet" from "nothing there".
   */
  leaf?: boolean
}

/** The whole path by default (`['content', 'news']`), or the last value with `emit-path: false`. */
export type CascaderModelValue = CascaderValue[] | CascaderValue | null

/** Loads one level. Called with `null` for the root, otherwise with the node opened. */
export type CascaderLoader = (
  option: CascaderOption | null,
  path: CascaderOption[],
) => CascaderOption[] | Promise<CascaderOption[]>

export interface CascaderProps {
  /** The tree. Leave empty and use `lazy` to build it from the backend instead. */
  options?: CascaderOption[]
  /** Whether a child list opens on click or on hover. */
  expandTrigger?: 'click' | 'hover'
  /** Lets a parent be chosen, not only a leaf. */
  checkStrictly?: boolean
  /** The model is the path of values; turn it off to store the last value alone. */
  emitPath?: boolean
  /** Shows the whole path in the field; off shows the chosen label alone. */
  showAllLevels?: boolean
  /** What goes between the levels in the field. */
  separator?: string
  /** Levels are fetched as they open. Requires `load`. */
  lazy?: boolean
  /** Fetches one level. Required when `lazy`. */
  load?: CascaderLoader
  placeholder?: string
  emptyText?: string
  clearable?: boolean
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

export interface CascaderEmits {
  change: [value: CascaderModelValue]
  /** A level was opened — the path of options leading to it. */
  expand: [path: CascaderOption[]]
  clear: []
  open: []
  close: []
}
