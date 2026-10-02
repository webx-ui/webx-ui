# @webx-ui/module-services

## 0.1.13

### Patch Changes

- Updated dependencies [41f2059]
  - @webx-ui/core@0.35.0
  - @webx-ui/module-admin@0.23.0
  - @webx-ui/module-blocks@0.11.5
  - @webx-ui/schema@0.7.1

## 0.1.12

### Patch Changes

- Updated dependencies [3a08c6c]
- Updated dependencies [67e44aa]
  - @webx-ui/module-admin@0.22.0
  - @webx-ui/schema@0.7.0
  - @webx-ui/core@0.34.3
  - @webx-ui/module-blocks@0.11.4

## 0.1.11

### Patch Changes

- Updated dependencies [38c5b08]
- Updated dependencies [5ebd999]
  - @webx-ui/module-admin@0.21.0
  - @webx-ui/core@0.34.2
  - @webx-ui/module-blocks@0.11.3

## 0.1.10

### Patch Changes

- Updated dependencies [2ba4290]
  - @webx-ui/module-admin@0.20.0
  - @webx-ui/module-blocks@0.11.2

## 0.1.9

### Patch Changes

- Updated dependencies [d1ff63d]
  - @webx-ui/module-admin@0.19.0
  - @webx-ui/module-blocks@0.11.1

## 0.1.8

### Patch Changes

- Updated dependencies [0b70a2f]
  - @webx-ui/module-blocks@0.11.0

## 0.1.7

### Patch Changes

- 95df90f: Leaving the editor while an autosave is on its way no longer asks whether to leave without saving: the save waits for the one in flight, and goes again only for what was typed meanwhile.
- Updated dependencies [45170fe]
  - @webx-ui/schema@0.6.3

## 0.1.6

### Patch Changes

- Updated dependencies [50ea8a5]
- Updated dependencies [85b8fca]
  - @webx-ui/core@0.34.0
  - @webx-ui/schema@0.6.2
  - @webx-ui/module-admin@0.18.1
  - @webx-ui/module-blocks@0.10.2

## 0.1.5

### Patch Changes

- Updated dependencies [264c23a]
- Updated dependencies [264c23a]
- Updated dependencies [3adb99c]
  - @webx-ui/module-blocks@0.10.0

## 0.1.4

### Patch Changes

- Updated dependencies [3abf5ad]
  - @webx-ui/module-blocks@0.9.0

## 0.1.3

### Patch Changes

- Updated dependencies [da138e0]
- Updated dependencies [da138e0]
  - @webx-ui/module-admin@0.18.0
  - @webx-ui/module-blocks@0.8.9

## 0.1.2

### Patch Changes

- Updated dependencies [c7b0084]
- Updated dependencies [c7b0084]
- Updated dependencies [c7b0084]
- Updated dependencies [c7b0084]
  - @webx-ui/module-admin@0.17.0
  - @webx-ui/module-blocks@0.8.6
  - @webx-ui/core@0.33.2

## 0.1.1

### Patch Changes

- b7caa68: A round of panel fixes.

  - `useModal` finds its host when the modal was opened inside `app.runWithContext()` — which is where vue-router runs every guard. "Leave without saving?" from `onBeforeRouteLeave` answered nothing: its buttons were the stand-in's, the dialog stayed open and the navigation hung.
  - The shared category screens are a component per module, so going from the blog's rubrics to the services' categories mounts the list anew instead of keeping the rubrics on a page titled "Categories".
  - The panel's toasts stack at the bottom centre instead of the bottom-right corner, where they covered the action bar's buttons.
  - A category's cover is a card under its name rather than a tab of its own; the cover of an article and of a service sits right under the name too.
  - A number field on a described screen stops at 240px instead of stretching to the width of a title.
  - A new icon, `briefcase`, and the services section wears it instead of `star`.

- Updated dependencies [b7caa68]
  - @webx-ui/core@0.33.1
  - @webx-ui/schema@0.6.1
  - @webx-ui/module-admin@0.16.1

## 0.1.0

### Minor Changes

- 298c894: `@webx-ui/module-services`: the Services section of the panel. The list is the whole catalogue
  with no pages, dragged into order — the order of the site without a filter, the order of one
  category with that category chosen, and no grips while a search or a state narrows it. The editor
  is the `services.form` screen (content, settings with the project's fields, SEO, history) with an
  autosaved draft, a revision against overwriting, publish, discard and restore. The categories are
  the panel's shared category screens.

  `@webx-ui/module-admin`: `wx-slug` — the address field of any record, with the module's prefix in
  front and a warning before a live address moves; the editor hosting the screen hands it the prefix
  with `provideRecordAddress()`. `wx-category-slug` is the same field now (`WxCategorySlug` stays as
  an alias of `WxSlugField`). The navigation lights up the section with the longest matching path, so
  a section inside another's (`/services/categories`) is the one highlighted.

### Patch Changes

- Updated dependencies [298c894]
  - @webx-ui/module-admin@0.16.0
  - @webx-ui/module-blocks@0.8.5
