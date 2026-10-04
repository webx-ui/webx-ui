# webx-ui/module-catalog-landings

Landing pages for the catalogue. A landing is a category's list (or the whole catalogue's) with a
filter set chosen in advance, and it has its own address, SEO card and texts: `/apple-laptops/`
instead of `/laptops/brand_apple/`. It renders through the category's template with the filter
already applied. It is not a page built from blocks; for that, use `webx-ui/module-pages`. This
package is a satellite of `webx-ui/module-catalog`, and its agent tools are served under the
catalogue's name and scopes. For the filter, its facets and the storefront views, read the core's
`AGENTS.md`. Most facets come from the property, brand, label and stock satellites.

## What it owns

- **Tables** `catalog_landings` (`WebxUi\CatalogLandings\Models\Landing`),
  `catalog_landing_products` (recommended products) and `catalog_landing_runs` (bulk generation).
  `name`, `slug`, `h1`, `text_above` and `text_below` are translatable. The set is stored as
  value ids, so renaming a value does not affect it.
- **Address type** `catalog.landing`: `/{slug}/` from the root, in the same space as categories,
  with `OnConflict::Fail`. A new slug leaves the old one as a 301. Every filter link that selects
  a landing's set points at the landing. The category's own spelling of that set answers with a
  301 to the landing.
- **Views** `webx-catalog-landings::parts.listing-top` («Collections»), `parts.listing-bottom`
  (neighbours), `parts.product` («In collections»), and `parts.links`. They are printed at the
  core's points `catalog.listing.top`, `catalog.listing.bottom` and `catalog.product.aside`.
- **Panel**: module `catalog-landings` in the catalogue's group; screen `catalog.landing-form`
  (tabs `main`, `texts-tab`, `recommended-tab`, `seo-tab`, `history-tab`). The API is under
  `/api/cms/catalog/landings` (with `count`, `facets`, `generate`, `publish`, `unpublish`,
  `restore`). It has no permissions of its own: `catalog.view` reads and `catalog.manage`
  writes.
- **Command** `webx:catalog-landings:count` (`--all`), which runs on the package's own schedule:
  every fifteen minutes, and with `--all` nightly.
- **MCP** tools `catalog_landings_list`, `_get`, `_facets`, `_count`, `_create`, `_update`,
  `_publish`, `_unpublish`, `_delete`, `_restore`, `_generate`, `_generation`. Resource:
  `catalog://landings` (the house rules).

## Change it without forking

| You want                               | Do this                                                                                     |
| -------------------------------------- | ------------------------------------------------------------------------------------------- |
| More or fewer links between landings   | `WEBX_CATALOG_LANDINGS_LINKS_CATEGORY`, `_SIBLINGS`, `_ELSEWHERE`, `_PRODUCT` (`links.*`)   |
| A longer strip of recommended products | `WEBX_CATALOG_LANDINGS_RECOMMENDED`                                                         |
| Bigger or smaller bulk generation      | `WEBX_CATALOG_LANDINGS_GENERATE_SYNC`, `_CHUNK`, `_MAX` (`generate.*`)                      |
| Different markup of the link lists     | `php artisan vendor:publish --tag=webx-catalog-landings-views`, keep only what you change   |
| The page of a landing itself           | override the core's `category` view: a landing is drawn with it                             |
| Every brand × these categories at once | «Create in bulk» in the panel, or `catalog_landings_generate` with `dry_run` as the preview |
| A field on the landing form            | a patch: `Screens::extend('catalog.landing-form', [...])`                                   |
| Change the defaults in a file          | `php artisan vendor:publish --tag=webx-catalog-landings-config`                             |

Landing form node ids: `naming`, `name`, `category`, `set`, `slug`, `display`, `sort`,
`on-category`, `position`, `texts`, `h1`, `text-above`, `text-below`, `recommended`, `seo`,
`history`.

## Do not

- Do not create a second landing with the same set on the same base (it is refused). Look up the
  existing one first with `catalog_landings_count`, which names the landing that already holds a
  set.
- Do not build a filter landing as a page out of blocks. It would not take over the filter's
  links, its 301s, or the product count. Make a landing instead.
- Do not edit `products_count` by hand. It is recounted after each indexing batch and by
  `webx:catalog-landings:count`. Run `--all` to recount now.
- Do not run large generations without a queue worker. Runs past `generate.sync_limit` go to the
  queue and wait there.
- Do not ignore «needs attention». A value in the set was deleted, and the set is now narrower
  than intended. Fix the set and save the landing; saving clears the mark.

## Check your work

- Open the landing's address. The filter on its category now links straight to it, and the
  category's spelling of the set answers with a 301. An empty landing stays up, but is
  `noindex` and out of the sitemap.
- `php artisan webx:catalog-landings:count --all`, then the count in the panel's list.
- With MCP: read `catalog://landings` first, and use `catalog_landings_facets` to get the codes
  and slugs a set is written in. Every write takes `dry_run: true`.

## Read more

- [README.md](README.md) in this directory: addresses, the page, links, the count, the API.
- Guide: https://webx-ui.github.io/webx-ui/guide/catalog
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_LANDINGS.md
- The core: `webx-ui/module-catalog` and its `AGENTS.md`.
