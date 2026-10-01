import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { LandingDetail } from './types'

/**
 * What the landing's form shares with the fields of its screen: the set builder reads the base
 * from the values, the address reads the set's suggestion, the recommended products read the
 * names the server sent with the ids.
 */
export interface LandingEditorContext {
  /** As the server last answered; `null` for a landing not created yet. */
  landing: Ref<LandingDetail | null>
  values: Ref<ScreenModel>
  locked: Ref<boolean>
  /** The slugs the set suggests, per language — written by the set builder after each count. */
  suggested: Ref<Record<string, string>>
  /** Where the landings' list is: `/catalog/landings`. */
  list: string
}

export const landingEditorKey: InjectionKey<LandingEditorContext> = Symbol('wx-catalog-landing')

export function provideLandingEditor(context: LandingEditorContext): void {
  provide(landingEditorKey, context)
}

/** `null` outside the landing's page — a field put on another screen keeps working on its own. */
export function useLandingEditor(): LandingEditorContext | null {
  return inject(landingEditorKey, null)
}
