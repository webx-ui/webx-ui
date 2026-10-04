# webx-ui/module-catalog-stock

Stock statuses for the catalogue: in stock, out of stock, on order. Each product has exactly one
status, and the status decides whether the product can be bought. It is a status only: no
quantities and no warehouses. This package is a satellite of `webx-ui/module-catalog` and works
only through the core's registries. For products, the filter, the card, the engine and the
`Purchasability` chain, read the core's `AGENTS.md`.

## What it owns

- **Tables** `catalog_stock_statuses` (`WebxUi\CatalogStock\Models\StockStatus`) and
  `catalog_product_stock` (one row per product). The migration creates the statuses `in-stock`
  (the default, can be bought), `out-of-stock` and `on-order`. A product without a row has the
  **default** status. That is how the module goes onto a live catalogue without writing a row for
  every product.
- **A purchase rule** in `Purchasability`. A status with `is_purchasable` off refuses the product
  with the code `stock` and the status's own name. The rule comes after the core's «not on sale»
  check and before «price on request».
- **View** `webx-catalog-stock::status`, the status line at `catalog.card.meta` and
  `catalog.product.aside`.
- **Panel**: module `catalog-stock` in the catalogue's group, under «Dictionaries»; screen
  `catalog.stock-status-form`. The API is `catalog/stock` under the panel's API path. It has no
  permissions of its own: `catalog.view` reads and `catalog.manage` writes.
- **What it adds to the core**: the node `stock-card` / field `stock.status` in the `main` tab of
  `catalog.product-form` (after `pricing`), a products-list column, the facet `stock` (not
  indexable), the search document fields `stock` and `purchasable`, an exchange column, the bulk
  action `set-stock`, and `products()->inStock()`.
- **MCP** tools `catalog_stock_list`, `_create`, `_update`, `_delete`, `_reorder`.

## Change it without forking

| You want                            | Do this                                                                                              |
| ----------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Another status, e.g. «discontinued» | create it in the panel or with `catalog_stock_create`; set `is_purchasable` as needed                |
| A different status for new products | make another status the default (`is_default`), so the old one stops being the default               |
| Different markup of the status line | `php artisan vendor:publish --tag=webx-catalog-stock-views`, keep only `status.blade.php`            |
| Only buyable products in a template | `products()->inStock()->take(8)`                                                                     |
| A field on the status form          | a patch: `Screens::extend('catalog.stock-status-form', [...])`; `project-fields` is the place for it |
| Other words in the panel            | `php artisan vendor:publish --tag=webx-catalog-stock-lang`                                           |

Status form node ids: `naming`, `title`, `code`, `color`, `is-purchasable`, `is-default`,
`is-visible`, `project-fields`.

## Do not

- Do not edit `vendor/webx-ui/module-catalog-stock`, and do not copy it into the site. If no row
  above fits, the package is missing a seam: say so.
- Do not insert a `catalog_product_stock` row for every product to "initialise" the stock. A
  product without a row already has the default status. Saving a product writes its row anyway.
- Do not delete the default status or a status that products are in (both are refused). Make
  another status the default, or move the products away with `set-stock`.
- Do not decide «can it be bought» in a template from the status code. Ask the core's
  `Purchasability`. It combines this rule with the core's rules, and with a cart's rules when one
  is installed.
- Do not set a product's status through the stock API. Use `catalog_products_update` with
  `stock.status`, or `catalog_bulk` with `set-stock`.

## Check your work

- A product in a status with `is_purchasable` off shows its status line, and `Purchasability`
  refuses it with the code `stock`. The `stock` filter on a category counts it.
- With MCP: run `catalog_stock_list`, then `catalog_products_get` on a product to see
  `stock.status`. Every write takes `dry_run: true` first.

## Read more

- [README.md](README.md) in this directory: the default status, the purchase rule, what it adds.
- Guide: https://webx-ui.github.io/webx-ui/guide/catalog
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_DICTIONARIES.md
- The core: `webx-ui/module-catalog` and its `AGENTS.md`.
