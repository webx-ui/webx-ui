---
'@webx-ui/php': minor
---

Shared categories and fields of the project, with the blog's rubrics moved onto them.

- `webx-ui/module-admin`: `WebxUi\Admin\Categories` — the code every module's categories share.
  `$table->category()` and `$table->categoryLinks()` lay down the tables; `IsCategory` (with the
  `Category` contract and a `CategoryKind` that names the screen, the permissions and the module's
  words) refuses to go into the bin while it holds items; `HasCategories` keeps two orders in the
  link table — the categories of a record, the first being the main one, and the record's place
  inside a category, kept when it is saved again and given by the order of the whole list when it
  is new; `Ordering::move()` writes either. `CategoryRoutes::register()` gives a module the whole
  API from its own route file (list, create, show with the values of the screen, update by
  `values`, delete, restore, reorder), `CategoryRoutes::items()` the reorder of its records;
  `CategoryLinkSource` and `CategoryTools` (list, create, update, delete, reorder) do the same for
  the link picker and for agents. New field type `wx-category-slug`.
- `webx-ui/module-admin`: fields of the project. `ScreenRecord` sorts what a described screen
  saved into the record's own fields, fields stored elsewhere and the rest — which now goes into
  `extra` instead of being dropped, merged rather than replaced, language by language for a
  localized field. `HasExtra` reads one of them on the site through its field type:
  `$service->extra('price-from')`. A module's screen keeps a `project-fields` card for a patch to
  add to.
- `webx-ui/module-blog`: rubrics are the blog's categories. The API keeps its address
  (`blog/rubrics`) and gains `GET blog/rubrics/{id}` and `restore`; `PUT` now takes `{ values }`
  of the new screen `blog.category-form` (content, image, and the SEO card patched in by
  `module-seo`). Agents get `rubrics_create`, `rubrics_update`, `rubrics_delete` and
  `rubrics_reorder` behind `rubrics:write`. A field a project patches onto an article or a rubric
  is saved in `extra` — through the draft for an article. A new migration adds `extra` to both
  tables and `item_position` to `article_rubric`, filled by date.
