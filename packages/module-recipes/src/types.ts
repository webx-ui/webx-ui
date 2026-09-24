import type { ScreenModel } from '@webx-ui/schema'

/**
 * Never published · on the site · on the site with edits waiting · taken off it — the same four
 * a service has (§5.10). "Taken off" and "draft" both mean it is not on the site, and only its
 * history tells them apart.
 */
export type RecipeStatus = 'draft' | 'published' | 'modified' | 'unpublished'

/** A category or a nutrient as a row of the list names one. The first category is the main one. */
export interface RecipeTermRef {
  id: number
  title: string
}

/**
 * One recipe as the section lists it: the draft's title beside the registry's address, which is
 * why a renamed recipe shows its new name at its old address until it is published.
 */
export interface RecipeRow {
  id: number
  title: string
  slug: string
  /** `null` — no address in the language the panel is open in. */
  path: string | null
  url: string | null
  /** The first picture of the gallery, which is what every card of the site shows. */
  cover: { thumb: string | null } | null
  /** `total_minutes`: how long it takes, all of it. */
  minutes: number | null
  status: RecipeStatus
  /** Its place in the one order recipes have (decision 4). */
  position: number
  /** In the order they were chosen in: the first is the main one. */
  categories: RecipeTermRef[]
  published_at: string | null
  updated_at: string | null
  deleted_at: string | null
  /** What this recipe was when it was read, so a save can be refused rather than written over. */
  revision?: string
}

/**
 * The whole list — no pages, because the list is where recipes are put in order and a drag cannot
 * cross a page boundary. The filters travel with it; `services` is `null` on a site without the
 * services module, and then there is no such filter.
 */
export interface RecipesList {
  data: RecipeRow[]
  filters: {
    categories: RecipeTermRef[]
    nutrients: RecipeTermRef[]
    services: RecipeTermRef[] | null
  }
}

export interface RecipeQuery {
  /** Title or address, in any language the site has. */
  q?: string
  category?: number | null
  nutrient?: number | null
  service?: number | null
  status?: RecipeStatus | ''
  /** What was deleted. The only way back to a recipe in the bin is through this list. */
  trashed?: boolean
}

export interface RecipeInput {
  title: string
  slug?: string
}

/** The values of `recipes.form` keyed by field name, and the revision they were read at. */
export interface RecipeSave {
  values: ScreenModel
  revision?: string
}

/** One recipe as its editor opens it. */
export interface RecipeDetail {
  recipe: RecipeRow
  values: ScreenModel
  revision: string
  /** The first segment of every recipe address (`webx-recipes.prefix`); never empty. */
  prefix: string
  preview_url: string | null
}

/** A 409: somebody wrote in between. `data` is the recipe as it now is. */
export interface RecipeConflict {
  message: string
  data: RecipeDetail
}

/** One publication in the history. */
export interface RecipeVersion {
  number: number
  created_at: string | null
  author: string | null
  source: 'panel' | 'mcp' | 'import' | string
  comment: string | null
  is_pinned: boolean
}
