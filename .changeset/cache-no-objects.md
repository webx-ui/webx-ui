---
'@webx-ui/php': patch
---

Catalog: the storefront's filter counts are cached as arrays, so a category page no longer fails with `__PHP_Incomplete_Class` on its second request when the site's cache unserializes no classes (`cache.serializable_classes => false`, Laravel's skeleton). Cache: `webx-cache.strict` (`WEBX_CACHE_STRICT`) refuses a value with an object in it when it is stored.
