export type {
  ActionContext,
  ActionDescriptor,
  ActionHandler,
  ActionRegistry,
  ComponentRegistry,
  DataAdapter,
  ListQuery,
  Paginated,
  SchemaNode,
  ValidationErrors,
} from './types'

/**
 * Placeholder registry helper. The renderer itself lands in a later milestone —
 * for now the package only fixes the contracts so adapters can be written against them.
 */
export function createComponentRegistry<T extends Record<string, unknown>>(components: T): T {
  return { ...components }
}
