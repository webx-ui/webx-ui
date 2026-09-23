# @webx-ui/schema

## 0.5.0

### Minor Changes

- 7bdeb63: Screens and block schemas can use every form control of the core. The registry now knows
  `wx-checkbox-group`, `wx-segmented`, `wx-slider`, `wx-rate`, `wx-time-picker`,
  `wx-date-time-picker`, `wx-date-range-picker`, `wx-tags-input`, `wx-autocomplete`,
  `wx-icon-picker`, `wx-code-editor`, `wx-cascader`, `wx-tree-select` and `wx-transfer`, plus
  `wx-heading` for display. `module-admin` registers a field type for each on the server — rules
  that check options, bounds, date formats and lists, and `store()` that casts and keeps an emptied
  list or date as `null`. `wx-date-time-picker` always writes the offset and is kept as ISO 8601 in
  the application's timezone. The agent catalogue and the block help list them too.

### Patch Changes

- b965650: A `wx-col` on a screen stacks the fields it holds with the form's step: two fields in one column no longer stand flush, the label of the second reading as the hint of the first.

## 0.4.0

### Minor Changes

- a0556f1: A list of records reads as a list: folded rows named `#1 · …`.

  `@webx-ui/core`: `WxRepeater` names a row by its position and then its `itemLabel` — `#2 · Lviv`,
  `#2` without one — and cuts a long header with an ellipsis. A key that holds a translated field
  shows the language being edited, else whichever is filled in; before, such a map made the header
  fall back to a bare number. `dragLabel` joins `addLabel` and `removeLabel`, so the grip's name can
  be translated too.

  `@webx-ui/schema`: `wx-repeater` on a screen starts folded unless the node sets
  `collapsed: false`, and takes its words — add, remove, reorder, the empty text — from the panel's
  dictionary; a node's own `addLabel` and the rest still win.

  `webx-ui/module-admin`: `screens.repeater.*` in all ten languages. `webx-ui/module-blocks`: the
  guide for agents names the repeater's props.

  A row lines its grip, header and actions up on one centre, and its fields run under the actions —
  and, in a repeater narrower than 560px, under the grip as well, so a phone gives the fields the
  whole width.

  `webx-ui/module-blocks`: `wx-row` and `wx-col` inside a `wx-repeater` of a block's schema no longer
  hide the fields in them. The bridge that names a block's nodes by their `id` named the layout too,
  and a named node is a field to the walk — so an image in a column was never resolved on the site.
  Layout keeps its id and gets no name; `Schema::LAYOUT` is the one list of those types.

### Patch Changes

- Updated dependencies [a0556f1]
  - @webx-ui/core@0.32.0

## 0.3.8

### Patch Changes

- Updated dependencies [8e0d587]
  - @webx-ui/core@0.31.0

## 0.3.7

### Patch Changes

- Updated dependencies [b1aeb52]
  - @webx-ui/core@0.30.0

## 0.3.6

### Patch Changes

- Updated dependencies [0a506df]
  - @webx-ui/core@0.29.0

## 0.3.5

### Patch Changes

- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
  - @webx-ui/core@0.28.0

## 0.3.4

### Patch Changes

- Updated dependencies [537df98]
  - @webx-ui/core@0.27.0

## 0.3.3

### Patch Changes

- Updated dependencies [a9383bb]
- Updated dependencies [b6a09a6]
  - @webx-ui/core@0.26.0

## 0.3.2

### Patch Changes

- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [937f4e2]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
  - @webx-ui/core@0.25.0

## 0.3.1

### Patch Changes

- Updated dependencies [f87e4ec]
- Updated dependencies [f87e4ec]
  - @webx-ui/core@0.24.0

## 0.3.0

### Minor Changes

