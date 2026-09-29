---
'@webx-ui/php': minor
---

`webx-ui/module-catalog`, the data layer of the catalogue core (`WEBX_UI_MODULE_CATALOG.md`, session
K1): products, a tree of categories with flat slugs, prices and units, a gallery on a disk of its
own, three product states and «Deleted». Addresses from the registry — a category at `/{slug}`, a
product at `/{slug}-{id}` with 301 from any other spelling; an unpublished or invisible product
answers a trimmed page with `noindex`, a deleted one 301 to its category or 410. The panel API
(products, gallery, categories with move, «Deleted»), the permissions `catalog.view`,
`catalog.manage`, `catalog.delete`, the journal for products and categories, and `ProductParts` —
the registry satellites write their share of the product form through, in the form's transaction.

`webx-ui/routing`: `Misses` — handlers a module registers for addresses the registry holds no row
for, asked before the 404. The refusal of a taken address names an entity by `name` when it has no
`title`.

`webx-ui/module-media`: `Thumbnails::variantOf($disk, $path, …)` and `forgetOf()` for a picture
outside the library, with previews kept beside it; `variant(MediaFile)` goes through the same code.
