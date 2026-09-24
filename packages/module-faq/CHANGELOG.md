# @webx-ui/module-faq

## 0.1.1

### Patch Changes

- Updated dependencies [da138e0]
- Updated dependencies [da138e0]
  - @webx-ui/module-admin@0.18.0

## 0.1.0

### Minor Changes

- c7b0084: The first release of `@webx-ui/module-faq`, the panel half of `webx-ui/module-faq`. `faq()` adds
  two sections under the FAQ group. Questions shows the list and the form of one question side by
  side (`WxListDetail`). You drag questions into order: the whole list, or inside the one category
  the list is narrowed to. A new question is the first line of the list and becomes a record on its
  first save. The form is the described screen `faq.form`, with a read-only anchor you can copy as a
  fragment (`wx-faq-anchor`). It saves with Ctrl+S and asks before unsaved words are dropped. On a
  phone the form fills the screen and has its own way back. Categories are the panel's shared
  category screens, without addresses (`faqCategoriesOptions()`).

### Patch Changes

- Updated dependencies [c7b0084]
- Updated dependencies [c7b0084]
- Updated dependencies [c7b0084]
  - @webx-ui/module-admin@0.17.0
  - @webx-ui/core@0.33.2
