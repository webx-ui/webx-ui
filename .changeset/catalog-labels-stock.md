---
'@webx-ui/php': minor
---

Two satellites of the catalogue, the composer half: `webx-ui/module-catalog-labels` (top, sale, new — several per product, badges, a filter, `products()->label()`) and `webx-ui/module-catalog-stock` (in stock, out of stock, on order — one per product, the refusal to sell, `products()->inStock()`). Both are reference books on the panel's shared categories under the «Dictionaries» caption of the catalog group. The core gets a `Dictionary` base model for reference books with a code and a tone, facets that keep the order of their reference book (`OrderedFacet`), and `products()` steps a satellite adds as macros.
