---
'@webx-ui/php': minor
---

module-catalog-labels: every label of a page's products in one query, for the badges and for the template. The badges
of `catalog.card.badges` load all labels — service ones too — and filter `is_badge` in PHP; `StorefrontParts::extend()`
adds the labels of more products in one more query at most. The answer is kept in the storefront's cache (`labels`,
keyed by the products and the generation of the catalogue) and in the request: a warm page asks no label query. A
template reads a product's labels through `app(Labels::class)->of($product)` instead of querying them itself.
