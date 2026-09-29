---
'@webx-ui/php': minor
---

`webx-ui/module-catalog`, the engine and the storefront of the catalogue core
(`WEBX_UI_MODULE_CATALOG.md`, session K2): the registries satellites plug into — `Facets` (the
core's `category` as a tree and `price` as a range), `Sorts`, `Documents` / `DocumentContributor`,
`ProductColumns`, `Purchasability`, `FilterUrls` with rewriters, `PopularitySignals`,
`StorefrontParts`; `CatalogQuery` / `CatalogResult` and `SqlEngine`; the index queue with
`webx:catalog:index`; popularity with `webx:catalog:flush-views` and the nightly
`webx:catalog:popularity`; a category's facets inherited from the nearest configured ancestor. The
storefront: a category with a filter of links that works without a script (`/laptops/brand_apple`,
one spelling, 301 on any other), `noindex` on combinations, ranges, sorts and later pages, the
root `/catalog/` behind the config, the search, `Product` / `Offer` / `ItemList` markup, the first
levels of the filter in the sitemap. The panel gets `GET /api/cms/catalog/facets` and a list of
products searched and counted by the engine.

`webx-ui/module-admin`: `DoctorChecks` — checks a module adds to `webx:doctor`.

`webx-ui/module-seo`: `SitemapSources` — addresses a module adds to the sitemap without rows in the
registry.
