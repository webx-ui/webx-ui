---
'@webx-ui/php': minor
---

The block constructor: the package and the renderer

`webx-ui/module-blocks` is the section of the panel where a block type is made entirely — its
fields, its Blade template, its styles and its script — and the mechanism that prints an entity's
content from such blocks. This is its first half, the rendering: three tables (`blocks`,
`block_versions`, `block_bundles`), the `Block` and `BlockVersion` models with `saveVersion()` and
`publish()`, the cached registry `BlockTypes`, Blade compiled from the database into one file per
version, the `@blocks` directive for nesting, the `HasBlocks` trait with the `$table->blocks()`
macro, and `Blocks::render()`.

Every block renders inside its own try/catch, so one broken template leaves a gap and a report
rather than taking the page with it; in preview mode the gap is a notice with the template's line,
and every block is wrapped in a pair of comments the panel finds it by. Publishing a version renders
it on its sample values first and refuses, with the line, when that throws. The schema's fields are
the template's variables — a field added after the content was written is `null` on the old pages,
not an error.

The second half of the same package, the styles and scripts: the set of types a page rendered, at
their versions, makes a hash that names a row of `block_bundles` with the glued CSS and JS, served
by `/blocks/{hash}.css` and `.js` with a year-long immutable cache. `@webxBlocks` in the layout
prints the tags — evaluated where it stands, after the content under `@extends` and components —
with `('styles')`, `('scripts')` and `('runtime')` variants and an inline mode for small sets. A
block's script is an initialiser per instance behind a small runtime (`webx.block`, `webx.mount`,
`webx.provide`, `webx.use`) that also ships on its own at `/blocks/runtime.js`. Commands:
`webx:blocks:bundles --prune|--warm` and `webx:blocks:clear`.

The preview route, the panel section and the MCP tools follow.
