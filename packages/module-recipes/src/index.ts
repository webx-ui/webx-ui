import { defineAsyncComponent } from 'vue'
export {
  recipes,
  recipeCategoriesOptions,
  recipeNutrientsOptions,
  type RecipesOptions,
} from './module'
export { createRecipesApi, RECIPES_API, type RecipesApi } from './api'
export { recipesMessages } from './messages'
export { formatMinutes } from './minutes'
export {
  provideRecipeEditor,
  recipeEditorKey,
  useRecipeEditor,
  type RecipeEditorContext,
} from './editor'
export const WxRecipesPage = defineAsyncComponent(() => import('./RecipesPage.vue'))
export { default as WxRecipeCreateDialog } from './RecipeCreateDialog.vue'
export const WxRecipeEditorPage = defineAsyncComponent(() => import('./RecipeEditorPage.vue'))
export { default as WxRecipeHistory } from './RecipeHistory.vue'
export type {
  RecipeConflict,
  RecipeDetail,
  RecipeInput,
  RecipeQuery,
  RecipeRow,
  RecipeSave,
  RecipesList,
  RecipeStatus,
  RecipeTermRef,
  RecipeVersion,
} from './types'
