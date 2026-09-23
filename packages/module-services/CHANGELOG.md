# @webx-ui/module-services

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
