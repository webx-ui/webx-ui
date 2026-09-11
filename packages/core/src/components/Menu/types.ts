import type { MenuMode, MenuSize, MenuValue } from '../../composables/useMenu'

export type { MenuMode, MenuSize, MenuValue }

export interface MenuProps {
  /** A sidebar down the page, or a bar across the top of it. */
  mode?: MenuMode
  /** Row height and text size. */
  size?: MenuSize
  /**
   * The icon rail: labels are hidden and submenus open as flyouts beside the rail.
   * Vertical menus only — there is nothing to collapse in a bar.
   */
  collapsed?: boolean
  /** Only one submenu stays open at a time. */
  accordion?: boolean
  /**
   * Opens the branch the active entry sits in. On by default: a sidebar rendered on
   * a nested route should show where you are without being told.
   */
  autoExpand?: boolean
  /** Accessible name of the menu, e.g. `Main navigation`. */
  label?: string
}

export interface MenuEmits {
  /** An entry was chosen. */
  select: [value: MenuValue, event: MouseEvent]
}
