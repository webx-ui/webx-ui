import { categoryRoutes, type AdminModule, type CategoriesOptions } from '@webx-ui/module-admin'
import RecipeEditorPage from './RecipeEditorPage.vue'
import RecipeHistory from './RecipeHistory.vue'
import RecipesPage from './RecipesPage.vue'

export interface RecipesOptions {
  /** Where the recipes live inside the panel. The categories and the nutrients sit under it. */
  path?: string
}

/**
 * The categories of recipes as the panel's shared category screens see them: where they answer,
 * what they are edited on and what this module calls them. Exported so that a panel mounting the
 * screens somewhere of its own does not have to repeat the words.
 */
export function recipeCategoriesOptions(path = '/recipes'): CategoriesOptions {
  return {
    api: 'recipes/categories',
    path: `${path}/categories`,
    name: 'webx.recipes.categories',
    module: 'recipe-categories',
    screen: 'recipes.category-form',
    manage: 'recipes.categories.manage',
    count: 'recipes_count',
    // A category has no order of its own inside it (decision 4): the link shows what is in it.
    items: (id) => ({ path, query: { category: String(id) } }),
    words: {
      new: 'webx-recipes::category.new',
      empty: 'webx-recipes::category.empty',
      'empty-help': 'webx-recipes::category.empty-help',
      order: 'webx-recipes::category.order',
      hidden: 'webx-recipes::category.hidden',
      'no-address': 'webx-recipes::category.no-address',
      count: 'webx-recipes::category.recipes',
      'show-items': 'webx-recipes::category.show-recipes',
      'delete-blocked': 'webx-recipes::category.delete-blocked',
      'delete-text': 'webx-recipes::category.delete-text',
      deleted: 'webx-recipes::category.deleted',
      saved: 'webx-recipes::category.saved',
      'field-title': 'webx-recipes::category.field-title',
      'field-slug': 'webx-recipes::category.field-slug',
      'address-moving': 'webx-recipes::category.address-moving',
    },
  }
}

/**
 * What a recipe is rich in — the second kind of category a recipe has (decision 8), with no
 * address: the shared list leaves the address line out when the server says there is none, as it
 * does for the categories of the FAQ.
 */
export function recipeNutrientsOptions(path = '/recipes'): CategoriesOptions {
  return {
    api: 'recipes/nutrients',
    path: `${path}/nutrients`,
    name: 'webx.recipes.nutrients',
    module: 'recipe-nutrients',
    screen: 'recipes.nutrient-form',
    manage: 'recipes.categories.manage',
    count: 'recipes_count',
    items: (id) => ({ path, query: { nutrient: String(id) } }),
    words: {
      new: 'webx-recipes::nutrient.new',
      empty: 'webx-recipes::nutrient.empty',
      'empty-help': 'webx-recipes::nutrient.empty-help',
      order: 'webx-recipes::nutrient.order',
      hidden: 'webx-recipes::nutrient.hidden',
      count: 'webx-recipes::nutrient.recipes',
      'show-items': 'webx-recipes::nutrient.show-recipes',
      'delete-blocked': 'webx-recipes::nutrient.delete-blocked',
      'delete-text': 'webx-recipes::nutrient.delete-text',
      deleted: 'webx-recipes::nutrient.deleted',
      saved: 'webx-recipes::nutrient.saved',
      'field-title': 'webx-recipes::nutrient.field-title',
    },
  }
}

/**
 * The recipes as sections of the panel: recipes, their categories and what they are rich in
 * (§5.9).
 *
 * Three modules rather than one, because the navigation is one entry per module; the server puts
 * them in the `recipes` group, which is what draws them under one heading. A section whose server
 * half is not installed never appears — the entry is built from the manifest.
 */
export function recipes(options: RecipesOptions = {}): AdminModule[] {
  const path = options.path ?? '/recipes'

  return [
    {
      id: 'recipes',
      path,
      routes: [
        { path, name: 'webx.recipes', component: RecipesPage, props: { base: path } },
        {
          path: `${path}/:id(\\d+)`,
          name: 'webx.recipes.edit',
          component: RecipeEditorPage,
          props: { base: path },
        },
      ],
      /*
       * The one part of `recipes.form` only this module can draw. Everything else on it is the
       * panel's (`wx-slug`, `wx-categories`, `wx-relations`, `wx-gallery`, `wx-rich-text`).
       */
      types: {
        'wx-recipe-history': { component: RecipeHistory, kind: 'display' },
      },
    },
    {
      id: 'recipe-categories',
      path: `${path}/categories`,
      routes: categoryRoutes(recipeCategoriesOptions(path)),
    },
    {
      id: 'recipe-nutrients',
      path: `${path}/nutrients`,
      routes: categoryRoutes(recipeNutrientsOptions(path)),
    },
  ]
}
