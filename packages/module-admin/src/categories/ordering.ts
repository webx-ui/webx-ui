import { computed, type ComputedRef } from 'vue'
import { useAdmin } from '../admin'
import { useTranslate } from '../i18n'
import { reorderItems } from './api'

/**
 * What the list of a module's records looks like right now, as far as its order is concerned.
 */
export interface ItemOrderState {
  /** What is typed in the search box. */
  q?: string | null
  /** The one category the list is narrowed to, if it is. */
  category?: number | null
  /** Whether any filter other than the category is on — status, author, the bin. */
  filtered?: boolean
}

/**
 * Which order a drag would write (decision 5 of the services spec).
 *
 * - `all` — the whole list is on screen, and dragging moves the common order;
 * - `category` — one category and nothing else, and dragging moves that category's own order;
 * - `locked` — a search or another filter is on, and the rows on screen are a selection: the
 *   gaps between them are records nobody can see, so a drag here would move them blind.
 */
export type ItemOrderMode = 'all' | 'category' | 'locked'

export function itemOrderMode(state: ItemOrderState): ItemOrderMode {
  if ((state.q ?? '').trim() !== '' || state.filtered) return 'locked'

  return state.category == null ? 'all' : 'category'
}

export interface ItemOrder {
  mode: ComputedRef<ItemOrderMode>
  /** Whether the handles are drawn at all. */
  sortable: ComputedRef<boolean>
  /** One line under the list: what a drag does here, or why there is nothing to drag. */
  hint: ComputedRef<string>
  /** Write the order the rows are in on screen — the whole list, or the category's. */
  move(ids: number[]): Promise<void>
}

/**
 * The order of a module's records, dragged in the list the editor is looking at.
 *
 * A composable rather than a component, because the list it belongs to is the module's own —
 * a table, a sortable list, cards — and what is shared is the rule about which order a drag
 * writes and the sentence that says so. `path` is the list's API (`services/items`), served by
 * `CategoryRoutes::items()`. A module whose records are ordered by something else (the blog, by
 * date) does not call it, and its list has no handles.
 */
export function useItemOrder(path: string, state: () => ItemOrderState): ItemOrder {
  const admin = useAdmin()
  const t = useTranslate('webx-admin')

  const mode = computed(() => itemOrderMode(state()))

  return {
    mode,
    sortable: computed(() => mode.value !== 'locked'),
    hint: computed(() => t(`categories.order-${mode.value}`)),
    move: (ids) =>
      reorderItems(admin, path, ids, mode.value === 'category' ? (state().category ?? null) : null),
  }
}
