import { inject, type ComputedRef, type InjectionKey } from 'vue'
import type { ButtonSize, ButtonType, ButtonVariant } from '../components/Button/types'

/**
 * What a `WxButtonGroup` hands down to the buttons inside it. Every field is
 * optional: a button's own prop always wins, the group only fills the gaps.
 */
export interface ButtonGroupContext {
  type: ComputedRef<ButtonType | undefined>
  variant: ComputedRef<ButtonVariant | undefined>
  size: ComputedRef<ButtonSize | undefined>
  disabled: ComputedRef<boolean>
  vertical: ComputedRef<boolean>
}

export const buttonGroupKey: InjectionKey<ButtonGroupContext> = Symbol('wx-button-group')

export function useButtonGroup(): ButtonGroupContext | null {
  return inject(buttonGroupKey, null)
}
