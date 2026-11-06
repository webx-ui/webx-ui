import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { CollectionDetail, CollectionPreset } from './types'

/**
 * What the collection's form shares with the fields of its screen: the set reads the base from the
 * values, the preset writes them, the list reads the names the server sent with the ids, the
 * preview asks with all of it.
 */
export interface CollectionEditorContext {
  /** As the server last answered; `null` for a collection not created yet. */
  collection: Ref<CollectionDetail | null>
  values: Ref<ScreenModel>
  locked: Ref<boolean>
  /** The presets of the site, written by the set when it asks for its facets. */
  presets: Ref<CollectionPreset[]>
}

export const collectionEditorKey: InjectionKey<CollectionEditorContext> =
  Symbol('wx-catalog-collection')

export function provideCollectionEditor(context: CollectionEditorContext): void {
  provide(collectionEditorKey, context)
}

/** `null` outside the collection's page: a field put on another screen keeps working on its own. */
export function useCollectionEditor(): CollectionEditorContext | null {
  return inject(collectionEditorKey, null)
}
