import { inject, type ComputedRef, type InjectionKey } from 'vue'

export type MenuMode = 'vertical' | 'horizontal'
export type MenuSize = 'sm' | 'md'

/** What identifies an entry: whatever you would put in a route table. */
export type MenuValue = string | number

/** What `WxMenu` hands down to everything inside it. */
export interface MenuContext {
  mode: ComputedRef<MenuMode>
  size: ComputedRef<MenuSize>
  /** The icon rail: labels hidden, submenus opening as flyouts. */
  collapsed: ComputedRef<boolean>
  /** The entry that is currently selected. */
  active: ComputedRef<MenuValue | undefined>
  /** The submenus the active entry sits in, outermost first. */
  trail: ComputedRef<MenuValue[]>
  select: (value: MenuValue, event: MouseEvent) => void
  isOpen: (value: MenuValue) => boolean
  setOpen: (value: MenuValue, open: boolean, ancestors: MenuValue[]) => void
  /**
   * Bumped when every open flyout should close — an entry inside one was chosen.
   * A panel cannot close on any click of its own: the branches inside it are opened
   * by clicking too, and that would shut the panel before they could unfold.
   */
  closeSignal: ComputedRef<number>
  closeFlyouts: () => void
  /**
   * An entry says where it sits so a submenu can tell it holds the active one —
   * the highlight on a collapsed branch, and what `autoExpand` follows.
   */
  register: (value: MenuValue, ancestors: MenuValue[]) => void
  unregister: (value: MenuValue) => void
}

/** What a `WxSubmenu` hands down to the entries it holds. */
export interface SubmenuContext {
  /** The submenus above this one, outermost first, including itself. */
  ancestors: MenuValue[]
  /** How deep the entries below sit — what the indent is counted from. */
  depth: number
  /**
   * Whether these entries are already inside a flyout panel. A panel is a fresh
   * surface: the depth restarts at its edge, and the branches in it open inline
   * rather than throwing a second panel beside the first.
   */
  inFlyout: boolean
}

export const menuKey: InjectionKey<MenuContext> = Symbol('wx-menu')
export const submenuKey: InjectionKey<SubmenuContext> = Symbol('wx-submenu')

export function useMenu(): MenuContext | null {
  return inject(menuKey, null)
}

export function useSubmenu(): SubmenuContext {
  return inject(submenuKey, { ancestors: [], depth: 0, inFlyout: false })
}
