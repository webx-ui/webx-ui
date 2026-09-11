import { inject, type InjectionKey } from 'vue'
import type { DescriptionsContext } from './types'

export const descriptionsKey: InjectionKey<DescriptionsContext> = Symbol('wx-descriptions')

/** A pair on its own, outside any list, still has to render something sensible. */
export function useDescriptions(): DescriptionsContext {
  return inject(descriptionsKey, { bordered: false, layout: 'horizontal', labelWidth: undefined })
}
