# Recipes

`@webx-ui/module-recipes` is recipes as three sections of the panel, and `webx-ui/module-recipes`
on the server is what they edit and what prints them. This page is both, because neither is useful
alone.

A recipe is a gallery, a lead, the ingredients, the method, the nutrition, the time and the
servings. Unlike a service or an article, its page is **not made of blocks**: it has a fixed
structure printed by the module's view, and a site changes how it looks by publishing that view,
one part at a time. The address is the registry of [`webx-ui/routing`](/guide/routing), the draft
and the history are `module-admin`, what a recipe says about itself is [`module-seo`](/guide/seo),
the photos are [`module-media`](/guide/media). The categories are the panel's shared
[categories](/guide/categories) — twice: the categories themselves, and what a recipe is **rich
in** («iron», «fibre»), a second list without addresses. The services a recipe leads to and the
similar recipes are [relations](/guide/relations).

## Install

```bash
pnpm add @webx-ui/module-recipes
composer require webx-ui/module-recipes
php artisan migrate
php artisan webx:blocks:offered --install --module=recipes
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { recipes } from '@webx-ui/module-recipes'
import '@webx-ui/module-recipes/style.css'

createAdmin({
  modules: [...recipes()],
})
```

`recipes()` returns three modules — **Recipes**, **Categories** and **Rich in** — which arrive
under one heading because the server puts all three in the `recipes` group.

The last command installs the block type the module offers — **Recipes**, slug `recipes`; it needs
[`module-blocks`](/guide/blocks), which is optional. Without it there is no block and no preview of
a draft; everything else works. Without [`module-services`](/guide/services) there is no Services
field and no services on a recipe's page. Without [`module-pages`](/guide/pages) nothing can stand
at the prefix in place of the index.

Permissions: `recipes.view` opens the list, `recipes.manage` writes (the order too), and
`recipes.categories.manage` covers the categories and the «rich in» list.

## Addresses

A recipe and a category live **on one level**, under one prefix:

| Type              | Address                                |
| ----------------- | -------------------------------------- |
| `recipe`          | `/recipes/oatmeal-with-berries`        |
| `recipe-category` | `/recipes/breakfast`                   |
| the index         | `/recipes`, route `webx.recipes.index` |

A recipe in three categories has one address. Their slugs share the level: a category and a recipe
that want the same slug are refused, the second one under its own field.

`webx-recipes.prefix` (`WEBX_RECIPES_PREFIX`) is the prefix, and **it is never empty**. Services
may live at the root of a site; recipes may not — a flat list of recipes beside the tree of pages
would argue with it over every address, so the package refuses to boot and says so. Changing the
prefix later:

```bash
php artisan webx:routes:rebuild --type=recipe --type=recipe-category
```

The old addresses stay behind as aliases that answer with a 301.

### The index, or a page in its place

The index is the whole catalogue, 24 to a page (`webx-recipes.per-page`), with a filter by what
the recipes are rich in. A site that wants more around it — an introduction, a showcase of the
season, a form — switches the index off and puts a page there:

1. `WEBX_RECIPES_INDEX=false` — the route is not registered, and the address `/recipes` is free;
2. **Pages** → a new page with the slug `recipes`;
3. **Content** → whatever the page needs, and the block **Recipes** in the view **Catalogue**;
4. publish.

The page takes the address, and the first step of every recipe's and every category's breadcrumbs
becomes that page, named the way it names itself. While nothing is there — or only a draft — the
trail has no such step.

## The catalogue: one fragment, three places

The index, a category page and the block print the same fragment,
`partials/catalog.blade.php`: a grid of cards, the filter by nutrients, and the pages. The filter
and the pages are **links**, not buttons — without a script they work, and a search engine reads
the pages the way a reader clicks through them. Rewrite the fragment once and all three change.

The **Recipes** block has two views:

