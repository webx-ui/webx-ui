# webx-ui/module-catalog

The core of the product catalogue: products, a tree of categories, a price, a gallery with videos,
the engine behind lists, filters and search, the storefront, and CSV/XLSX import and export. Its
other job is the registries that satellites plug into. Properties, stock, brands, labels, landing
pages and the Manticore engine are separate packages (`webx-ui/module-catalog-*`), and each has a
guide of its own. The address is `webx-ui/routing`, the tree `webx-ui/nested-set`, the screens and
journal `webx-ui/module-admin`, the SEO card `webx-ui/module-seo` — read those guides when the
question is about one of them.

## What it owns

- **Tables** `catalog_products` (`WebxUi\Catalog\Models\Product`), `catalog_categories`
  (`Category`), `catalog_category_product`, `catalog_product_images`, `catalog_category_facets`,
  `catalog_filter_aliases`, plus service tables: `catalog_index_queue`,
  `catalog_product_popularity`, `catalog_bulk_runs`, `catalog_bulk_run_items` and
  `catalog_exchange_*` (profiles, runs, errors, run items).
- **Address types** `catalog.category` (`/{slug}`, flat at any depth, `OnConflict::Fail`) and
  `catalog.product` (`/{slug}-{id}`, `OnConflict::Suffix`). Filters are path segments after a
  category (`/laptops/brand_apple/price_100-500`). The only query parameter is `?sort=`.
- **Routes** `webx.catalog.search` (`/catalog/search`, always on) and `webx.catalog.root`
  (`/catalog`, only with `root.enabled`). With a language prefix, each is registered a second time.
- **Views** `webx-catalog::category`, `root`, `search`, `product`, `product-unavailable`, `filter`
  and `filter/*`, `grid`, `card`, `sort`, `pagination`, `breadcrumbs`. They render inside
  `config('webx-catalog.layout')`.
- **Storefront points** that satellites print into: `catalog.card.badges`, `catalog.card.meta`,
  `catalog.product.aside`, `catalog.product.tabs`, `catalog.product.unavailable`,
  `catalog.listing.top`, `catalog.listing.bottom`.
- **Panel screens** `catalog.product-form` (tabs `main`, `description-tab`, `images-tab`,
  `seo-tab`, `settings-tab`, `history-tab`) and `catalog.category-form` (`main`, `filters-tab`,
  `seo-tab`, `history-tab`). Also the exchange forms `catalog.exchange-import` and
  `catalog.exchange-profile`. The API is under `/api/cms/catalog`. Permissions are
  `catalog.view`, `catalog.manage` and `catalog.delete`; satellites have none of their own.
- **Registries** (singletons a satellite fills from its provider): `ProductParts`, `Facets`,
  `Sorts`, `Documents`, `ProductColumns`, `BulkActions`, `ExchangeColumns`, `ExchangeFormats`,
  `StorefrontParts`, `Purchasability`, `PopularitySignals`, `CatalogEngines`, `FilterUrls`,
  `SearchContributors`, `VideoProviders`, `SatelliteTools`.
- **MCP** tools `catalog_products_*` (list, get, create, update, publish, unpublish, delete,
  restore), `catalog_products_gallery*`, `catalog_categories_*` (tree, create, update, move,
  delete, restore), `catalog_bulk`, `catalog_import`, `catalog_export`, `catalog_exchange_columns`,
  `catalog_exchange_run`, `catalog_exchange_profiles`. Resources: `catalog://facets`, `fields`,
  `addresses`, `categories`, `product-parts`, `bulk-actions`, `exchange`. Scopes are
  `catalog:read` and `catalog:write`.
