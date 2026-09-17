import type { TypeRegistry } from '@webx-ui/schema'
import ListScreen from './ListScreen.vue'

/**
 * Node types the panel itself brings, under every module's and every project's.
 *
 * `wx-list` is the frame a section's list is drawn in — the heading, the one action beside it,
 * the views as tabs, the card under them. It is a node type and not five copies of the same
 * markup because that is what stops the next section from inventing a sixth shape: a list
 * screen is described, and what a module supplies is the rows inside it (§19 of the visual
 * spec, §10.3 for the tabs).
 *
 * The rows themselves are still a module's own type — `wx-pages-table`, `wx-media` — because
 * only the module knows what it is listing and where it asks for it.
 */
export const adminTypes: TypeRegistry = {
  'wx-list': { component: ListScreen, kind: 'layout', labelProp: 'title' },
}
