---
'@webx-ui/php': minor
---

module-catalog: the product page asks each thing once. The shape of the category tree (bounds and visibility) is
kept in the storefront's cache (`tree`, `Storefront\CategoryIndex`): the trail, `Category::isVisible()`, the product's
visibility on its page and `products()->category(…)` read it instead of the tree. The main category, its trail and the
main categories of a shelf come from one pool a request — one query. The product page, the category page and the
brands note their addresses with `RouteCache` instead of loading `routes`. The visible property groups are cached too.
Warm on the demo shop: product page 22 → 13 queries, category 26 → 21.

routing: `RouteCache::preload()` only notes the entities; the first `canonical()` reads everything noted by then in one
`many()` and one query for every type.

module-media: `MediaFiles::prefetch()` names keys the first look-up of every response takes along. module-settings
uses it for its pictures, so the logo printed by the header comes in the same query as the pictures of the blocks.
`ScreenValues::preload()` (module-admin) hands a screen's stored values to the types that load ahead.
