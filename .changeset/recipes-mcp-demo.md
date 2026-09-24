---
'@webx-ui/php': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-blocks': patch
---

`webx-ui/module-recipes`: recipes for an agent — `recipes_list`, `recipes_get`, `recipes_create`, `recipes_update`, `recipes_publish`, `recipes_unpublish`, `recipes_delete`, `recipes_reorder` through the panel's own doors, the shared category tools as `recipe_categories_*` and `recipe_nutrients_*`, and `recipes://catalog` to read first. A recipe, a service and a similar recipe are named by id or by address, a category by slug, a nutrient by title; creating is the row and its values in one transaction; the tools that write say that the ingredients and the method are HTML lists, because the `Recipe` markup reads `<li>`. Demo content: three categories, five nutrients, six recipes, links to the demo services when they are there, and a page `/recipes-showcase` with both views of the block when blocks and pages are.

`@webx-ui/module-admin`: "The record of the page it stands on" in the relation filter of `wx-collection` (`related.current`), and a chosen record in the bin marked "In the bin" rather than only "Not on the site".

`@webx-ui/module-blocks`: the block template's autocomplete knows the card of the `recipes` source.
