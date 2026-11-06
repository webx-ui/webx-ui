import { defineComponent, h, type Component } from 'vue'
import type { RouteRecordRaw } from 'vue-router'
import CategoriesPage from './CategoriesPage.vue'
import CategoryEditorPage from './CategoryEditorPage.vue'
import type { CategoriesOptions } from './types'

/**
 * The page, as a component of this module's own.
 *
 * Two modules' categories are the same two pages with different options, and `RouterView`
 * patches a component that stays the same rather than mounting it anew: from the blog's rubrics
 * to the services' categories the list kept its instance, took the new title from the props and
 * went on showing the rubrics it had loaded — everything else was read once, in `setup`. A
 * component per call is a different type for each module, so the switch is a fresh mount.
 */
function bound(page: Component, options: CategoriesOptions): Component {
  return defineComponent({
    name: 'WxCategoriesView',
    setup: () => () => h(page, { options }),
  })
}

/**
 * The key of a page with no tree: its route without `webx.`, and a route of one word gets
 * `.index` (WEBX_UI_PANEL_HELP.md §2.3). From the name and not from `options.module`: that one is
 * the module's id, which says `rubrics` where the route says `blog.rubrics`, and the groups of
 * properties share it with the list of properties, whose text they would have opened.
 */
function listHelpKey(name: string): string {
  const key = name.replace(/^webx\./, '')

  return key.includes('.') ? key : `${key}.index`
}

/**
 * The two screens of a module's categories — the list and the page of one — as routes to put
 * into the module's section:
 *
 *     { id: 'rubrics', path: '/blog/rubrics', routes: categoryRoutes({ api: 'blog/rubrics', … }) }
 *
 * One description for both, so the list links to the editor and the editor back to the list
 * without either being told the other's address.
 */
export function categoryRoutes(options: CategoriesOptions): RouteRecordRaw[] {
  return [
    {
      path: options.path,
      name: options.name,
      component: bound(CategoriesPage, options),
      meta: { help: listHelpKey(options.name) },
    },
    {
      path: `${options.path}/:id(\\d+)`,
      name: `${options.name}.edit`,
      component: bound(CategoryEditorPage, options),
      meta: { help: options.screen },
    },
  ]
}
