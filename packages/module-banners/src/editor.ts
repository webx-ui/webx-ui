import { inject, provide, type ComputedRef, type InjectionKey, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { PlaceRow } from './types'

/**
 * What the banner's page tells the fields of its screen that need more than their own value: the
 * layer editor draws the banner's picture, puts in its title and text, and lays out its buttons,
 * in the frame of the place the banner stands in.
 */
export interface BannerEditorContext {
  values: Ref<ScreenModel>
  place: ComputedRef<PlaceRow | null>
}

const key: InjectionKey<BannerEditorContext> = Symbol('wx-banner-editor')

export function provideBannerEditor(context: BannerEditorContext): void {
  provide(key, context)
}

export function useBannerEditor(): BannerEditorContext | null {
  return inject(key, null)
}
