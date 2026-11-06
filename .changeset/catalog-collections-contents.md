---
'@webx-ui/php': minor
---

`webx-ui/module-catalog-collections`: a collection now knows what it holds. By hand or by a filter, with the presets «new», «on sale» and «by label», the rule `only_purchasable` and a limit a place can only lower, from a cache of its own group `catalog-collections`. A value of a set merged or deleted repairs the collection. New index on `catalog_products.old_price`. After updating: `php artisan migrate`.
