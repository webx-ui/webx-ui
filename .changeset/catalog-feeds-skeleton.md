---
'@webx-ui/php': minor
'@webx-ui/module-catalog-feeds': minor
---

New satellite of the catalogue, `webx-ui/module-catalog-feeds` and `@webx-ui/module-catalog-feeds`: the tables of product feeds, the feeds' CRUD API under `catalog/feeds` and the section «Catalog» → «Feeds» in the panel, with an empty list for now. The address with the feed's key goes only to `catalog.manage`, and a feed is always in the site's currency (`WEBX_CATALOG_CURRENCY`). Building and serving a file come next. After updating: `php artisan migrate`.
