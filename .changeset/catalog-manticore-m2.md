---
'@webx-ui/php': minor
'@webx-ui/module-catalog': minor
---

The catalogue search on Manticore (M2): the codes of a product — article number, barcode, external id — searched as written and by any part of their letters and digits, the product whose code the search is first, and the storefront going straight to its card; a search that finds nothing tried with the other keyboard layout of the site's languages, then corrected word by word, with «Showing results for …, search instead for …» on the storefront and in the panel; and 503 with `Retry-After` on the site's own page when the catalogue is too large to fall back on the database. The tables gain `codes`, `codes_flat`, `code_keys` and `min_infix_len`: `webx:doctor` reports them out of date, `webx:catalog:index --rebuild` makes them anew.
