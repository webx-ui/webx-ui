import { ref, type Ref } from 'vue'
import type { AdminContext } from '@webx-ui/module-admin'
import { createCatalogApi } from './api'
import type { CategoryNode, FacetInfo, FacetKind } from './types'

/**
 * What several screens of the catalogue read and nobody should ask for twice: the tree of
 * categories and the registry of facets.
 *
 * The tree is read by the list's filter, by both category pickers of the product form and by the
 * tree screen; one panel, one copy, dropped whenever a category is created, saved, moved, deleted
 * or restored — the next reader asks again. Kept per panel rather than per module instance, so a
 * test that builds a panel of its own starts from nothing.
 */
interface Shelf<T> {
  value: Ref<T | null>
  pending: Promise<T> | null
}

interface CatalogStore {
  tree: Shelf<CategoryNode[]>
  facets: Shelf<FacetInfo[]>
}

const stores = new WeakMap<AdminContext, CatalogStore>()

function storeOf(admin: AdminContext): CatalogStore {
  let store = stores.get(admin)

  if (!store) {
    store = {
      tree: { value: ref(null), pending: null },
      facets: { value: ref(null), pending: null },
    }
    stores.set(admin, store)
  }

  return store
}

function fetchInto<T>(shelf: Shelf<T>, fetch: () => Promise<T>, force: boolean): Promise<T> {
  if (!force && shelf.value.value !== null) return Promise.resolve(shelf.value.value)
  if (!force && shelf.pending) return shelf.pending

  const pending = fetch()
    .then((value) => {
      if (shelf.pending === pending) shelf.value.value = value

      return value
    })
    .finally(() => {
      if (shelf.pending === pending) shelf.pending = null
    })

  shelf.pending = pending

  return pending
}

export interface CategoryTree {
  /** `null` until the first answer. */
  nodes: Ref<CategoryNode[] | null>
  load(force?: boolean): Promise<CategoryNode[]>
  /** Put in what the server answered with — a move answers with the whole tree. */
  take(nodes: CategoryNode[]): void
  /** Forget it; the next reader asks again. */
  drop(): void
}

export function useCategoryTree(admin: AdminContext): CategoryTree {
  const shelf = storeOf(admin).tree
  const api = createCatalogApi(admin)

  return {
    nodes: shelf.value,
    load: (force = false) => fetchInto(shelf, () => api.categories(), force),
    take: (nodes) => {
      shelf.pending = null
      shelf.value.value = nodes
    },
    drop: () => {
      shelf.pending = null
      shelf.value.value = null
    },
  }
}

export interface FacetRegistry {
  facets: Ref<FacetInfo[] | null>
  load(): Promise<FacetInfo[]>
}

/**
 * The facets every category can show (§7.1). Their kind is lowercased on the way in: the server
 * names the enum's cases, and a panel comparing with `'range'` should not care how they are cased.
 */
export function useFacetRegistry(admin: AdminContext): FacetRegistry {
  const shelf = storeOf(admin).facets
  const api = createCatalogApi(admin)

  return {
    facets: shelf.value,
    load: () =>
      fetchInto(
        shelf,
        () =>
          api.facets().then((facets) =>
            facets.map((facet) => ({
              ...facet,
              kind: String(facet.kind).toLowerCase() as FacetKind,
            })),
          ),
        false,
      ),
  }
}

/** Every node of the tree, depth first, as the server ordered them. */
export function flatten(nodes: CategoryNode[]): CategoryNode[] {
  return nodes.flatMap((node) => [node, ...flatten(node.children ?? [])])
}

/** A node and the chain of its parents, the node first. Empty when it is not in the tree. */
export function ancestry(nodes: CategoryNode[], id: number): CategoryNode[] {
  const byId = new Map(flatten(nodes).map((node) => [node.id, node]))
  const chain: CategoryNode[] = []
  let current = byId.get(id)

  while (current) {
    chain.push(current)
    current = current.parent_id === null ? undefined : byId.get(current.parent_id)
  }

  return chain
}
