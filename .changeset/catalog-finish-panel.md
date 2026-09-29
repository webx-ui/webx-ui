---
'@webx-ui/module-catalog': minor
---

Bulk actions in the product list: tick rows or pick "everything found" (sent as the list's query
and turned into ids by the server when the run starts), choose an action from the server's list
— the core's and every satellite's, only those the administrator may start — and answer what it
asks (a category from the tree). A small selection is done at once; a large one runs in the
background with a progress bar, and what refused is listed by product and left selected. New in
the API client: `bulkActions()`, `startBulk()`, `bulkRun()`, with the `BulkActionInfo`,
`BulkSelection` and `BulkRun` types.
