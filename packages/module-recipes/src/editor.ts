import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { RecipeRow } from './types'

/**
 * What the editor knows and the nodes of `recipes.form` do not: the recipe being edited, and a
 * way to read it again. The history needs both — its list is keyed by the recipe, and a restored
 * version has to reach the fields the form is bound to.
 */
export interface RecipeEditorContext {
  /** The recipe as the server last answered it, or `null` while the first request is out. */
  recipe: Ref<RecipeRow | null>
  /** Whether this administrator may write at all. */
  canManage: boolean
  /** Ask the server for the recipe again — after a restore, a publication, a discard. */
  reload(): Promise<void>
}

export const recipeEditorKey: InjectionKey<RecipeEditorContext> = Symbol('wx-recipe-editor')

export function provideRecipeEditor(editor: RecipeEditorContext): void {
  provide(recipeEditorKey, editor)
}

/** The editor above this node, or `null` outside one — a demo or a test draws nothing. */
export function useRecipeEditor(): RecipeEditorContext | null {
  return inject(recipeEditorKey, null)
}
