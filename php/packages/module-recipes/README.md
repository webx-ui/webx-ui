# webx-ui/module-recipes

Recipes as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: a page of
fixed structure per recipe — gallery, ingredients, method, nutrition, time and servings — flat
categories that are pages of the site, what a recipe is rich in, related services and recipes,
schema.org `Recipe`, and a block that puts a showcase or the whole catalogue on any page.

The address is `webx-ui/routing`, the draft, the history, the categories and the relations are
`webx-ui/module-admin`, what a page says about itself is `webx-ui/module-seo`, the photos are
`webx-ui/module-media`, the languages are `webx-ui/localization`. What this package adds is the
recipe and its page.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-media`, `webx-ui/module-seo`, `webx-ui/routing`,
  `webx-ui/localization`, `webx-ui/mcp`
- Optional: `webx-ui/module-blocks` (the block, and the preview of a draft),
  `webx-ui/module-services` (the Services field), `webx-ui/module-pages` (a page at the prefix)

## Install

```bash
composer require webx-ui/module-recipes
php artisan migrate
php artisan webx:blocks:offered --install --module=recipes   # with module-blocks
```

Permissions: `recipes.view`, `recipes.manage`, `recipes.categories.manage` (the categories and
the "rich in" list).

## Addresses

Two types in the registry, **on one level** under one prefix:

| Type              | Example                |
| ----------------- | ---------------------- |
| `recipe`          | `recipes/oat-porridge` |
| `recipe-category` | `recipes/breakfasts`   |

The prefix is `webx-recipes.prefix` (`WEBX_RECIPES_PREFIX`, `recipes` by default) and is **never
empty**: recipes at the root of the site would argue with the pages over every address, so the
package refuses to boot. A clash between a recipe and a category is an error under the slug
(`OnConflict::Fail`). Changing the prefix later:

```bash
php artisan webx:routes:rebuild --type=recipe --type=recipe-category
```

The old addresses stay behind as aliases that redirect.

**The index** is a route at the prefix, `webx.recipes.index`, while `webx-recipes.index` is on.
Switched off (`WEBX_RECIPES_INDEX=false`), the route is not registered and the address is free: a
page of `module-pages` with the slug `recipes` takes it, puts the catalogue on itself as a block,
and becomes the first step of every recipe's trail.

## The page

`recipe.blade.php` is one view of parts, each its own `@include`:

```
recipe/gallery · recipe/heading · recipe/facts · recipe/ingredients · recipe/method ·
recipe/nutrition · recipe/services · recipe/similar
```

Publish the views and keep only the part you rewrite — the rest falls through to the package:

```bash
php artisan vendor:publish --tag=webx-recipes-views
```

What every part is handed is listed in `Rendering\RecipePage`. A field of the project (an
author's note, a call to action) is a patch on `recipes.form` and a line in the part that prints
it: `{{ $recipe->extra('author-note') }}`.

**The catalogue** — `partials/catalog.blade.php` — is one fragment for the index, a category page
and the block. Rewrite it once and all three change.

## Draft

Everything the editor chooses waits in the draft and goes on the site with "Publish" — the text,
and also the categories, the "rich in" list, the services and the similar recipes. There is one
order, the whole list's: a category or a service shows its recipes in it, and nobody drags them
inside one.

## Similar recipes

`webx-recipes.similar` (3) under a recipe. Chosen by hand, they are what is shown — never topped
up. Chosen none, they are picked among the published ones by what they share: a category or a
service counts two, a nutrient one; nothing shared is not similar.

## SEO

The SEO card on the recipe and the category (a patch from `module-seo`), the sitemap, `hreflang`,
the trail, and schema.org `Recipe`: the gallery as `image`, `totalTime`, `recipeYield`,
`recipeCategory`, `nutrition`, the site's organisation as `author` — and `recipeIngredient` and
`recipeInstructions` read out of the documents: every `<li>` is one item, or with no list every
paragraph. So type the ingredients and the steps as lists.

The index and a category page are an `ItemList`. A catalogue narrowed to a nutrient
(`?nutrient=`) is `noindex` with a canonical without the filter; `?page=` stays in the canonical.

Check a recipe on https://validator.schema.org and in the Rich Results Test — Google shows recipes
to any site.

## In a template

```blade
@foreach (recipes()->in('breakfasts')->take(6) as $recipe)
    <a href="{{ $recipe['url'] }}">{{ $recipe['title'] }}</a>
@endforeach

recipes()->nutrients([3])
recipes()->relatedTo('service', $service)
recipes()->except($recipe)->take(3)
```

A card: `id`, `anchor`, `url`, `title`, `lead`, `cover`, `gallery`, `minutes`, `servings`,
`categories` (ids), `nutrients` (`[{id, title}]`), `fields`.

## In a block

`wx-collection` with `source: recipes`, related to services — so a block on a service's page can
show "the recipes of this service". The offered type "Recipes" has two views: a showcase (the
limit of the field, a link to all recipes) and a catalogue (pages, the nutrient filter). Two
catalogues on one page share `?page=` and `?nutrient=`.

## For an agent: MCP

With the panel's MCP server on, the three sections are tools behind `recipes:read` / `recipes:write`
and the categories' scopes:

| Tool                  | What it does                                                             |
| --------------------- | ------------------------------------------------------------------------ |
| `recipes_list`        | Every recipe, or a category, a nutrient, a service, a state — or the bin |
| `recipes_get`         | One recipe in full: the values, the revision, a preview link             |
| `recipes_create`      | A new recipe as a draft, the row and its values in one transaction       |
| `recipes_update`      | The values into the draft, guarded by the revision                       |
| `recipes_publish`     | The draft onto the site · `recipes_unpublish` takes it off               |
| `recipes_delete`      | To the bin, and its address is released                                  |
| `recipes_reorder`     | The one order there is                                                   |
| `recipe_categories_*` | `list`, `create`, `update`, `delete`, `reorder`                          |
| `recipe_nutrients_*`  | the same for "rich in"                                                   |

A recipe is named by its id or its address, a service in `services` and a similar recipe in
`related` the same way. The nutrition is `nutrition.calories` … `nutrition.fiber`, or one object
`nutrition`. The tools that write say that the ingredients and the method are HTML lists — one
`<li>` per ingredient and per step — because the `Recipe` markup reads them. Read
`recipes://catalog` first: the categories with their recipes, drafts included, the recipes in no
category, and the "rich in" list.

## Demo content

`php artisan webx:demo` seeds three categories, five nutrients and six recipes (English and
Russian, as far as the site has them): one in two categories, one a draft, one in English only,
one with similar recipes chosen by hand, two without nutrition, three without a gallery. With
`module-services` two recipes are linked to the demo services; with `module-blocks` and
`module-pages` a page `/recipes-showcase` carries both views of the block. `--remove` takes it back
out; a site that already has recipes is left alone.

## License

MIT
