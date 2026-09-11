import { inject, type InjectionKey } from 'vue'

/** What a `WxDropdown` hands down, so an item inside it can close the panel. */
export interface DropdownContext {
  close: () => void
  /** Whether a click on an item closes the panel — the dropdown's `closeOnClick`. */
  closeOnSelect: () => boolean
}

export const dropdownKey: InjectionKey<DropdownContext> = Symbol('wx-dropdown')

export function useDropdown(): DropdownContext | null {
  return inject(dropdownKey, null)
}
