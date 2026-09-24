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
    { path: options.path, name: options.name, component: bound(CategoriesPage, options) },
    {
      path: `${options.path}/:id(\\d+)`,
      name: `${options.name}.edit`,
      component: bound(CategoryEditorPage, options),
    },
  ]
}
