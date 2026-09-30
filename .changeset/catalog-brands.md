---
'@webx-ui/php': minor
---

The third satellite of the catalogue, the composer half: `webx-ui/module-catalog-brands` — a brand per product with a page of its own at `/brands/{slug}/` (the catalogue narrowed to the brand, the filter's tail behind it), the list at `/brands/`, a logo from the media library, a description, an SEO card, a journal, an indexable `brand` facet, `products()->brand()` and `brands()->featured()`. The core's storefront gets the seam a satellite's page needs: `Storefront::listing()` is public, and a `FilterContext` may carry a `ListingSubject` that names the page, its trail and its SEO card instead of a category.
