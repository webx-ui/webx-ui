import { inject, type InjectionKey } from 'vue'
import type { LightboxItem } from '../components/Lightbox/types'

/** One picture in a group: where it is on the page, and what it opens as. */
export interface ImageGroupEntry {
  el: () => Element | null
  item: () => LightboxItem
}

export interface ImageGroupContext {
  /** Joins the group; the answer leaves it. */
  register: (entry: ImageGroupEntry) => () => void
  /** Opens the group's lightbox on this picture. */
  open: (entry: ImageGroupEntry) => void
}

export const imageGroupKey: InjectionKey<ImageGroupContext> = Symbol('wx-image-group')

/** The `WxImageGroup` around this component, if there is one. */
export function useImageGroup() {
  return inject(imageGroupKey, null)
}
