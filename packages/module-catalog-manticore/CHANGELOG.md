# @webx-ui/module-catalog-manticore

## 0.1.0

### Minor Changes

- 92c75c1: The search index in the panel (M3 of the Manticore engine): «System → Search index» — on the Manticore engine only — shows the server, each language's table against the products in the database, and the queue, and rebuilds an out-of-date table as a job on the queue with its progress (`search-index.view`, `search-index.manage`); `catalog_index_status` tells an agent the same, and why one product is or is not found. The list of products says so when the index does not answer and the database does (`fell_back`). A table of an older schema is asked and written by the columns it has until it is rebuilt, instead of failing the list and the queue. New npm package `@webx-ui/module-catalog-manticore`; `Indexer::rebuild()` reports its progress.

### Patch Changes

- 9226fe8: The Manticore engine accepted on the alfatech export (93 677 products: a rebuild in 44 s, a category with ten facets in 44 ms, a corrected typo in 51 ms). A question that falls into a rebuild's swap is asked once more instead of failing; «System → Search index» counts a rebuild started from the console and follows it; the stock status in a product's document is the one the filter shows, so a hidden status is counted by neither engine.
