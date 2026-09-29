import type { AdminModule } from '@webx-ui/module-admin'
import CategoriesPage from './CategoriesPage.vue'
import CategoryEditorPage from './CategoryEditorPage.vue'
import CategoryField from './CategoryField.vue'
import DeletedPage from './DeletedPage.vue'
import FacetsField from './FacetsField.vue'
import GalleryField from './GalleryField.vue'
import ProductEditorPage from './ProductEditorPage.vue'
import ProductsPage from './ProductsPage.vue'

export interface CatalogOptions {
  /**
   * Where the catalogue lives inside the panel. The server's refusal of a taken article number
   * links to `{path}/products/{id}` under the panel's own path, so a panel that moves it has to
   * move the server's idea of it too — leave it unless there is a reason.
   */
  path?: string
}

/**
 * The catalogue as a section of the panel (§11): products, their categories as a tree, and
 * «Deleted».
 *
 * One section and not three, because the server registers one module — `catalog` — and the
 * navigation draws one entry per module. The products are where it opens; the tree and «Deleted»
 * are reached from their head. The satellites (brands, stock, labels) stand beside it in the
 * `catalog` group as sections of their own.
 *
 * The three node types are what only this module can draw on its two screens: a category picker
 * that knows the tree, the «Filters» tab of a category (§6.2), and the gallery.
 */
export function catalog(options: CatalogOptions = {}): AdminModule {
  const path = options.path ?? '/catalog'
  const props = { base: path }

  return {
    id: 'catalog',
    path,
    routes: [
      { path, redirect: `${path}/products` },
      { path: `${path}/products`, name: 'webx.catalog.products', component: ProductsPage, props },
      {
        path: `${path}/products/:id(\\d+)`,
        name: 'webx.catalog.products.edit',
        component: ProductEditorPage,
        props,
      },
      {
        path: `${path}/categories`,
        name: 'webx.catalog.categories',
        component: CategoriesPage,
        props,
      },
      {
        path: `${path}/categories/:id(\\d+)`,
        name: 'webx.catalog.categories.edit',
        component: CategoryEditorPage,
        props,
      },
      { path: `${path}/deleted`, name: 'webx.catalog.deleted', component: DeletedPage, props },
    ],
    types: {
      'wx-catalog-category': { component: CategoryField, kind: 'field' },
      // Wide: a row of the list carries a name, a switch and a grip, and half a line is too narrow
      // to read which filter is which.
      'wx-catalog-facets': { component: FacetsField, kind: 'field', wide: true },
      'wx-catalog-gallery': { component: GalleryField, kind: 'display' },
    },
  }
}
