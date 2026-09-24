# @webx-ui/module-services

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
