/**
 * Backend-agnostic description of an admin UI.
 *
 * A screen is a tree of {@link SchemaNode}s. Rendering is done by `@webx-ui/schema`'s renderer
 * (not implemented yet) against a {@link ComponentRegistry} and an {@link ActionRegistry}; data
 * access goes through a {@link DataAdapter} so that Laravel, or anything else, stays behind
 * one interface.
 */
export interface SchemaNode {
  /** Registry key of the component to render, e.g. `"card"` or `"input"`. */
  type: string
  /** Props passed to the component as-is. */
  props?: Record<string, unknown>
  /** Child nodes, or plain text for leaf nodes. */
  children?: SchemaNode[] | string
  /** Event name -> action descriptor, resolved through the action registry. */
  on?: Record<string, ActionDescriptor>
  /** Expression or boolean controlling whether the node renders. */
  visible?: boolean | string
  /** Stable key for list rendering. */
  key?: string
}

export interface ActionDescriptor {
  /** Registry key of the action, e.g. `"submit"` or `"navigate"`. */
  type: string
  payload?: Record<string, unknown>
}

/** Maps schema `type` values to concrete Vue components. */
export type ComponentRegistry = Record<string, unknown>

/** Maps action `type` values to handlers. */
export type ActionRegistry = Record<string, ActionHandler>

export type ActionHandler = (context: ActionContext) => void | Promise<void>

export interface ActionContext {
  payload: Record<string, unknown>
  event?: unknown
  node: SchemaNode
}

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

/** Field name -> validation messages, as produced by Laravel's 422 responses. */
export type ValidationErrors = Record<string, string[]>

/**
 * Everything the renderer needs from a backend. Implemented per backend, e.g. by
 * `@webx-ui/adapter-laravel`.
 */
export interface DataAdapter {
  list<T>(resource: string, query?: ListQuery): Promise<Paginated<T>>
  get<T>(resource: string, id: string | number): Promise<T>
  create<T>(resource: string, payload: Record<string, unknown>): Promise<T>
  update<T>(resource: string, id: string | number, payload: Record<string, unknown>): Promise<T>
  remove(resource: string, id: string | number): Promise<void>
}
