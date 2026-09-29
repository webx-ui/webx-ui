---
'@webx-ui/php': minor
---

`module-catalog` is complete. Bulk actions (§11.4): the `BulkActions` registry satellites add to,
the core's publish, unpublish, set, add and remove a category, delete and restore; `GET`/`POST
/api/cms/catalog/bulk` and `GET /bulk/{run}`; a selection by ids or by the list's query, fixed as
ids when the run starts; up to `bulk.sync_limit` inside the request, more queued in chunks
(`catalog_bulk_runs`, `catalog_bulk_run_items`, the `ProcessBulkChunk` job), each chunk a
transaction with a savepoint per product, one journal run with a row per product. Fifteen MCP
tools with the permissions of §12.1 — deleting and restoring behind `catalog.delete`, `catalog_bulk`
asking it for those two actions — `dry_run` that does the write and takes it back, and six
resources, the parts of the product form among them. `webx:demo` seeds a shop of fifteen
categories and about a hundred and fifty products. `products()` on `RecordQuery`, the
`products` collection, categories and products in the menu's link sources, the catalogue in
`webx:setup`'s list and `extra.webx` for `webx:panel --sync`. The catalogue's API routes now carry
`webx.history`, so a save from the panel is journaled as `panel` with its author.
