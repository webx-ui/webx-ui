import type { TypeRegistry } from '@webx-ui/schema'
import CategoriesField from './categories/CategoriesField.vue'
import LinkField from './LinkField.vue'
import ListScreen from './ListScreen.vue'
import RichTextField from './RichTextField.vue'
import SlugField from './SlugField.vue'

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
 *
 * `wx-link` is here for the same reason twice over: what it can point at is whatever the installed
 * content modules registered, and the only thing that knows what those are is the panel's own
 * backend.
 *
 * `wx-categories` files a record under a module's categories (`categories/`). `wx-slug` is the
 * address of any record with the module's prefix in front of it — a service, a category; the
 * editor hosting the screen hands it the prefix (`provideRecordAddress`). `wx-category-slug` is
 * the same field under the name the category screens were first described with.
 */
export const adminTypes: TypeRegistry = {
  'wx-list': { component: ListScreen, kind: 'layout', labelProp: 'title' },
  // Full width: an editor shares a row with nothing, and a form of two columns would give it
  // half a line to write a page of text in.
  'wx-rich-text': { component: RichTextField, kind: 'field', wide: true },
  // Wide as well: a row of the picker is a segmented switch, a section and a search box, and half
  // a form's width leaves the search box too narrow to read a page title in.
  'wx-link': { component: LinkField, kind: 'field', wide: true },
  'wx-categories': { component: CategoriesField, kind: 'field' },
  'wx-slug': { component: SlugField, kind: 'field' },
  'wx-category-slug': { component: SlugField, kind: 'field' },
}
