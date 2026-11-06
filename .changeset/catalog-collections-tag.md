---
'@webx-ui/php': minor
---

`webx-ui/module-catalog-collections`: the tag `<x-webx-catalog-collections::collection key="…" />` prints a collection on the site, as a grid or a carousel, with the catalogue's own card. `view` and `title` are per place, and `limit` can only lower the collection's own. A collection that is off, in the bin, unknown or empty prints nothing. Base look in `resources/theme/catalog-collections.css`; after updating: `php artisan webx:theme:sync`.
