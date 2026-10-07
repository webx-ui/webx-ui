# webx-ui/module-catalog

The core of a product catalogue as a section of the [WebX UI](https://github.com/webx-ui/webx-ui)
admin panel: products, a tree of categories, prices, a gallery, three product states and a bin —
and the registries its satellite modules (properties, stock, brands, labels…) plug into.

The address is `webx-ui/routing`, the tree is `webx-ui/nested-set`, the journal and the screens are
`webx-ui/module-admin`, what a page says about itself is `webx-ui/module-seo`, the previews are
`webx-ui/module-media`, the languages are `webx-ui/localization`.

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-media`, `webx-ui/module-seo`, `webx-ui/routing`,
  `webx-ui/nested-set`, `webx-ui/localization`

## Install

```bash
composer require webx-ui/module-catalog
php artisan migrate
```

Permissions: `catalog.view`, `catalog.manage`, `catalog.delete`.

## Addresses

Both at the root of the site:

| What     | Address        | Notes                                                                               |
| -------- | -------------- | ----------------------------------------------------------------------------------- |
| category | `/{slug}`      | flat at any depth; a slug taken by a page or a category is a 422                    |
| product  | `/{slug}-{id}` | any other spelling of the slug answers 301 to this one; the slug need not be unique |

A product is **published**, **unpublished** or **deleted**. Published and in at least one visible
category — the full page. Unpublished, or published in no visible category — a trimmed page, 200
with `noindex`. Deleted — 301 to its main category (or a visible additional one), 410 when there is
none. A deleted category answers 410. An unpublished category hides everything below it.

## Views

`product` and `product-unavailable`, overridden by a site in
`resources/views/vendor/webx-catalog/`. `webx-catalog.layout` names the site's layout component.

With an empty SEO card a product names its page itself (`HasSeoFallback`): the name through the
site's title template, the summary (or the description, cut short) as the description, the main
picture as `og:image`, above the site's default social image. A page of a list (`CatalogPage`) is
called by its heading, and on the plain page the category's description and cover come with it.
So no view prints `<title>` itself, and a site's copy that still has the old
`@if ($meta->title === null)` block can delete it.

## Satellites

A satellite writes its share of the product form through a `ProductPart` registered with
`ProductParts::register()`; its fields on the screen `catalog.product-form` are named
`<part key>.<field>`. The form saves the core and every part in one transaction, with one journal
row.

## Documentation

The spec: `docs/architecture/WEBX_UI_MODULE_CATALOG.md` in the monorepo.
