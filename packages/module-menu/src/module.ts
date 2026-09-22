import type { AdminModule } from '@webx-ui/module-admin'
import MenusPage from './MenusPage.vue'

export interface MenuOptions {
  /** Where the section lives inside the panel. */
  path?: string
}

/**
 * The menus of the site as a section of the panel.
 *
 * One route and no editor under it: a menu is arranged in place, and an item is a dialog over
 * the tree it belongs to. Which menu is open travels in the address instead, so a link to the
 * footer is a link somebody can send.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: the section shows up when both halves are installed.
 */
export function menu(options: MenuOptions = {}): AdminModule {
  const path = options.path ?? '/menus'

  return {
    id: 'menu',
    path,
    routes: [{ path, name: 'webx.menus', component: MenusPage }],
  }
}
