import type { LocalizedValue } from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import type { RouteLocationRaw } from 'vue-router'

/**
 * One category as its list draws it — the same row for every module, because the server half is
 * one controller (`WebxUi\Admin\Categories\Http\CategoryResource`).
 *
 * How many items are in it travels under the module's own word (`articles_count`), so it is not
 * a field here: read it with {@link CategoriesOptions.count}.
 */
export interface CategoryRow {
  id: number
  /** What to show: the title of this language, its address, or its number — in that order. */
  name: string
  title: LocalizedValue | null
  slug: LocalizedValue | null
  /** `null` — no address in the language the panel is open in, or categories without addresses. */
  path: string | null
  url: string | null
  is_visible: boolean
  position: number
  deleted_at: string | null
}

export interface CategoriesPayload {
  data: CategoryRow[]
  /** Where the module's addresses start; `null` for categories without an address. */
  prefix: string | null
}

/** One category as its editor opens it: the row, the values of its screen and the prefix. */
export interface CategoryDetail {
  category: CategoryRow
  values: ScreenModel
  prefix: string | null
}

/**
 * The words the shared screens say, by what they are for.
 *
 * Each is a full key — `webx-blog::rubric.new` — and every one has a default under
 * `webx-admin::categories.*` that calls the thing a category. A module overrides the ones that
 * name it: the blog says "rubric" and "articles", and keeps the panel's "Edit" and "Cancel".
 */
export type CategoryWord =
  | 'new'
  | 'empty'
  | 'empty-help'
  | 'order'
  | 'hidden'
  | 'no-address'
  | 'count'
  | 'show-items'
  | 'edit'
  | 'open-on-site'
  | 'delete'
  | 'delete-blocked'
  | 'delete-title'
  | 'delete-text'
  | 'deleted'
  | 'cancel'
  | 'create'
  | 'save'
  | 'saved'
  | 'save-failed'
  | 'reorder-failed'
  | 'field-title'
  | 'field-slug'
  | 'address-moving'
  | 'untitled'
  | 'trail'
  | 'leave-title'
  | 'leave-text'
  | 'leave'

/**
 * What a module says about its categories, once, for the list, the dialog and the editor.
 *
 * The server half reads the same things off `CategoryKind`; this is its front-end twin, and
 * {@link categoryRoutes} is how a module mounts both screens from one description.
 */
export interface CategoriesOptions {
  /** Under the panel's API path: `blog/rubrics`. */
  api: string
  /** The list in the panel: `/blog/rubrics`. One category is edited at `{path}/{id}`. */
  path: string
  /** The route names: `{name}` for the list, `{name}.edit` for the editor. */
  name: string
  /** The module the list is — its title in the manifest is the heading of both screens. */
  module: string
  /** The described screen of one category: `blog.category-form`. */
  screen: string
  /** The permission everything that writes asks for. */
  manage: string
  /** The key the number of items travels under: `articles_count`. */
  count: string
  /** Where the items of one category are listed, for the row's menu. Left out, no such item. */
  items?: (id: number) => RouteLocationRaw
  /** The module's own words over the panel's — see {@link CategoryWord}. */
  words?: Partial<Record<CategoryWord, string>>
}
