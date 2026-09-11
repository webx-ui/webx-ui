import { inject, type ComputedRef, type InjectionKey } from 'vue'
import type { ActionSize } from '../components/Action/types'

/** What a `WxActions` row hands down to the actions inside it. */
export interface ActionsContext {
  size: ComputedRef<ActionSize | undefined>
}

export const actionsKey: InjectionKey<ActionsContext> = Symbol('wx-actions')

export function useActions(): ActionsContext | null {
  return inject(actionsKey, null)
}
