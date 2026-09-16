# @webx-ui/module-blocks

## 0.1.0

### Minor Changes

- 7cecf88: The block constructor: the panel

  `@webx-ui/module-blocks` is new: the section where a block type is made — a list of cards with
  live thumbnails, and an editor with the template, styles, script, fields and settings on one side
  and the block drawn on its sample, the sample's form built from the schema being edited and where
  the type stands on the other. Checks run live under the editor; publishing is a separate step,
  refused with the line when the template fails on the sample or on a page. `wx-blocks` is the field
  that builds an entity out of blocks: a tree with drag to reorder, a picker of types with pictures
  that offers only what may go here, the selected block's fields as a form, and the entity's own
  preview beside them — the whole page while looking at it, a phone at one to one while editing a
  block, swapped in place after a field changes. `provideBlocksPreview()` is how the hosting screen
  hands the preview address in.

  In `webx-ui/module-blocks`, the panel half: the module (`blocks.view`, `blocks.manage`, the groups
  and `webx.provide()` names in the manifest), the API under `/blocks` — types, catalogue, save
  (a version per save, none for an unchanged one), publish with the check on every page's values,
  render on sent values or on an unsaved template, usage, history and restore — the lints the
  server sends back with a saved version, `webx-blocks.editing` as a read-only switch, and the
  dictionary in ten languages.
