import type { AdminModule } from '@webx-ui/module-admin'
import LandingEditorPage from './LandingEditorPage.vue'
import LandingProductsField from './LandingProductsField.vue'
import LandingSetField from './LandingSetField.vue'
import LandingSlugField from './LandingSlugField.vue'
import LandingSortField from './LandingSortField.vue'
import LandingsPage from './LandingsPage.vue'

export interface CatalogLandingsOptions {
  /** Where the catalogue lives inside the panel — the same as `catalog({ path })`. */
  path?: string
}

/**
 * Landings as a section of the panel (§8 of the landings spec): the list with its filters and
 * «Create in bulk», and the page of one — new at `…/landings/new` — which is the server's
 * `catalog.landing-form`. «Catalog» → «Landings» after «Brands» on the server's side (order 303).
 *
 * The node types are the parts of that form a plain field cannot be: the set builder with its
 * live count, the address with the set's suggestion, the default sort out of the catalogue's
 * registry, and the recommended products found and put in order.
 */
export function catalogLandings(options: CatalogLandingsOptions = {}): AdminModule[] {
  const catalog = options.path ?? '/catalog'
  const props = { base: catalog }

  return [
    {
      id: 'catalog-landings',
      path: `${catalog}/landings`,
      routes: [
        {
          path: `${catalog}/landings`,
          name: 'webx.catalog-landings',
          component: LandingsPage,
          props,
        },
        {
          path: `${catalog}/landings/new`,
          name: 'webx.catalog-landings.new',
          component: LandingEditorPage,
          props,
        },
        {
          path: `${catalog}/landings/:id(\\d+)`,
          name: 'webx.catalog-landings.edit',
          component: LandingEditorPage,
          props,
        },
      ],
      types: {
        // Wide: a facet's row is a label, a list of values and a button, and half a form is too
        // narrow for a combobox of brands beside its label.
        'wx-catalog-landing-set': { component: LandingSetField, kind: 'field', wide: true },
        'wx-catalog-landing-slug': { component: LandingSlugField, kind: 'field' },
        'wx-catalog-landing-sort': { component: LandingSortField, kind: 'field' },
        'wx-catalog-landing-products': {
          component: LandingProductsField,
          kind: 'field',
          wide: true,
        },
      },
    },
  ]
}
