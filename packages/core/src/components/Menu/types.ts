import type { MenuMode, MenuSize, MenuValue } from '../../composables/useMenu'

export type { MenuMode, MenuSize, MenuValue }

/**
 * What a bar does with entries it has no room for: move them into a branch at the
 * end of it, or let the bar scroll sideways. Vertical menus ignore this — a column
 * has as much room as it needs.
 */
export type MenuOverflow = 'menu' | 'scroll'

export interface MenuProps {
  /** A sidebar down the page, or a bar across the top of it. */
  mode?: MenuMode
  /** Row height and text size. */
  size?: MenuSize
  /** What a bar does with the entries that do not fit across it. */
  overflow?: MenuOverflow
  /** Title of the branch those entries move into. */
  overflowTitle?: string
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