| View       | What it shows                                                                                    |
| ---------- | ------------------------------------------------------------------------------------------------ |
| `showcase` | the recipes the collection field picks, up to its limit, and a link to all of them               |
| `catalog`  | every recipe the field picks, `per_page` to a page (empty — the index's number), with the filter |

The field is a [collection](/guide/collections) of the `recipes` source: categories, a limit, and
«only related to» a service. «All recipes» in the showcase points at whatever stands at the prefix —
the index, or the page in its place — and is not drawn while nothing does. An untouched block is a
showcase: the block stores only what the editor changed.

**Two catalogues on one page share `?page=` and `?nutrient=`.** Paging one pages the other. Giving
each block its own parameters would complicate every link for a page nobody builds; put one
catalogue on a page and showcases around it.

A filtered page (`?nutrient=3`) is `noindex`, with a canonical without the filter; `?page=2` keeps
its own canonical. A page past the last one answers 404 on the index and a category, and draws an
empty block elsewhere.

## A recipe's page

`recipe.blade.php` is one view of parts, each its own `@include`, in this order:

| Part                 | What it prints                                                          |
| -------------------- | ----------------------------------------------------------------------- |
| `recipe/gallery`     | the first picture large, the rest as a strip; one picture — no strip    |
| `recipe/heading`     | the title and the lead                                                  |
| `recipe/facts`       | the time (`45 min`, `1 h 15 min`), the servings, categories, nutrients  |
| `recipe/ingredients` | the ingredients as the editor wrote them                                |
| `recipe/method`      | the method                                                              |
| `recipe/nutrition`   | a table of what is written in this language; nothing written — no table |
| `recipe/services`    | cards of the visible related services, with `module-services`           |
| `recipe/similar`     | the similar recipes                                                     |

### What to publish for your own markup

```bash
php artisan vendor:publish --tag=webx-recipes-views
```

copies every view into `resources/views/vendor/webx-recipes`. **Keep only what you rewrite and
delete the rest**: a part that is not there falls through to the package's, and gets its fixes with
every update. To change how the facts look, keep `recipe/facts.blade.php`; to change the order of
the parts, keep `recipe.blade.php`; to change every grid of recipes on the site, keep
`partials/catalog.blade.php` (and `partials/card.blade.php` for the card alone).

A part is markup over plain data — no part asks the database for itself. What each is handed:

| Variable                  | What it is                                                                                     |
| ------------------------- | ---------------------------------------------------------------------------------------------- |
| `$recipe`                 | the model — for `extra()` and anything else a site's part wants                                |
| `$pictures`               | the gallery, resolved; the first is the cover                                                  |
| `$title`, `$lead`         | in the language of the page                                                                    |
| `$minutes`, `$servings`   | numbers, or null                                                                               |
| `$categories`             | `[{ title, url }]` — visible, with an address in this language                                 |
| `$nutrients`              | `[{ id, title }]` — visible                                                                    |
| `$ingredients`, `$method` | HTML as stored — the field type cleaned it on the way in                                       |
| `$nutrition`              | `[key => text]` — `calories`, `protein`, `fat`, `carbohydrates`, `fiber`, only what is written |
| `$services`, `$similar`   | cards, as `services()` and `recipes()` give them                                               |

The views stand in the site's layout the way every module's do: `webx-recipes.layout` names the
component, the same seam as the [blog's](/guide/blog#the-layout).

### Similar recipes

Under a recipe, up to `webx-recipes.similar` (3):

- **chosen by hand** in the field **Similar recipes** — these, the visible ones, in the order
  chosen, and **never topped up**: two chosen is two shown;
- **none chosen** — picked among the visible recipes by what they share with this one: a category
  counts 2, a service 2, a nutrient 1; nothing shared is not similar; a tie goes by the order of
  the list.

The pick is one query, with the counting done by the database — not a loop over every recipe.

## The Recipe markup, and how to check it

A recipe describes itself as a schema.org `Recipe`:

| Property              | From                                                                    |
| --------------------- | ----------------------------------------------------------------------- |
| `name`, `description` | the title, the lead                                                     |
| `image`               | every picture of the gallery                                            |
| `totalTime`           | `PT45M`, `PT1H15M`                                                      |
| `recipeYield`         | the servings                                                            |
| `recipeCategory`      | the main category — the first one                                       |
| `recipeIngredient`    | every `<li>` of the ingredients as text; no list — every paragraph      |
| `recipeInstructions`  | a `HowToStep` for every `<li>` of the method; no list — every paragraph |
| `nutrition`           | `NutritionInformation` from what is written; nothing — no `nutrition`   |
| `author`              | the site's `Organization` from the SEO settings, by `@id`               |

**So type the ingredients and the steps as lists.** An editor who writes the ingredients as one
paragraph gives the markup one ingredient; the hint under both fields says so, and so do the
descriptions of the MCP tools. The index and a category page are an `ItemList`, the trail is
`BreadcrumbList`.

Check a recipe in both:

- https://validator.schema.org — is the markup valid;
- the [Rich Results Test](https://search.google.com/test/rich-results) — is it a recipe to Google.

Unlike the [FAQ](/guide/faq#the-markup-and-its-flag), recipes are a rich result Google shows for any site, so the
Rich Results Test should list **Recipes** with no errors. «Missing field `video`» and
«`aggregateRating`» are warnings, not errors: the module has neither.

## Services and recipes

The field **Services** on a recipe is a [relation](/guide/relations) to the services of
`module-services`. It works both ways without a second field:

- on the recipe's page, `recipe/services` prints the chosen services, the visible ones, in the
  order chosen;
- on a service's page, a **Recipes** block with «only related to» pointing at that service shows
  its recipes. With the switch **«The record of the page it stands on»** one block in the service
  layout does it for every service.

In a template:

```blade
@foreach (recipes()->relatedTo('service', $service)->take(3) as $recipe)
    <a href="{{ $recipe['url'] }}">{{ $recipe['title'] }}</a>
@endforeach
```

Like everything else the editor chooses, a service linked or unlinked reaches the site with
**Publish**, not with the save.

## In a template: `recipes()`

```blade
@foreach (recipes()->in('breakfast')->nutrients([3])->take(6) as $recipe)
    <a href="{{ $recipe['url'] }}">
        @if ($recipe['cover'])
            <img src="{{ $recipe['cover']['url'] }}" alt="">
        @endif
        {{ $recipe['title'] }} · {{ $recipe['minutes'] }} min
    </a>
@endforeach
```

| Step                      | What it does                                                 |
| ------------------------- | ------------------------------------------------------------ |
| `in($categories)`         | An id, a slug, a category or a list; null or empty — all     |
| `nutrients($ids)`         | Only the ones rich in these                                  |
| `relatedTo('service', …)` | Only the ones related to these services                      |
| `only([12, 7])`           | These and no others, in this order                           |
| `except($recipe)`         | «Other recipes» on a recipe page                             |
| `take(6)`                 | At most six; null or zero — all                              |
| `locale('uk')`            | The language of the cards; by default the one being rendered |
| `get()`, `first()`        | A list of cards, or one; the query can be looped and counted |

A card is `id`, `anchor` (the slug), `categories` (ids), `title`, `url`, `lead`, `cover`,
`gallery`, `minutes`, `servings`, `nutrients` (`[{ id, title }]`) and `fields` — the project's own
fields by name. Always in the one order recipes have: a category or a service shows its recipes in
the order of the whole list. The block template's autocomplete knows these keys.

## Fields of the project

A food blog wants «The author's note» beside the method, a clinic a call to action to book a
consultation. Neither is a column of the package: the site lays a patch over the screen, and
whatever the screen draws that the model has no column for is kept in `extra`.

`recipes.form` and `recipes.category-form` keep an empty card with the public id `project-fields`.
`resources/screens/recipes.form.json` in the site:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": {
      "id": "author-note",
      "type": "wx-textarea",
      "name": "author-note",
      "label": "Author's note",
      "localized": true,
      "props": { "rows": 3, "maxlength": 1000 }
    }
  }
]
```

and in a service provider of the site:

```php
use WebxUi\Admin\Facades\Screens;

public function boot(): void
{
    Screens::extend('recipes.form', resource_path('screens/recipes.form.json'));
}
```

The field appears on the **Settings** tab, is checked by its type on every save — the panel's and
an agent's — and waits in the draft with the rest. Printing it is the one part you publish:
`resources/views/vendor/webx-recipes/recipe/method.blade.php`, with the package's markup and one
more paragraph:

```blade
@if ($note = $recipe->extra('author-note'))
    <aside class="wx-recipe__note">{{ $note }}</aside>
@endif
```

`extra()` reads it in the language of the page, because the field is localized. On a card it is
`$recipe['fields']['author-note']`.

## The panel

**Recipes** is every recipe on one screen, without pages, in the one order the site shows them. A
row is the cover, the title with the address, the categories as chips, the time, the state and the
row menu. Filters: a category, a nutrient, a service (with `module-services`), a state, words.
**The list can be dragged only while nothing narrows it**: recipes have no order inside a
category, and the line under the filters says why the grips are gone.

Four states, as for services: **draft**, **published**, **published with edits**, **unpublished**.

**The editor** is the screen `recipes.form`, four tabs. **Recipe** — the gallery (the first picture
is the cover; drag another one first to change it), the ingredients and the method, the five
nutrition fields. **Settings** — the title, the address with the prefix in front, the lead with a
counter, the time in minutes, the servings, the categories, what it is rich in, the services, the
similar recipes («empty — they are picked by themselves»), the project's card. **SEO** and
**History** as for services. Everything — the categories and the links included — waits in the
draft and goes on the site with **Publish**. A save over somebody else's is refused with a `409`.

**Categories** — the name, the address, whether it is on the site, the introduction, the cover, the
SEO card, the project's card. **Rich in** — the name, whether it is shown, the project's card; no
address, no SEO. Either refuses to go into the bin while recipes are in it.

```
GET    /api/cms/recipes                 q, category, nutrient, service, status, trashed — no pages
POST   /api/cms/recipes                 { title, slug? }
GET    /api/cms/recipes/{id}            values, the revision, the prefix, a preview link
PUT    /api/cms/recipes/{id}            the draft: { values, revision }
POST   /api/cms/recipes/{id}/discard    · /publish · /unpublish · /restore
DELETE /api/cms/recipes/{id}            to the bin
GET    /api/cms/recipes/{id}/versions   · POST /versions/{n}/restore
POST   /api/cms/recipes/reorder         { ids } — the whole list only

GET    /api/cms/recipes/categories      the shared categories API
GET    /api/cms/recipes/nutrients       the same, for «rich in»
```

## For an agent: MCP

With the panel's MCP server on (see [AI agents](/guide/agents)), the three sections are tools too:

| Tool                  | What it does                                                                    |
| --------------------- | ------------------------------------------------------------------------------- |
| `recipes_list`        | Every recipe, or a category, a nutrient, a service, a state, words — or the bin |
| `recipes_get`         | One recipe in full: the values, the revision, a preview link                    |
| `recipes_create`      | A new recipe as a draft at the end of the list, in one transaction              |
| `recipes_update`      | The values into the draft, guarded by the revision                              |
| `recipes_publish`     | The draft onto the site · `recipes_unpublish` takes it off                      |
| `recipes_delete`      | To the bin, and its address is released                                         |
| `recipes_reorder`     | The one order there is                                                          |
| `recipe_categories_*` | `list`, `create`, `update`, `delete`, `reorder`                                 |
| `recipe_nutrients_*`  | the same for «rich in» — named by id or title, no address                       |

A recipe is named by its id or its address (`"/recipes/oatmeal-with-berries"`), a category by its id
or slug. A plain string in a translated field is the default language. The nutrition is five fields
with literal dotted names — `nutrition.calories` is one name — or, for convenience, one object
`{ "nutrition": { "calories": "380 kcal" } }`. The services and the similar recipes are
`services` and `related` in the values: lists of ids or addresses (`"/services/nutrition-plan"`),
first to last; an empty `related` lets the site pick. Both tools that write say, in their
description, that the ingredients and the method are HTML lists — one `<li>` per ingredient and
per step — because that is what the markup reads.

Before writing, an agent reads **`recipes://catalog`**: every category in its order with its
recipes, hidden categories and drafts included, the recipes in no category at the end, and what
recipes can be rich in. Each recipe has its address and `written_in` — the languages it has a title
in.

## Demo content

`php artisan webx:demo` seeds three categories, five nutrients and six recipes in the two languages
of the demo, as far as the site has them. Each recipe past the first shows one rule: one is in two
categories, one is a draft, one is written in English only and so has no Russian address, one has
its similar recipes chosen by hand, two have no nutrition, three have no gallery. The pictures are the two
the library's demo seeded.

What else is installed decides the rest:

- with `module-services` — two recipes are linked to the demo services;
- with `module-blocks` and `module-pages` — a page `/recipes-showcase` with both views of the block:
  a showcase of three breakfasts, and the whole catalogue four to a page below it.

`--remove` takes all of it back out. Recipes, categories or nutrients that already exist leave the
demo alone.

## Config

`config/webx-recipes.php`:

| Key           | Default   | What it is                                                    |
| ------------- | --------- | ------------------------------------------------------------- |
| `prefix`      | `recipes` | The first segment of every address; never empty               |
| `index`       | `true`    | The package answers the prefix with its own catalogue         |
| `per-page`    | `24`      | Recipes on a page of the index and of a category              |
| `similar`     | `3`       | The most similar recipes under a recipe                       |
| `views.*`     |           | The views the index, a category and a recipe are printed with |
| `layout`      |           | The Blade component those views stand in                      |
| `breadcrumbs` | `true`    | The package views print the visible trail                     |
| `middleware`  |           | What the index route runs through                             |
