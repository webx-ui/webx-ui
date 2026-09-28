# @webx-ui/module-tariffs

## 0.1.1

### Patch Changes

- 6686e25: Tails from the last five modules. `WxInputNumber` with `precision` shows a whole number bare
  (`1380`, not `1380.00`) and a fraction to the precision (`1380.50`); the model is untouched. A
  refusal of a repeater row (`duties.1.text.ru`) is drawn under that row only, no longer a second
  time under the repeater as a whole. Ctrl+S in the editors saves also when a click on empty space
  left the focus on `<body>` — `useBodyKeys` in `module-admin`.
- Updated dependencies [6686e25]
  - @webx-ui/core@0.34.1
  - @webx-ui/schema@0.6.4
  - @webx-ui/module-admin@0.18.2

## 0.1.0

### Minor Changes

- f6a84ef: `@webx-ui/module-tariffs` — the tariffs section of the panel: price cards with a badge, a price in a
  currency of the site's list and a period (or words instead of a number), what is included, a
  description, one button and a "recommended" mark, in groups, ordered by hand. A list with the price on
  one line and a star for the recommended card beside the form of one tariff (`?tariff=`), two orders — the
  whole list, or one group's — a new tariff written before it exists and made on the first save, the
  bin, `Ctrl+S`, and a question before unsaved words are left. The groups are the panel's shared
  category screens under `/tariffs/groups`; the panel includes both as `...tariffs()`.
