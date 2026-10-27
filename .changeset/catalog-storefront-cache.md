---
'@webx-ui/php': minor
---

module-catalog: the generation of the catalogue and the cache of the filter's counts. `StorefrontCache` keeps the
storefront's caches on the version of the `catalog` cache group; it moves on a save of any watched model (the core's
and the satellites', which now watch theirs through `StorefrontCache::watch()`) and on every `Catalog::touch()`,
`touchQuery()`, `touchCategory()` — whatever the engine — once per transaction or `defer()`. `SqlEngine` keeps the
counts of a storefront's filter on it, keyed by the selection, the normalised filters, the search and the language
(not the page or the sort): a warm category page asks no facet query. `webx-catalog.cache.enabled` /
`WEBX_CATALOG_CACHE_ENABLED` and `ttl` / `WEBX_CATALOG_CACHE_TTL`; reset with `cache_reset` (`group: catalog`);
`debug_page` shows each storefront cache as `catalog.<cache>.<hash>` (memo / store / miss).
