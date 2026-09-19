import type { TypeRegistry } from '@webx-ui/schema'
import ListScreen from './ListScreen.vue'
import RichTextField from './RichTextField.vue'

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
 *
 * `wx-rich-text` is here rather than in `@webx-ui/schema` because it is not the editor that
 * makes it a panel field: it is the panel's words on its toolbar and the panel's library behind
 * its image button, and the schema package knows about neither.
 */
export const adminTypes: TypeRegistry = {
  'wx-list': { component: ListScreen, kind: 'layout', labelProp: 'title' },
  // Full width: an editor shares a row with nothing, and a form of two columns would give it
  // half a line to write a page of text in.
  'wx-rich-text': { component: RichTextField, kind: 'field', wide: true },
}
