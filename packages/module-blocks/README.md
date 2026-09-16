# @webx-ui/module-blocks

The block constructor as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: the screen where a block type is made entirely — its fields, its Blade template, its
styles and its script — and `wx-blocks`, the field that builds an entity's content out of such
blocks.

The other half is `webx-ui/module-blocks` on the server, which stores the types, renders them and
serves the preview. Neither is useful alone.

**Access to the section is access to deployment.** A block type is Blade, and Blade is PHP.

## Install

```bash
pnpm add @webx-ui/module-blocks
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { blocks } from '@webx-ui/module-blocks'
import '@webx-ui/module-blocks/style.css'

createAdmin({
  modules: [blocks()],
})
```

The section appears at the top level once the Composer half is installed and migrated.
Permissions: `blocks.view`, `blocks.manage`. With `webx-blocks.editing` off on the server the
section is read-only whatever the permission says.

## What it brings

- `/blocks` — every type as a card with a live thumbnail drawn on its sample values, grouped the
  way the picker groups them.
- `/blocks/:id` — the editor: template, styles, script, fields and settings on the left; the
  block drawn on its sample at a desktop, tablet or phone width, the sample's form built from the
  schema being edited, and where the type stands on the right. The loop is closed: edit the
  schema and the form rebuilds, edit the values and the stage redraws, edit the template and so
  does it. Under the editor, what was noticed — a selector outside the block's prefix, a bare
  element selector, a media query, a missing `data-wx-block`, a variable the schema does not
  declare — and what was not.
- **Publish** — a separate step, refused with the line when the template fails on the sample or
  on any page the block already stands on. The history lists a version per save; restoring one
  makes it the draft.
- `wx-blocks` — a field type any screen can put on an entity. Nothing selected: the tree of
  blocks and the whole page in preview. A block selected: the tree, its fields, the page as a
  phone at one to one. The preview is the entity's own `/_preview/…` address, handed in by the
  hosting screen:

```ts
import { provideBlocksPreview } from '@webx-ui/module-blocks'

provideBlocksPreview({ url: previewUrl, reload: reloadCounter })
```

Without it the field is a tree with a form, which is all a screen outside the panel can be.

## License

MIT.
