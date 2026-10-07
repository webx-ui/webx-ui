# @webx-ui/module-catalog-manticore

## 0.2.1

### Patch Changes

- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
  - @webx-ui/core@0.38.0
  - @webx-ui/module-admin@0.23.5

## 0.2.0

### Minor Changes

- 3dfe40a: The console's `webx:catalog:index --rebuild` and the panel's «Rebuild» share one lock: the second
  one is refused, and the search index page holds its button back while the console rebuilds.
  `WxTable` keeps its heading row in sight while the page scrolls past a long table (`stickyHeader`,
  on by default). In the audit's page card, a picture that answered shows its thumbnail and opens
  full size; the stand's, unchecked and broken ones keep the placeholder and are not fetched.

### Patch Changes

- Updated dependencies [3dfe40a]
  - @webx-ui/core@0.37.0
  - @webx-ui/module-admin@0.23.2

## 0.1.2

### Patch Changes

- Updated dependencies [62497fc]
  - @webx-ui/core@0.36.0
  - @webx-ui/module-admin@0.23.1

## 0.1.1

### Patch Changes

- Updated dependencies [41f2059]
  - @webx-ui/core@0.35.0
  - @webx-ui/module-admin@0.23.0

## 0.1.0

### Minor Changes

- 92c75c1: The search index in the panel (M3 of the Manticore engine): «System → Search index» — on the Manticore engine only — shows the server, each language's table against the products in the database, and the queue, and rebuilds an out-of-date table as a job on the queue with its progress (`search-index.view`, `search-index.manage`); `catalog_index_status` tells an agent the same, and why one product is or is not found. The list of products says so when the index does not answer and the database does (`fell_back`). A table of an older schema is asked and written by the columns it has until it is rebuilt, instead of failing the list and the queue. New npm package `@webx-ui/module-catalog-manticore`; `Indexer::rebuild()` reports its progress.

### Patch Changes

- 9226fe8: The Manticore engine accepted on a real shop export (93 677 products: a rebuild in 44 s, a category with ten facets in 44 ms, a corrected typo in 51 ms). A question that falls into a rebuild's swap is asked once more instead of failing; «System → Search index» counts a rebuild started from the console and follows it; the stock status in a product's document is the one the filter shows, so a hidden status is counted by neither engine.
