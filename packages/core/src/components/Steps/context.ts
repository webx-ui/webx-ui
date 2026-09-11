import { inject, type InjectionKey } from 'vue'
import type { StepsContext } from './types'

export const stepsKey: InjectionKey<StepsContext> = Symbol('wx-steps')

/** A step outside a sequence is one step, on its own, and still renders. */
export function useSteps(): StepsContext {
  return inject(stepsKey, {
    register: () => {},
    unregister: () => {},
    indexOf: () => 0,
    stateOf: () => 'current',
    choose: () => {},
    direction: 'horizontal',
    size: 'md',
    clickable: false,
    total: 1,
  })
}
