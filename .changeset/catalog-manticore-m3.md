---
'@webx-ui/php': minor
'@webx-ui/module-catalog': minor
'@webx-ui/module-catalog-manticore': minor
---

The search index in the panel (M3 of the Manticore engine): «System → Search index» — on the Manticore engine only — shows the server, each language's table against the products in the database, and the queue, and rebuilds an out-of-date table as a job on the queue with its progress (`search-index.view`, `search-index.manage`); `catalog_index_status` tells an agent the same, and why one product is or is not found. The list of products says so when the index does not answer and the database does (`fell_back`). A table of an older schema is asked and written by the columns it has until it is rebuilt, instead of failing the list and the queue. New npm package `@webx-ui/module-catalog-manticore`; `Indexer::rebuild()` reports its progress.
