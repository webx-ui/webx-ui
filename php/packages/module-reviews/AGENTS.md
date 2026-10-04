# webx-ui/module-reviews

Reviews of the site: a photo, a name, a job title, a rating and a text, in flat categories with
two orders. A review has no page and no address of its own — it reaches the site in a block (one,
a grid, a slider, a marquee) or through `reviews()` in a template; the page it stands on brings
the address, the SEO and the menu entry. The section «Reviews» of the panel and the MCP tools
`reviews_*` edit it. The blocks are `webx-ui/module-blocks`, the photo `webx-ui/module-media`, the
categories, screens and bin `webx-ui/module-admin` — read their guides for questions about those.

## What it owns

- **Tables** `reviews` (`WebxUi\Reviews\Models\Review`), `review_categories`
  (`WebxUi\Reviews\Models\ReviewCategory`) and the link `review_category_review`. `name`,
  `job_title` and `text` are translatable; a review is shown in a language only when it has a
  text there. `position` is the order of the whole list, `item_position` the order inside a
  category.
- **No public route.** The collection source `reviews` and the offered block type `reviews`
  (`resources/blocks/reviews.json`) are how a review reaches a page.
- **Helper** `reviews()` — declared only when the site has no function of that name.
- **Panel screens** `reviews.form` (nodes `author`, `photo`, `name`, `job-title`, `profile-url`,
  `content`, `text`, `rating`, `reviewed-on`, `settings`, `published`, `categories`,
  `project-fields`) and `reviews.category-form` (`naming`, `title`, `is-visible`,
  `project-fields`).
- **API** under `/api/cms/reviews` and `/api/cms/reviews/categories`; permissions `reviews.view`,
  `reviews.manage`, `reviews.categories.manage`.
- **MCP** tools `reviews_list`, `reviews_get`, `reviews_create`, `reviews_update`,
  `reviews_delete`, `reviews_reorder`, and `review_categories_list`, `review_categories_create`,
  `review_categories_update`, `review_categories_delete`, `review_categories_reorder`; resource
  `reviews://catalog`. Scopes `reviews:read`, `reviews:write`, `review-categories:read`,
  `review-categories:write`.
- Also registered: the panel group `reviews`, demo content (`resources/demo`).

## Change it without forking

| You want                              | Do this                                                                                      |
| ------------------------------------- | -------------------------------------------------------------------------------------------- |
| The Reviews block on the site         | `php artisan webx:blocks:offered --install --module=reviews` (a type already there is kept)  |
| Different markup of the block         | edit the site's copy of the block type `reviews` in the panel — not the JSON in the package  |
| Reviews in a template of the site     | `reviews()->in($categories)->take(6)`; `only()`, `except()`, `locale()`, `categories()`      |
| A field of the site's own (city, ...) | a patch: `Screens::extend('reviews.form', [...])` adding into `project-fields`; read `extra` |
| A field on a category                 | the same patch on `reviews.category-form`, card `project-fields`                             |
| Other words in the panel              | `php artisan vendor:publish --tag=webx-reviews-lang`                                         |

`config/webx-reviews.php` (tag `webx-reviews-config`) is empty on purpose: there is nothing to set
yet. A project field lives in the `extra` column — `$review->extra('city')`, and `fields.city` on
the card. A patch whose target node is gone throws when the screen is first built.

## Do not

- Do not edit anything in `vendor/webx-ui/module-reviews`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
- Do not add a migration for a project field: the patch on the screen and the `extra` column are
  the place, and a column of your own is never read by the form, the card or MCP.
- Do not add a route or a page for one review: a review has no address by design. Put a reviews
  block on a page of `webx-ui/module-pages` instead.
- Do not print schema.org markup for reviews: the module prints none on purpose, since search
  engines ignore self-serving review stars and the markup only carries risk.
- Do not pass a category as a slug to `reviews()->in()`: categories have no slugs, and any string
  that is not an id is a filter nothing passes. Pass ids or models.
- Do not delete rows with SQL: deleting a review sends it to the bin, where
  `POST reviews/{id}/restore` (or the bin in the panel) brings it back. A raw delete skips the bin
  and takes the review out of every category for good — use `reviews_delete` or the panel.

## Check your work

- `php artisan webx:doctor` — among other things, whose `reviews()` the site calls.
- Open the page with the block on the site: a review without a text in that language, or
  unpublished, is not shown there — by design.
- With MCP: read `reviews://catalog` first (each review with `visible_in` and `written_in`), then
  `reviews_get`; every tool that changes something takes `dry_run: true` first.

## Read more

- [README.md](README.md) in this directory — the card, `reviews()`, the block, the panel API.
- Guide: https://webx-ui.github.io/webx-ui/guide/reviews
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_REVIEWS.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