- 74d1369: A round of panel fixes, mostly from looking at the two demo sites on a phone.

  - A field stops at a width it can be read at: `WxFormItem` caps its control at
    `--wx-field-max-width` (640px), and what is not a field in that sense says `wide` — a prop on
    the item, `wide: true` on a registry entry.
  - A hovered table row and the `···` at its end no longer paint themselves the same grey: the row
    goes a tone softer, and the row menu carries no fill at rest.
  - Tooltips never open on a touch screen, where the tap that opens one is the tap that was meant
    for the button under it. `useHoverPointer()` is the question, asked once for the application.
  - The panel's step reaches what the panel teleports out of itself — drawers, dialogs, the toaster
    — so a phone no longer lays one screen out with desktop air.
  - Blocks: a row of the tree offers its actions as the panel's `···` rather than three icons that
    only appeared on hover, and removing a block always asks first.
  - Inbox: the form editor no longer draws its save bar across the middle of the form, the section's
    panes stay inside the card's corners, the recipient's bin is red and asks, and a submission
    keeps its notes, its log and its metadata in one card with three tabs instead of three cards.

### Patch Changes

- Updated dependencies [74d1369]
  - @webx-ui/core@0.23.0

## 0.2.3

### Patch Changes

- Updated dependencies [ad9ead7]
  - @webx-ui/core@0.22.0

## 0.2.2

### Patch Changes

- Updated dependencies [738a7e9]
- Updated dependencies [2e27380]
- Updated dependencies [738a7e9]
- Updated dependencies [738a7e9]
- Updated dependencies [738a7e9]
- Updated dependencies [e93ae5b]
- Updated dependencies [a16ff45]
  - @webx-ui/core@0.21.0

## 0.2.1

### Patch Changes

- Updated dependencies [2c2c2ba]
  - @webx-ui/core@0.20.0

## 0.2.0

### Minor Changes

- 0304791: Repeater: a field whose value is a list of records

  `WxRepeater` is `WxSortableList` once every row is a form — a set of fields, repeated, in an order
  that is part of the answer. Rows fold to a name taken from their own fields, and each keeps a key
  of its own, so writing a field, removing the row above or dragging one elsewhere never rebuilds the
  form under the caret. `WxSortableList` gained `itemLabel` for the same reason: a row has to be
  called something out loud.

  In a described screen it is `wx-repeater`, the one type whose model is nested: the node's children
  are the fields of one item, and a `name` inside it is a key of that item. A type of its own can do
  the same with `nested: true`, which hands the component the node and the render context.

  On the server `RepeaterType` checks, stores and resolves items with the types their children
  declare — per language where a child is localized — and a failed row says which row it was.
  `FieldType::resolve` now takes the requested locale as a third argument, and `Tree::fields` stops
  at a named node: a repeater's children belong to its value, not to the screen.

### Patch Changes

- Updated dependencies [c92f42a]
- Updated dependencies [0304791]
  - @webx-ui/core@0.19.0

## 0.1.1

### Patch Changes

- Updated dependencies [47d52ee]
  - @webx-ui/core@0.18.0

## 0.1.0

### Minor Changes

- 64a7895: Screens as JSON, the client half: the node format from the specification (`id`, `type`, `name`, `label`, `help`, `localized`, `props`, `children`, `slot`, `visible`, `can`), `applyPatch` with add / remove / replace / move / set, `validateScreen` and `validatePatch`, conditional visibility, the `trans::` marker, a type registry built on the core components, and `WxScreenRenderer` that draws a tree against a model. The package now ships `schemas/screen.schema.json` and `schemas/patch.schema.json`. The earlier placeholder contracts (`SchemaNode`, action registries, `createComponentRegistry`) are gone; `DataAdapter`, `Paginated` and `ListQuery` stay for the adapter to come.

## 0.0.2

### Patch Changes

- 16106cb: Bootstrap the monorepo: design tokens generated from JSON, `WxButton` / `WxInput` / `WxCard`, schema
  contracts, VitePress documentation and the CI / release / docs pipelines.
