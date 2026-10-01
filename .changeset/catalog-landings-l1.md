---
'@webx-ui/php': patch
---

The catalogue's core is ready for landings: `Storefront::listing()` takes a base state the tail adds to, and "nothing chosen" means nothing over it; a rewriter can open a state it takes whole (`RewrittenUrl::$indexable`); the list's owner prints its texts (`HasListingTexts`, the category's description above) and opens in its own order (`HasDefaultSort`); the points `catalog.listing.top` and `catalog.listing.bottom`; `FacetValueRetargeted` from `FilterAliases::retarget()`, which a deleted brand now calls too.
