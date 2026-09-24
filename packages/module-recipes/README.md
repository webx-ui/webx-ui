# @webx-ui/module-recipes

The front end of the recipes section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: recipes — a gallery, ingredients, a method, nutrition, time and servings — the categories
they are filed under, and what they are rich in.

The other half is the Composer package `webx-ui/module-recipes`, which owns the recipes, their
pages and addresses, the `Recipe` markup, the Recipes block, `recipes()` for templates and the
API. A section appears in the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-recipes
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { recipes } from '@webx-ui/module-recipes'
import '@webx-ui/module-recipes/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...recipes()],
}).mount()
```

`recipes()` answers with three sections — the recipes, their categories and "Rich in" — because
the panel draws one entry per module. The server puts all three in the `recipes` group.
`recipes({ path: '/cookbook' })` puts them somewhere else inside the panel.

## What is here

- **The list** — every recipe at once, in the one order the site shows them. Recipes have no order
  inside a category or a service, so the list can be dragged only while nothing narrows it; with a
  filter on, the line under it says why the grips are gone.
- **The editor** — the described screen `recipes.form` with the tabs Recipe · Settings · SEO ·
  History, autosaved into a draft and guarded by a revision. The categories, the nutrients, the
  services and the similar recipes wait in the draft with the text and reach the site on
  "Publish". A project adds its own fields with a patch into the `project-fields` card.
- **Categories and "Rich in"** — the panel's shared category screens (`categoryRoutes`), with
  `recipeCategoriesOptions()` and `recipeNutrientsOptions()` exported for a panel that mounts them
  elsewhere.

The words are English by default and come from the server in the panel's language
(`webx-recipes::`).
