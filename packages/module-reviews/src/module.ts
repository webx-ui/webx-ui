import { categoryRoutes, type AdminModule, type CategoriesOptions } from '@webx-ui/module-admin'
import ReviewsPage from './ReviewsPage.vue'

export interface ReviewsOptions {
  /** Where the reviews live inside the panel. The categories sit under it. */
  path?: string
}

/**
 * The categories of the reviews as the panel's shared category screens see them. Exported so
 * that a panel mounting the screens somewhere of its own does not have to repeat the words.
 *
 * No words about addresses: a category here has none (decision 5) — it is a button of the filter
 * on the site, and the shared list leaves the address line out when the server says so.
 */
export function reviewCategoriesOptions(path = '/reviews'): CategoriesOptions {
  return {
    api: 'reviews/categories',
    path: `${path}/categories`,
    name: 'webx.reviews.categories',
    module: 'review-categories',
    screen: 'reviews.category-form',
    manage: 'reviews.categories.manage',
    count: 'reviews_count',
    // The list narrowed to one category is also where its own order is dragged.
    items: (id) => ({ path, query: { category: String(id) } }),
    words: {
      new: 'webx-reviews::category.new',
      empty: 'webx-reviews::category.empty',
      'empty-help': 'webx-reviews::category.empty-help',
      order: 'webx-reviews::category.order',
      hidden: 'webx-reviews::category.hidden',
      count: 'webx-reviews::category.reviews',
      'show-items': 'webx-reviews::category.show-reviews',
      'delete-blocked': 'webx-reviews::category.delete-blocked',
      'delete-text': 'webx-reviews::category.delete-text',
      deleted: 'webx-reviews::category.deleted',
      saved: 'webx-reviews::category.saved',
      'field-title': 'webx-reviews::category.field-title',
    },
  }
}

/**
 * The reviews as sections of the panel: reviews, and their categories (§4.6).
 *
 * Two modules rather than one, because the navigation is one entry per module; the server puts
 * both in the `reviews` group, which is what draws them under one heading. A section whose server
 * half is not installed never appears — the entry is built from the manifest.
 */
export function reviews(options: ReviewsOptions = {}): AdminModule[] {
  const path = options.path ?? '/reviews'

  return [
    {
      id: 'reviews',
      path,
      // One route: the open review is in the address (`?review=`), beside the list it was opened
      // from, so a link to it is a link to both.
      routes: [{ path, name: 'webx.reviews', component: ReviewsPage, props: { base: path } }],
    },
    {
      id: 'review-categories',
      path: `${path}/categories`,
      routes: categoryRoutes(reviewCategoriesOptions(path)),
    },
  ]
}
