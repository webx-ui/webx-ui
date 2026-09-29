# @webx-ui/module-catalog

## 0.1.0

### Minor Changes

- 7d47c13: Bulk actions in the product list: tick rows or pick "everything found" (sent as the list's query
  and turned into ids by the server when the run starts), choose an action from the server's list
  — the core's and every satellite's, only those the administrator may start — and answer what it
  asks (a category from the tree). A small selection is done at once; a large one runs in the
  background with a progress bar, and what refused is listed by product and left selected. New in
  the API client: `bulkActions()`, `startBulk()`, `bulkRun()`, with the `BulkActionInfo`,
  `BulkSelection` and `BulkRun` types.
- 31ef272: The catalogue's screens in the panel (K3): the list of products with search, views, facets as
  filters, the satellites' columns and picked rows; the product editor with its gallery (order,
  `alt` and `title` in every language, uploads and pictures by address) and a way to the product
  holding a taken article number; the tree of categories with counts, drag and publication; the
  category editor with its «Filters» tab (own setting, or whose it inherits); and «Deleted».

### Patch Changes

- Updated dependencies [d1ff63d]
  - @webx-ui/module-admin@0.19.0
