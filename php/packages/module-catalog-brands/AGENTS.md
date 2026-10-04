# webx-ui/module-catalog-brands

Brands for the catalogue. A product has at most one brand, and each brand has a page of its own:
the catalogue narrowed to that brand, with a logo and a description above it. This package is a
satellite of `webx-ui/module-catalog` and works only through the core's registries. For products,
categories, the filter, the storefront views and the engine, read the core's `AGENTS.md`. The
brand page's address comes from `webx-ui/routing`, and its logo is picked from
`webx-ui/module-media`.

## What it owns

- **Tables** `catalog_brands` (`WebxUi\CatalogBrands\Models\Brand`) and `catalog_product_brand`
  (one row per product). `title`, `slug` and `description` are translatable.
- **Address type** `catalog.brand`: `/{prefix}/{slug}/`, which takes the filter's tail like a
  category does (`/brands/apple/category_laptops/`). It uses `OnConflict::Fail`. A hidden brand
  returns 404 and a deleted one returns 410. The list `/{prefix}/` is the route
  `webx.catalog-brands.index`, and it is in the sitemap.
- **Views** `webx-catalog-brands::brands` (the list), `brand` (a brand's page, built from the
  catalogue's own partials and layout) and `line` (the brand line on a card and beside the buy
  button).
- **Panel**: module `catalog-brands` in the catalogue's group; screen `catalog.brand-form` (tabs
  `main`, `description-tab`, `seo-tab`, `history-tab`). The API is `catalog/brands` under the
  panel's API path. It has no permissions of its own: `catalog.view` reads and `catalog.manage`
  writes.
- **What it adds to the core**: the node `brand-card` / field `brand.id` in the `main` tab of
  `catalog.product-form`, a products-list column, the facet `brand` (indexable), the field `brand`
  of the search document, an exchange column, and the bulk action `set-brand`. It also adds the
  brand line at `catalog.card.meta` and `catalog.product.aside`, `products()->brand('apple')`, and
  brands as link targets in menus.
- **Template helper** `brands()`, which returns published brands as cards:
  `brands()->featured()->take(8)`.
- **MCP** tools `catalog_brands_list`, `_create`, `_update`, `_delete`, `_reorder`.

## Change it without forking

| You want                            | Do this                                                                                       |
| ----------------------------------- | --------------------------------------------------------------------------------------------- |
| Brands under another path           | `WEBX_CATALOG_BRANDS_PREFIX`, then `php artisan webx:routes:rebuild --type=catalog.brand`     |
| Different markup of the brand pages | `php artisan vendor:publish --tag=webx-catalog-brands-views`, keep only the files you change  |
| Brands on the home page             | `brands()->featured()->take(8)` in the template; mark brands «featured» in the panel          |
| Products of a brand in a template   | `products()->brand('apple')->take(8)` (by slug or id)                                         |
| A field on the brand form           | a patch: `Screens::extend('catalog.brand-form', [...])`; `project-fields` is the place for it |
| Other words in the panel            | `php artisan vendor:publish --tag=webx-catalog-brands-lang`                                   |
| Change the default config           | `php artisan vendor:publish --tag=webx-catalog-brands-config`                                 |

Brand form node ids: `naming`, `title`, `slug`, `logo`, `is-visible`, `is-featured`,
`project-fields`, `description`, `seo`, `history`.

## Do not

- Do not edit `vendor/webx-ui/module-catalog-brands`, and do not copy it into the site. If no row
  above fits, the package is missing a seam: say so.
- Do not change the prefix on a live site and stop there. Every brand address moves. Run
  `webx:routes:rebuild --type=catalog.brand`, which keeps the old addresses as redirects.
- Do not use `_` in a brand slug (it is refused). `_` marks a filter segment. Slugs are also
  unique across the whole site, including pages and categories.
- Do not delete a brand that products still use (it is refused). First move those products to
  another brand with the bulk action `set-brand`, or clear the brand that way.
- Do not set a product's brand through the brand API. Use `catalog_products_update` with
  `brand.id` (empty clears it), or `catalog_bulk` with `set-brand`.

## Check your work

- Open `/brands/` and a brand's page on the site. A hidden brand does not appear in the list,
  and its page returns 404.
- On a category page, the `brand` filter lists the brand. `/laptops/brand_apple/` is open to
  search engines.
- With MCP: run `catalog_brands_list`, then `catalog_products_get` on a product to see
  `brand.id`. Every write takes `dry_run: true` first.

## Read more

- [README.md](README.md) in this directory: addresses, pages, what it adds to the catalogue.
- Guide: https://webx-ui.github.io/webx-ui/guide/catalog
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_DICTIONARIES.md
- The core: `webx-ui/module-catalog` and its `AGENTS.md`.
