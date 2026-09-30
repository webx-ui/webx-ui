# webx-ui/module-catalog-stock

Stock statuses for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel —
in stock, out of stock, on order. One per product, a line on the card and beside the buy button, a
filter, the refusal to sell, and `products()->inStock()` in a template. A satellite of
`webx-ui/module-catalog`: almost all of it is registrations in the core's registries.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-catalog` and what it requires

## Install

```bash
composer require webx-ui/module-catalog-stock
php artisan migrate
```

The migration makes three statuses: **In stock** (the default, can be bought), **Out of stock**
(cannot be bought) and **On order** (can be bought). The panel shows **Stock** in the «Catalog»
group, under the «Dictionaries» caption. There are no permissions of its own: `catalog.view` reads
the list, `catalog.manage` writes it.

## The default status

A product without a row in `catalog_product_stock` is in the default status, so the module goes onto
a live catalogue without writing a row per product. Saving a product with the default status writes
the row anyway: moving the default later must not quietly move products somebody set by hand.

The default cannot be deleted, and it stops being the default only when another status becomes it.
A status products are in cannot be deleted either.

## Can it be bought

A status with `is_purchasable` off refuses the product in the chain of `Purchasability` with the code
`stock` and the status's own name. It stands after the core's «not on sale» and before «price on
request».

## What it adds to the catalogue

- the field `stock.status` of the product form, in the «Main» tab;
- a column of the list of products, the facet `stock` (not indexable), the fields `stock` and
  `purchasable` of the search document;
- the bulk action `set-stock`;
- the status line in `catalog.card.meta` and `catalog.product.aside` — the view
  `webx-catalog-stock::status`, published with `--tag=webx-catalog-stock-views`;
- `products()->inStock()` — the products whose status can be bought.

To an agent: `catalog_stock_list`, `_create`, `_update`, `_delete`, `_reorder`; a product's status is
written by `catalog_products_update` with `stock.status`.

## License

MIT
