---
'@webx-ui/php': minor
'@webx-ui/module-catalog': patch
---

The product gets a manufacturer part number and additional barcodes. `mpn` is a column beside `barcode` (`webx-catalog.fields.mpn`, `WEBX_CATALOG_MPN`): on the form, in `ProductResource`, the exchange and `catalog_products_*`. The additional barcodes live in `catalog_product_barcodes`: a list under the main barcode on the form, the `barcodes` column of the exchange (through `;`, an empty cell leaves it, `empty_clears` erases it; a row is never matched by them), `barcodes` in `catalog_products_get`, `_create`, `_update`, and `Product::allBarcodes()` for a theme, the main one first. The search of both engines finds a product by any of its barcodes.

**After the update:** `php artisan migrate`, and on Manticore `php artisan webx:catalog:index --rebuild` — the document has a new field `barcodes`, and an additional barcode is not found until the product is indexed again.
