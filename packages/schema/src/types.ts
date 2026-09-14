import type { Component } from 'vue'

/**
 * A screen is a tree of nodes. Every node has a stable `id` — the address patches use —
 * and a `type` the registry resolves to a component. Nothing else is interpreted:
 * whatever a component needs travels in `props` as-is.
 *
 * The keys are closed: a node carries exactly these and nothing more, so a typo is a
 * validation error rather than a silently empty tab.
 */
export interface ScreenNode {
  /** Unique within the screen. Renaming one is a breaking change for every patch. */
  id: string
  /** Registry key: the full component name (`wx-input`), or whatever a project registered. */
  type: string
  /**
   * For fields: the key in the model. A literal string — dots are part of the key,
   * not a path. Nesting is what `wx-repeater` is for.
   */
  name?: string
  label?: string
  /** Hint under a field. */
  help?: string
  /** The field is edited per content language; the value is a record keyed by locale. */
  localized?: boolean
  /** Passed to the component untouched. */
  props?: Record<string, unknown>
  children?: ScreenNode[]
  /** Named slot of the parent to land in; the parent's default slot otherwise. */
  slot?: string | null
  /** `false` hides the node; a condition is evaluated against the model. */
  visible?: boolean | VisibilityCondition
  /** Permission required to render the node, e.g. `settings.manage`. */
  can?: string | null
}

/** The file a module ships and the answer `GET /api/cms/screens/<name>` gives. */
export interface Screen {
  $schema?: string
  /** `<module>.<screen>`, e.g. `settings.index`. */
  screen: string
  /** What the values belong to — informational for now. */
  model?: string
  root: ScreenNode[]
}

/**
 * "Show this when that field holds this value." Evaluated on the client against the
 * current model; the server never sees it.
 */
export type VisibilityCondition =
  | { when: string; is: unknown }
  | { when: string; in: unknown[] }
  | { when: string; not: unknown }
  | { all: VisibilityCondition[] }
  | { any: VisibilityCondition[] }

/** Where an added or moved node lands among its siblings. `last` is the default. */
export type PatchPosition = 'first' | 'last' | `before:${string}` | `after:${string}`

export type PatchOperation =
  | { op: 'add'; target: string; node: ScreenNode; position?: PatchPosition }
  | { op: 'remove'; target: string }
  | { op: 'replace'; target: string; node: ScreenNode }
  | { op: 'move'; target: string; position?: PatchPosition; to?: string }
  | ({ op: 'set'; target: string } & Partial<Omit<ScreenNode, 'id'>>)

export type Patch = PatchOperation[]

/** An operation that could not be applied. The tree is left as it was before it. */
export interface PatchError {
  /** Index of the operation in the patch. */
  index: number
  op: PatchOperation
  message: string
}

/** A problem found by {@link validateScreen}, with the path of the node it is about. */
export interface ScreenError {
  path: string
  message: string
}

export type NodeKind = 'layout' | 'field' | 'display'

/** How the renderer treats one type. */
export interface TypeEntry {
  component: Component
  /**
   * `layout` gets its children in slots and never touches the model; `field` is bound
   * to the model by `name` and wrapped in a form item; `display` only draws.
   */
  kind: NodeKind
  /** Slot that receives children without a `slot` of their own. Default: `default`. */
  childrenSlot?: string
  /**
   * Prop the node's `label` goes to (`title` on a card). For a `display` type without
   * one the label becomes the default slot content; a `field` shows it in its form item.
   */
  labelProp?: string
  /** Props derived from the node itself, beyond `props` — a tab's `value`, say. */
  bind?: (node: ScreenNode) => Record<string, unknown>
}

export type TypeRegistry = Record<string, TypeEntry>

/**
 * Turns `trans::<namespace>::<key>` into words. Receives what follows the marker —
 * `<namespace>::<key>` — and returns the translation, or the key when there is none.
 */
export type Translate = (key: string) => string

/** The values a screen shows and edits, keyed by node `name`. */
export type ScreenModel = Record<string, unknown>

/** Field name -> validation messages, as produced by Laravel's 422 responses. */
export type ValidationErrors = Record<string, string[]>

/** Normalised, backend-agnostic page of records. */
export interface Paginated<T> {
  items: T[]
  page: number
  lastPage: number
  perPage: number
  total: number
}

export interface ListQuery {
  page?: number
  perPage?: number
  sort?: { field: string; direction: 'asc' | 'desc' }[]
  filters?: Record<string, unknown>
  search?: string
}

/**
 * Everything a list screen will need from a backend. Implemented per backend, e.g. by
 * `@webx-ui/adapter-laravel`; nothing in this package calls it yet.
 */
export interface DataAdapter {
  list<T>(resource: string, query?: ListQuery): Promise<Paginated<T>>
  get<T>(resource: string, id: string | number): Promise<T>
  create<T>(resource: string, payload: Record<string, unknown>): Promise<T>
  update<T>(resource: string, id: string | number, payload: Record<string, unknown>): Promise<T>
  remove(resource: string, id: string | number): Promise<void>
}
