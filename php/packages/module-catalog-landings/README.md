# webx-ui/module-catalog-landings

Landing pages for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel. A
landing is a category's list — or the whole catalogue's — with a set of filters chosen in advance,
under an address, an SEO card and texts of its own: `/apple-laptops/` instead of
`/laptops/brand_apple/`. A satellite of `webx-ui/module-catalog`.

Specification: `docs/architecture/WEBX_UI_CATALOG_LANDINGS.md` in the monorepo.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-catalog` and what it requires (`module-admin`, `module-seo`, `routing`)

## Install

```bash
composer require webx-ui/module-catalog-landings
php artisan migrate
```

There are no permissions of its own: `catalog.view` reads the landings, `catalog.manage` writes them.

## Addresses

- `/{slug}/` from the site's root, type `catalog.landing` of `webx-ui/routing`, in the same space as
  the categories: a slug one holds the other cannot take. A new slug leaves the old one as a 301.
- Every link of the filter that chooses a landing's set points at the landing, and the category's
  spelling of the set is a 301 there. A choice over the set is written after it —
  `/apple-laptops/price_0-1000/` — and closed to the index.
- The largest landing that covers the state wins; a landing covers a facet only when the same
  values are chosen, so Apple and Dell together are not the Apple landing.

## The page

The category's template (`webx-catalog::category`), started from the set. On the plain page:

- the SEO card of the landing; an empty title is the H1, an empty H1 the name;
- the text above the list and the text under the pages;
- its own default sort, which the reader's `?sort=` beats;
- the strip of recommended products over the list (`webx-catalog-landings.recommended`).

A landing whose list is empty stays up, `noindex`, and out of the sitemap.

## Links

Four lists, each a Blade partial a site can publish (`webx-catalog-landings-views`) and a limit in
`webx-catalog-landings.links.*`: «Collections» on a category (landings marked `on_category`), the
neighbours of a landing on its base, the same set on other categories, and «In collections» beside
a product. Only published landings with products get links.

## The count

`products_count` is recounted after every batch of the index that touched a landing's base, and
nightly by `php artisan webx:catalog-landings:count --all` (on the package's schedule). Where the
database answers the searches, there is no index, and the quarter-hourly run counts every landing.

## Values that go

A set is stored by value ids. A merged value is followed; a deleted one drops out and the landing is
marked «needs attention»; a set left empty, or turned into another landing's, is unpublished. A
property in the bin drops out of every set at the next count. Saving the landing clears the mark.

## API

`/api/cms/catalog/landings` — the list (`q`, `category` — an id or `root` — `attention`,
`published`, `empty`, `trashed`), a landing, `POST`, `PUT`, `DELETE`, `restore`, `publish`,
`unpublish`; `POST count` (a set → the number of products, and the landing that already holds it)
and `GET facets?category=` (the facets of a base with their values).

## License

MIT
