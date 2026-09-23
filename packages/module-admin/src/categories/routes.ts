import type { RouteRecordRaw } from 'vue-router'
import CategoriesPage from './CategoriesPage.vue'
import CategoryEditorPage from './CategoryEditorPage.vue'
import type { CategoriesOptions } from './types'

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
    { path: options.path, name: options.name, component: CategoriesPage, props: { options } },
    {
      path: `${options.path}/:id(\\d+)`,
      name: `${options.name}.edit`,
      component: CategoryEditorPage,
      props: { options },
    },
  ]
}
