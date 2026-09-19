import type { Paginated } from '@webx-ui/core'

/**
 * Never published · waiting for its day · on the site · on the site with edits waiting · taken
 * off it (§10).
 *
 * The last two are the pair worth keeping apart. "Taken off" and "draft" both mean the article
 * is not on the site, and only its history tells them apart — "draft" on something that was
 * live at breakfast is a lie.
 */
export type ArticleStatus = 'draft' | 'scheduled' | 'published' | 'modified' | 'unpublished'

/**
 * A rubric or a tag as a row names one, and the same shape a filter is set from — which is why
 * the author of an article is not one of these but an {@link ArticleAuthor}. A person has a
 * name; a rubric has a title and an address, and calling them the same thing would cost the
 * difference the first time somebody wanted the address.
 */
export interface BlogNamed {
  id: number
  title: string
  /** A rubric and a tag have one; a filter option does not carry it. */
  slug?: string
}

export interface ArticleAuthor {
  id: number
  name: string
}

/**
 * A cover, with the two addresses `module-media` distinguishes.
 *
 * `url` is what the picture is shown by; `source` — the same bytes off the panel's own origin —
 * is what the image editor needs. Neither is stored: both are worked out from the file on every
 * read, because a signed link expires and a cropped picture keeps its key.
 */
export interface ArticleCover {
  id: number
  path: string
  url: string
  thumb: string | null
}

/**
 * One article as the section lists it.
 *
 * The title is the draft's and the address is the registry's, which is why a renamed article
 * shows a new title beside its old address: that is what the site is serving until it is
 * published.
 */
export interface ArticleRow {
  id: number
  title: string
  slug: string
  lead: string
  /** `null` — this article names no address in the language the panel is open in (§9). */
  path: string | null
  url: string | null
  status: ArticleStatus
  pinned: boolean
  published_at: string | null
  updated_at: string | null
  deleted_at: string | null
  author: ArticleAuthor | null
  cover: ArticleCover | null
  /** In the order they were dragged into: the first is the main one (§2.6). */
  rubrics: BlogNamed[]
  tags: BlogNamed[]
  /** What this article was when it was read, so a save can be refused rather than written over. */
  revision: string
  // A row of `WxTable`, which reads its cells by name; without this the table falls back on
  // its own `TableRow` and every slot hands back `unknown`.
  [key: string]: unknown
}

/** What the dropdowns over the list can be set to — it travels with the rows (§10). */
export interface ArticleFilters {
  rubrics: BlogNamed[]
  tags: BlogNamed[]
  authors: BlogNamed[]
}

/**
 * A page of articles, with the filters beside it.
 *
 * Flat, the way `WxTable` reads a paginator: the server answers with `meta` around the numbers,
 * Laravel's own shape, and the API client spreads it before the table ever sees it.
 */
export interface ArticlesPage extends Paginated<ArticleRow> {
  filters: ArticleFilters
}

export interface ArticleQuery {
  /** Title or address, in any language the site has. */
  q?: string
  rubric?: number | null
  tag?: number | null
  author?: number | null
  status?: ArticleStatus | ''
  /** `-published_at` for newest first; left out, the list is pinned first and then by date. */
  sort?: string | null
  /** What was deleted. The only way back to an article in the bin is through this list. */
  trashed?: boolean
  page?: number
  per_page?: number
}

/** What a save carries: the article's own fields, the pivots, and what was read. */
export interface ArticleInput {
  title?: string
  slug?: string
  lead?: string
  cover_id?: number | null
  author_id?: number | null
  pinned?: boolean
  /** In the order they are to be kept; the first rubric is the main one. */
  rubrics?: number[]
  tags?: number[]
  related?: number[]
  revision?: string
}
