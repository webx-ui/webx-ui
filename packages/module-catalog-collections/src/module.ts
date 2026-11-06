import type { AdminModule } from '@webx-ui/module-admin'
import CollectionField from './CollectionField.vue'
import CollectionKeyField from './CollectionKeyField.vue'
import CollectionPresetField from './CollectionPresetField.vue'
import CollectionPreviewPanel from './CollectionPreviewPanel.vue'
import CollectionProductsField from './CollectionProductsField.vue'
import CollectionSetField from './CollectionSetField.vue'
import CollectionSortField from './CollectionSortField.vue'

export interface CatalogCollectionsOptions {
  /** Where the catalogue lives inside the panel — the same as `catalog({ path })`. */
  path?: string
}

/**
 * Collections as a section of the panel (§6 of the collections spec): the list, and the page of one
 * — new at `…/collections/new` — which is the server's `catalog.collection-form`. «Catalog» →
 * «Product collections» below «Landings» on the server's side (order 305).
 *
 * The node types are the parts of that form a plain field cannot be, and `wx-catalog-collection`,
 * the field of the block `product-collection`, which any screen may use.
 */
export function catalogCollections(options: CatalogCollectionsOptions = {}): AdminModule[] {
  const catalog = options.path ?? '/catalog'
  const props = { base: catalog }

  return [
    {
      id: 'catalog-collections',
      path: `${catalog}/collections`,
      routes: [
        {
          path: `${catalog}/collections`,
          name: 'webx.catalog-collections',
          component: () => import('./CollectionsPage.vue'),
          props,
        },
        {
          path: `${catalog}/collections/new`,
          name: 'webx.catalog-collections.new',
          component: () => import('./CollectionEditorPage.vue'),
          props,
        },
        {
          path: `${catalog}/collections/:id(\\d+)`,
          name: 'webx.catalog-collections.edit',
          component: () => import('./CollectionEditorPage.vue'),
          props,
        },
      ],
      types: {
        'wx-catalog-collection': { component: CollectionField, kind: 'field' },
        'wx-catalog-collection-key': { component: CollectionKeyField, kind: 'field' },
        'wx-catalog-collection-preset': { component: CollectionPresetField, kind: 'display' },
        'wx-catalog-collection-preview': { component: CollectionPreviewPanel, kind: 'display' },
        // Wide: a facet's row is a label, a list of values and a button.
        'wx-catalog-collection-set': { component: CollectionSetField, kind: 'field', wide: true },
        'wx-catalog-collection-sort': { component: CollectionSortField, kind: 'field' },
        'wx-catalog-collection-products': {
          component: CollectionProductsField,
          kind: 'field',
          wide: true,
        },
      },
    },
  ]
}
