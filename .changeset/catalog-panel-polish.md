---
'@webx-ui/module-catalog': minor
'@webx-ui/php': minor
---

Catalog panel polish. The categories are an entry of the navigation of their own
(`catalog-categories`), and the products' entry is called «Products»: `catalog()` now returns two
modules — write `...catalog()` in `modules`. The product form puts the publish switch under the
name, the unit beside the price and the priority on a «Settings» tab; every other tab is a card. The
gallery rows are compact, with the size on the picture, and the upload from an address is a card of
its own. A category's «Filters» tab is off by default (`webx-catalog.fields.facets`), and its
picture is half as wide.
