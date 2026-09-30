---
'@webx-ui/module-catalog-labels': minor
'@webx-ui/module-catalog-stock': minor
'@webx-ui/module-catalog-brands': minor
'@webx-ui/module-catalog': minor
'@webx-ui/module-admin': minor
'@webx-ui/core': patch
'@webx-ui/php': minor
---

The catalogue's reference books in the panel: `@webx-ui/module-catalog-labels`, `-stock` and `-brands` — `catalogLabels()`, `catalogStock()`, `catalogBrands()`, each a section on the shared category screens with the number of products on a row and «Show its products» as the list narrowed by its facet. `module-catalog` gets the frame they stand on (`dictionarySection()`), the tone field of a label or a status (`wx-catalog-tone`), satellite columns drawn by the shape of their value (tags of a tone, a tag, a name muted when off the site) with breakpoints that keep the product's name readable, and bulk params asked out of a reference book (`source`). `module-admin`: `wx-select` takes `source` like `wx-categories`, and the shared category editor names its record to `wx-history`. `WxSelect` with `filterable` shows the label of a value whose options arrive after it. The composer half: `PartField` carries `source`, the list's facets of labels and stock come in the order an editor gave them, the product form's fields say what empty means.
