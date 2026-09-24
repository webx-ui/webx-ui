---
'@webx-ui/php': minor
---

`webx-ui/module-recipes`, new: recipes as a section of the panel. A recipe is a page of fixed structure — a gallery whose first photo is the cover, ingredients and method as translated documents, nutrition as five translated lines, time and servings — printed by the module's view in parts a site can publish and rewrite one at a time. Flat categories with pages of their own and a "rich in" list (iron, fibre) without, both filed in the draft and published with the text, like the related services and the similar recipes (`webx_relations`). Addresses `{prefix}/{category}` and `{prefix}/{recipe}` on one level under a prefix that is never empty; the index at the prefix can be switched off, and a page of `module-pages` takes the address and the first step of the trail. The catalogue is one fragment for the index, a category page and the offered block "Recipes" (a showcase, or the whole catalogue with pages and a nutrient filter, which is `noindex`). Similar recipes are the ones chosen by hand, or picked by shared categories, services and nutrients in one query. SEO card, sitemap, trail and schema.org `Recipe` with ingredients and steps read out of the lists. `recipes()` for templates, `recipes` as a `wx-collection` source related to services, `recipe` as a relation target.

`webx-ui/module-seo`: the SEO card on the recipe and recipe category forms.

`webx-ui/module-admin`: `webx:setup` offers recipes, and `webx:doctor` checks whose `recipes()` a template calls.
