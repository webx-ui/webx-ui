# @webx-ui/module-reviews

## 0.1.4

### Patch Changes

- Updated dependencies [d1ff63d]
  - @webx-ui/module-admin@0.19.0

## 0.1.3

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

## 0.1.2

### Patch Changes

- Updated dependencies [50ea8a5]
- Updated dependencies [85b8fca]
  - @webx-ui/core@0.34.0
  - @webx-ui/schema@0.6.2
  - @webx-ui/module-admin@0.18.1

## 0.1.1

### Patch Changes

- Updated dependencies [da138e0]
- Updated dependencies [da138e0]
  - @webx-ui/module-admin@0.18.0

## 0.1.0

### Minor Changes

- 8e61847: The first release of `@webx-ui/module-reviews`, the panel half of `webx-ui/module-reviews`.
  `reviews()` adds two sections under the Reviews group. Reviews shows the list and the form of one
  review side by side (`WxListDetail`); a row has the photo or the initials, the name, the stars,
  the languages it is seen in, and says when a published review is seen in none. You drag reviews
  into order: the whole list, or inside the one category the list is narrowed to. A new review is the
  first line of the list and becomes a record on its first save. The form is the described screen
  `reviews.form`. It saves with Ctrl+S and asks before unsaved words are dropped. On a phone the form
  fills the screen and has its own way back. Categories are the panel's shared category screens,
  without addresses (`reviewCategoriesOptions()`).
