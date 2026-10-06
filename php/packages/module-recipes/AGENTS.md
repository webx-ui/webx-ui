# webx-ui/module-recipes

Recipes: a page of fixed structure per recipe — gallery, ingredients, method, nutrition, time and
servings — flat categories that are pages of the site, a «rich in» list (nutrients), related
services and similar recipes, schema.org `Recipe`, and a block that puts a showcase or the whole
catalogue on any page. The section «Recipes» of the panel and the MCP tools `recipes_*` edit it.
The address is `webx-ui/routing`, the draft, history, categories and relations
`webx-ui/module-admin`, the SEO card `webx-ui/module-seo`, the photos `webx-ui/module-media` —
read their guides when the question is about one of those.

## What it owns

- **Tables** `recipes` (`WebxUi\Recipes\Models\Recipe`), `recipe_categories`
  (`WebxUi\Recipes\Models\RecipeCategory`), `recipe_nutrients`
  (`WebxUi\Recipes\Models\RecipeNutrient`) and the links `recipe_category_recipe`,
  `recipe_nutrient_recipe`. There is one order, the whole list's.
- **Address types** `recipe` and `recipe-category`, both on one level under
  `config('webx-recipes.prefix')` (default `recipes`), `OnConflict::Fail`. The prefix is never
  empty: an empty one stops the package from booting.
- **Index route** `webx.recipes.index` at the prefix, while `webx-recipes.index` is on.
- **Public views** `config('webx-recipes.views.index' | '.category' | '.recipe')`, defaults
  `recipes.index`, `recipes.category`, `recipes.recipe`. The recipe view is made of parts
  `recipe/gallery`, `recipe/heading`, `recipe/facts`, `recipe/ingredients`, `recipe/method`,
  `recipe/nutrition`, `recipe/services`, `recipe/similar`; the catalogue of the index, a category
  and the block is one fragment, `partials/catalog`. What each part is handed: `Rendering\RecipePage`.
- **Helper** `recipes()`, collection source `recipes`, offered block type `recipes` (with
  `webx-ui/module-blocks`), relation target `recipe`.
- **Panel screens** `recipes.form` (nodes `tabs`, `recipe`, `photos`, `gallery`, `cooking`,
  `ingredients`, `method`, `nutrition`, `nutrients`, `settings`, `naming`, `title`, `slug`,
  `lead`, `details`, `total-minutes`, `servings`, `taxonomy`, `categories`, `relations`,
  `services`, `related`, `project-fields`, `seo`, `history`, `versions`),
  `recipes.category-form` and `recipes.nutrient-form`.
- **API** under `/api/cms/recipes`, `/api/cms/recipes/categories`, `/api/cms/recipes/nutrients`;
  permissions `recipes.view`, `recipes.manage`, `recipes.categories.manage` (categories and
  nutrients).
- **MCP** tools `recipes_list`, `recipes_get`, `recipes_create`, `recipes_update`,
  `recipes_publish`, `recipes_unpublish`, `recipes_discard`, `recipes_delete`, `recipes_reorder`;
  `recipe_categories_*` and `recipe_nutrients_*` (`list`, `create`, `update`, `delete`,
  `reorder`); resource `recipes://catalog`. Scopes `recipes:read`, `recipes:write`,
  `recipe-categories:write`, `recipe-nutrients:write` (and their `:read`).
- Also registered: a link source, the panel group `recipes`, demo content (`resources/demo`).

## Change it without forking

| You want                                | Do this                                                                                                     |
| --------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Recipes inside the site's header/footer | `WEBX_RECIPES_LAYOUT=layout` (`<x-layout>`, slots `head` and default, `@stack('head')` in it)               |
| One part of the recipe page different   | `php artisan vendor:publish --tag=webx-recipes-views`, keep only the part you rewrite                       |
| The catalogue different everywhere      | rewrite `partials/catalog.blade.php` once: the index, a category and the block all change                   |
| Whole pages of your own                 | `WEBX_RECIPES_VIEW_INDEX`, `WEBX_RECIPES_VIEW_CATEGORY`, `WEBX_RECIPES_VIEW_RECIPE`                         |
| Another first segment                   | `WEBX_RECIPES_PREFIX`, then `php artisan webx:routes:rebuild --type=recipe --type=recipe-category`          |
| The index page built of blocks          | `WEBX_RECIPES_INDEX=false` and a page with the slug of the prefix in `webx-ui/module-pages`                 |
| Page size, number of similar recipes    | `WEBX_RECIPES_PER_PAGE` (24), `WEBX_RECIPES_SIMILAR` (3)                                                    |
| No visible breadcrumbs                  | `WEBX_RECIPES_BREADCRUMBS=false` (the BreadcrumbList in `<head>` stays, it is module-seo's)                 |
| An author's note, a call to action      | a patch: `Screens::extend('recipes.form', [...])` into `project-fields`, and `$recipe->extra()` in the part |
| Recipes in a template or a block        | `recipes()->in('breakfasts')->nutrients([3])->relatedTo('service', $service)->take(6)`                      |
| Other words in the panel or the site    | `php artisan vendor:publish --tag=webx-recipes-lang`                                                        |

All keys live in `config/webx-recipes.php` (`vendor:publish --tag=webx-recipes-config`). A screen
patch whose target is gone throws when the screen is first built.

## Do not

- Do not edit anything in `vendor/webx-ui/module-recipes`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
- Do not set the prefix empty: recipes at the root would fight the pages over every address, so
  the package refuses to boot. Change it with `webx:routes:rebuild`, which leaves redirects.
- Do not add a route for the prefix or a recipe in `routes/web.php`: the registry owns them. To
  take over the index, switch `index` off and make a page there.
- Do not send `blocks` to `recipes_update`: a recipe has no blocks, its page is the module's view.
  Write its fields.
- Do not type ingredients or steps as loose text: the `Recipe` markup reads one `<li>` per
  ingredient and per step. Use lists.
- Do not save without the `revision` you read: a stale one is refused. Read again with
  `recipes_get` and redo the change.
- Do not expect a saved change on the site: categories, nutrients, services and similar recipes
  wait in the draft with the text until `recipes_publish`.
- Do not delete rows with SQL: deleting bins a recipe and releases its address; a raw delete
  leaves the address in the routing registry.

## Check your work

- `php artisan webx:doctor` — the layout and its `@stack('head')`, and whose `recipes()` it is.
- Open the recipe on the site after publishing it, and check it on https://validator.schema.org
  and in Google's Rich Results Test.
- With MCP: read `recipes://catalog` first, then `recipes_get` (it gives a preview link of the
  draft); every tool that changes something takes `dry_run: true` first.

## Read more

- [README.md](README.md) in this directory — addresses, the page parts, similar recipes, SEO.
- Guide: https://webx-ui.github.io/webx-ui/guide/recipes
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_RECIPES.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