- **Commands** (all on the package's own schedule): `webx:catalog:index` (`--rebuild`),
  `webx:catalog:flush-views`, `webx:catalog:popularity`, `webx:catalog:exchange-prune`. Also
  registered: doctor checks for the engine, the exchange and the root; link sources;
  `wx-collection`'s products source; the sitemap file `catalog-filters`; demo content.

## Change it without forking

| You want                                  | Do this                                                                                                        |
| ----------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| The storefront inside the site's layout   | `WEBX_CATALOG_LAYOUT=layout` (`<x-layout>`); `webx:panel --sync` sets it                                       |
| Different markup of a catalogue page      | `php artisan vendor:publish --tag=webx-catalog-views`, keep only the files you change                          |
| A catalogue without prices                | `WEBX_CATALOG_PRICE=false` (form, facet, sorts, exchange and markup all go)                                    |
| `Offer` in the product's structured data  | `WEBX_CATALOG_CURRENCY` (ISO 4217)                                                                             |
| No barcode / no videos                    | `WEBX_CATALOG_BARCODE=false` / `WEBX_CATALOG_VIDEO=false`                                                      |
| Each category picks its own filters       | `WEBX_CATALOG_CATEGORY_FACETS=true` (the «Filters» tab)                                                        |
| A `/catalog` page over everything         | `WEBX_CATALOG_ROOT=true`; `WEBX_CATALOG_ROOT_PREFIX` moves it and the search                                   |
| Translated filter codes in addresses      | `facet_codes` in `config/webx-catalog.php` (`--tag=webx-catalog-config`)                                       |
| Another unit, sort order, page size       | `units` (+ its word via `--tag=webx-catalog-lang`), `sorts`, `default_sort`, `per_page`                        |
| Popularity counted differently            | bind your own `PopularityFormula` in the container                                                             |
| Thousands of products                     | `webx-ui/module-catalog-manticore` and `WEBX_CATALOG_ENGINE=manticore`                                         |
| Other disks for pictures / exchange files | `WEBX_CATALOG_IMAGES_DISK` / `WEBX_CATALOG_EXCHANGE_DISK`                                                      |
| A field on the product form               | a patch `Screens::extend('catalog.product-form', [...])` plus a `ProductPart` in `ProductParts`                |
| A dictionary of your own (a filter)       | a table keyed by `product_id`, then `ProductParts`, `Facets`, `Documents`, `ProductColumns`, `ExchangeColumns` |
| Something in a card or on a product page  | a `StorefrontPart` registered at one of the points above                                                       |
| A shelf of products in any template       | `products()->category('laptops')->sort('popular')->take(8)`; narrow it with `ProductQuery::macro`              |
| A refusal to sell                         | a `PurchaseRule` in `Purchasability`                                                                           |

Fields that are switched off are removed from the screen at boot, and so are never validated or
saved. A satellite's fields on `catalog.product-form` are named `<part key>.<field>`. The core
saves its own data and every part in one transaction, which produces one journal entry. Product
form node ids: `naming`, `name`, `slug`, `is_published`, `codes`, `sku`, `barcode-col`, `pricing`,
`prices`, `price`, `old_price`, `unit`, `placement`, `category_id`, `categories`, `summary`,
`description`, `gallery`, `seo`, `priority`, `history`. Category form node ids: `naming`, `name`,
`slug`, `is_published`, `presentation`, `cover`, `description`, `facets`, `seo`, `history`.

## Do not

- Do not edit `vendor/webx-ui/module-catalog`, and do not copy it into the site. If no row above
  fits, the package is missing a seam: say so.
- Do not add a column to `catalog_products` for your own data. Keep it in your own table with a
  `product_id` and write it through a `ProductPart`. Removing your module then leaves the
  product whole.
- Do not write products or their links with raw SQL and stop there. The engine never learns about
  the change. After a mass change, call `app(Catalog::class)->touchQuery($products)`, or better,
  save through the form, `catalog_products_update` or `catalog_bulk`.
- Do not put filters in query parameters, and do not use `_` in a category slug (it is refused).
  The `_` marks a filter segment, and each filter has exactly one spelling; anything else gets a 301.
- Do not hard-delete products. Deleting moves a product to «Deleted». It keeps its pictures, and
  its address redirects to its category (301), or returns 410 if there is no visible category.
  Links from orders must keep working. Use `catalog_products_delete` / `_restore`.
- Do not write a satellite's data through its own endpoint for a product. Use
  `catalog_products_update` with the part's key (`brand.id`, `labels.ids`, `stock.status`,
  `properties.values`). `catalog://product-parts` lists the parts that are installed.
- Do not hide a switched-off feature in a template. Switch it off in config, and nothing about it
  gets registered.
- Do not print `<title>` in a site's copy of a view: with an empty SEO card the product names its
  page itself (`seoFallback()` — the name through the title template, the summary, the main
  picture), and a list is called by its heading, with the category's description and cover on
  the plain page. A copy published before still has an `@if ($meta->title === null)` block:
  delete it, and keep `$meta` only where the `<h1>` reads `$meta->h1`.

## Check your work

- `php artisan webx:doctor` reports when the SQL engine is past `sql_engine_limit`, when the
  exchange is misconfigured, and when the root hides a page.
- Open the category and the product on the site. An unpublished product gets a trimmed page
  (`noindex`). A product only counts as visible when it is in at least one published category
  whose ancestors are all published.
- With Manticore: run `php artisan webx:catalog:index` (schedule and queue worker needed) and the
  product shows up in search.
- With MCP: read `catalog://fields` and `catalog://product-parts` first. Every write tool takes
  `dry_run: true`. `catalog_products_get` shows what was saved.

## Read more

- [README.md](README.md) in this directory: addresses, product states, views, satellites.
- Guide: https://webx-ui.github.io/webx-ui/guide/catalog
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_CATALOG.md
- The family design: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG.md
- Import and export: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_CATALOG_EXCHANGE.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
