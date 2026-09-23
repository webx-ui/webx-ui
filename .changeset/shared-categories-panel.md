---
'@webx-ui/module-admin': minor
'@webx-ui/module-blog': minor
'@webx-ui/schema': minor
---

The screens of categories are shared, and a rubric is edited on a page of its own.

- `@webx-ui/module-admin`: `categoryRoutes(options)` mounts a module's list of categories
  (`WxCategoriesPage`) and the page of one (`WxCategoryEditorPage`: the module's screen, one Save,
  a refused field opens its tab), with `WxCategoryCreateDialog` for a new one. The words default to
  the panel's own ("category") under `webx-admin::categories.*`, and a module hands its own keys in
  for the ones that name its things. New node types `wx-categories` — the categories a record is
  in, dragged into order, the first marked as the main one, chosen from the path in `source` — and
  `wx-category-slug`, the address with the module's prefix in front. `useItemOrder()` and
  `reorderItems()` say which order a drag in a module's list writes: the whole list, one category's,
  or none while a search or another filter is on. `createCategoriesApi()` talks to
  `CategoryRoutes` on the server.
- `@webx-ui/module-blog`: rubrics are the shared category screens with the blog's words; a rubric
  opens at `/blog/rubrics/{id}` and a field a site patches onto `blog.category-form` is saved.
  `WxRubricsPage`, `WxRubricDialog`, `WxArticleRubrics`, `RubricInput`, `RubricsPayload` and the
  rubric methods of `createBlogApi()` are gone; `RubricRow` is the shared `CategoryRow` with
  `articles_count`, and `rubricsOptions()` is the description the screens are mounted with.
- `@webx-ui/schema`: a container none of whose children are drawn — or one described with
  `children: []` — is not drawn either, so an empty `project-fields` card no longer shows. A
  refusal named by language (`slug.en`) is shown under its field, and `wx-tabs` (now
  `WxScreenTabs`) opens the tab a refused field is on.
