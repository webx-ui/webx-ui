# @webx-ui/module-recipes

## 0.1.1

### Patch Changes

- 3abf5ad: Block components: a block type can be called by tag from any template —
  `<x-webx-block type="recipe-card" :card="$card" fallback="…" />` in another block, a module's view
  or the site's layout, with slots. A type of kind `component` stays out of the picker; its schema is
  the tag's input, with `wx-data` for a value passed from code and `wx-slot` for a named slot. Every
  version records the types its template calls, and publishing a type first draws every type that
  calls it on its sample and its pages; a type others call cannot be deleted, and circles of calls
  are refused. Modules declare the places they call a component from (`BlockComponents`, with data
  shapes in `BlockShapes`) and print them with `@webxPart`; **Customise** in the panel — or
  `blocks_create` for an agent — starts a component from the module's own view, and deleting it
  brings that view back. The MCP tools report the kind, `uses`, `used_by`, the data shapes and the
  declared places.

  The recipe card is the first declared place, `recipe-card`: the catalogue and similar recipes print
  it, and it now carries `category_links` and `service_links` — the services of a whole list in one
  query — and prints the main category beside the time and the services under the title.

## 0.1.0

### Minor Changes

- da138e0: The recipes section of the panel: the list of every recipe in its one order (dragged only while no
  filter narrows it — a recipe has no order inside a category or a service), filters by category,
  nutrient, service and status, and the bin; the editor of `recipes.form` with the tabs Recipe ·
  Settings · SEO · History, autosaved into a draft that carries the categories, the nutrients, the
  services and the similar recipes to the site on "Publish", guarded by a revision; "similar recipes"
  never offer the recipe itself. Categories and "Rich in" are the panel's shared category screens.

### Patch Changes

- Updated dependencies [da138e0]
- Updated dependencies [da138e0]
  - @webx-ui/module-admin@0.18.0
