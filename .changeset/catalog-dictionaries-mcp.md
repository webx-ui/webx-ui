---
'@webx-ui/php': patch
---

The catalogue's reference books, checked through an agent on a real site: the default stock status counts the products without a row of their own too, as the filter does (the panel and `catalog_stock_list` showed only the written rows); `catalog_brands_list` no longer tells an agent that a product's first brand is its main one — `CategoryKind` takes an optional `single` for a set an item is under at most once; and a missing `brand.id` is refused as "There is no such brand." rather than "no such id".
