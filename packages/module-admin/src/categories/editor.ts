import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { CategoryRow } from './types'

/**
 * What the category editor knows and the nodes of its screen do not — the same seam the article
 * editor has. `wx-category-slug` (`wx-slug`) prints the whole address, and the prefix of the module is
 * configuration the editor was handed with the record, not something a description can carry.
 */
export interface CategoryEditorContext {
  /** The category as the server last answered it, or `null` while the first request is out. */
  category: Ref<CategoryRow | null>
  /** The values of the screen as they are right now, edits included. */
  values: Ref<ScreenModel>
  /** Where the module's addresses start; `null` for categories without an address. */
  prefix: Ref<string | null>
  /** "The address is changing" — in the module's words. */
  moving: () => string
}

export const categoryEditorKey: InjectionKey<CategoryEditorContext> = Symbol('wx-category-editor')

export function provideCategoryEditor(editor: CategoryEditorContext): void {
  provide(categoryEditorKey, editor)
}

/** The editor above this node, or `null` outside one — a demo, a test, a stray screen. */
export function useCategoryEditor(): CategoryEditorContext | null {
  return inject(categoryEditorKey, null)
}
