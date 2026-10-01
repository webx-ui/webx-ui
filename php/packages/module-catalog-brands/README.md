# webx-ui/module-catalog-brands

Brands for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel. One per
product, each with a page of its own — the catalogue narrowed to the brand, with a logo and a
description on top — a filter, a line on the card and beside the buy button, `products()->brand()`
and `brands()->featured()` in a template. A satellite of `webx-ui/module-catalog`.

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-catalog` and what it requires (`module-media`, `module-seo`, `routing`)

## Install

```bash
composer require webx-ui/module-catalog-brands
php artisan migrate
```

The panel shows **Brands** in the «Catalog» group, after «Products». There are no permissions of its
own: `catalog.view` reads the list, `catalog.manage` writes it.

## Addresses

- `/brands/` — the list of published brands, in the order of the list. A route, not a row of the
  registry.
- `/brands/{slug}/` — a brand's page, type `catalog.brand` of `webx-ui/routing`. It takes the
  filter's tail as a category does: `/brands/apple/category_laptops/`. The brand's own facet is not
  on its page, so the first level is the categories.
- The prefix is `webx-catalog-brands.prefix` (`WEBX_CATALOG_BRANDS_PREFIX`); after changing it, run
  `php artisan webx:routes:rebuild --type=catalog.brand`.

A slug is unique in its language across the site: a taken one is a 422 naming whoever holds it, and
`_` is refused — it marks the filter. A hidden brand's page answers 404, a deleted one's 410. A brand
products are of cannot be deleted.

## The pages

`webx-catalog-brands::brand` and `webx-catalog-brands::brands`, published with
`--tag=webx-catalog-brands-views`. The brand's page is drawn with the catalogue's own partials
(`filter`, `grid`, `sort`, `pagination`) and its layout (`webx-catalog.layout`); `$page` is the
catalogue's `CatalogPage`, `$brand` the brand. Its SEO is the brand's own card on the plain page and
«{brand} {value}» on the first level of the filter.

## What it adds to the catalogue

- the field `brand.id` of the product form, in the «Main» tab — empty is no brand;
- a column of the list of products, the facet `brand` (indexable: `/laptops/brand_apple/` is open to
  search engines), the field `brand` of the search document;
- the bulk action `set-brand` — an empty brand takes the brand away;
- the brand line in `catalog.card.meta` and `catalog.product.aside` — the view
  `webx-catalog-brands::line`; a hidden brand prints nothing;
- `products()->brand('apple')` — the products of published brands, by slug or id;
- `brands()` — published brands as cards (`id`, `name`, `slug`, `url`, `logo`, `featured`,
  `description`): `brands()->featured()->take(8)`;
- brands as links for a menu.

To an agent: `catalog_brands_list`, `_create`, `_update`, `_delete`, `_reorder`; a product's brand is
written by `catalog_products_update` with `brand.id`.

## License

MIT
