import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { PropertyInterval, PropertyRow } from './types'

/**
 * What the property's editor knows and the nodes of `catalog.property-form` do not: which property
 * it is — the values and the intervals are records of their own, saved by requests of their own —
 * and what the form says right now, which the type field and the live example read.
 */
export interface PropertyEditorContext {
  /** The property as the server last answered it, or `null` while the first request is out. */
  property: Ref<PropertyRow | null>
  /** The values of the screen as they are right now, edits included. */
  values: Ref<ScreenModel>
  /** The intervals as the server last answered them; the node keeps its own edits. */
  intervals: Ref<PropertyInterval[]>
  /** Closed for writing: no permission, or a save in flight. */
  locked: Ref<boolean>
}

export const propertyEditorKey: InjectionKey<PropertyEditorContext> = Symbol('wx-catalog-property')

export function providePropertyEditor(editor: PropertyEditorContext): void {
  provide(propertyEditorKey, editor)
}

/** The editor above this node, or `null` outside one — a demo, a test, a stray screen. */
export function usePropertyEditor(): PropertyEditorContext | null {
  return inject(propertyEditorKey, null)
}
