# @webx-ui/module-catalog-labels

## 0.1.1

### Patch Changes

- Updated dependencies [3a08c6c]
- Updated dependencies [67e44aa]
  - @webx-ui/module-catalog@0.5.0
  - @webx-ui/module-admin@0.22.0
  - @webx-ui/schema@0.7.0
  - @webx-ui/core@0.34.3

## 0.1.0

### Minor Changes

- 38c5b08: The catalogue's reference books in the panel: `@webx-ui/module-catalog-labels`, `-stock` and `-brands` — `catalogLabels()`, `catalogStock()`, `catalogBrands()`, each a section on the shared category screens with the number of products on a row and «Show its products» as the list narrowed by its facet. `module-catalog` gets the frame they stand on (`dictionarySection()`), the tone field of a label or a status (`wx-catalog-tone`), satellite columns drawn by the shape of their value (tags of a tone, a tag, a name muted when off the site) with breakpoints that keep the product's name readable, and bulk params asked out of a reference book (`source`). `module-admin`: `wx-select` takes `source` like `wx-categories`, and the shared category editor names its record to `wx-history`. `WxSelect` with `filterable` shows the label of a value whose options arrive after it. The composer half: `PartField` carries `source`, the list's facets of labels and stock come in the order an editor gave them, the product form's fields say what empty means.

### Patch Changes

- Updated dependencies [38c5b08]
- Updated dependencies [5ebd999]
  - @webx-ui/module-catalog@0.4.0
  - @webx-ui/module-admin@0.21.0
  - @webx-ui/core@0.34.2
