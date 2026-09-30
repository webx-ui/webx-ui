import { defineComponent, h, type Component } from 'vue'
import type { RouteRecordRaw } from 'vue-router'
import {
  categoryRoutes,
  type AdminModule,
  type CategoriesOptions,
  type CategoryWord,
} from '@webx-ui/module-admin'
import { GROUPS_API } from './api'
import CategoryPropertiesField from './CategoryPropertiesField.vue'
import { NAMESPACE, useCatalogPropertiesMessages } from './i18n'
import IntervalsField from './IntervalsField.vue'
import NumberExample from './NumberExample.vue'
import ProductPropertiesField from './ProductPropertiesField.vue'
import PropertiesPage from './PropertiesPage.vue'
import PropertyEditorPage from './PropertyEditorPage.vue'
import TypeField from './TypeField.vue'
import ValuesField from './ValuesField.vue'

export interface CatalogPropertiesOptions {
  /** Where the catalogue lives inside the panel — the same as `catalog({ path })`. */
  path?: string
}

/** The words of the shared category screens the groups say in their own way. */
const GROUP_WORDS: CategoryWord[] = [
  'new',
  'empty',
  'empty-help',
  'order',
  'hidden',
  'count',
  'delete-blocked',
  'delete-text',
  'deleted',
  'saved',
]

/**
 * The groups of the card — «Screen», «Processor» — as the panel's shared category screens see
 * them: a flat list in the order of the card, and the page of one, which is the server's
 * `catalog.property-group-form`. Not an entry of the navigation: they are reached from the head
 * of the properties and lead back there.
 */
export function propertyGroupsOptions(options: CatalogPropertiesOptions = {}): CategoriesOptions {
  const catalog = options.path ?? '/catalog'

  return {
    api: GROUPS_API,
    path: `${catalog}/properties/groups`,
    name: 'webx.catalog-properties.groups',
    module: 'catalog-properties',
    title: `${NAMESPACE}::panel.groups-title`,
    back: { path: `${catalog}/properties`, label: `${NAMESPACE}::module.title` },
    screen: 'catalog.property-group-form',
    manage: 'catalog.manage',
    count: 'properties_count',
    words: Object.fromEntries(GROUP_WORDS.map((word) => [word, `${NAMESPACE}::group.${word}`])),
  }
}

/**
 * The shared screens run nothing of this package's before they draw, so the route puts its
 * English under the panel's dictionary first — as the catalogue's reference books do.
 */
function seeded(route: RouteRecordRaw): RouteRecordRaw {
  const page = (route as { component?: Component }).component

  if (page === undefined) return route

  return {
    ...route,
    component: defineComponent({
      name: 'WxCatalogPropertyGroupsView',
      setup() {
        useCatalogPropertiesMessages()

        return () => h(page)
      },
    }),
  } as RouteRecordRaw
}

/**
 * The properties of products as a section of the panel (§7 of the properties spec): the list,
 * the page of one property with its values and intervals, and their groups behind a button.
 * «Catalog» → «Dictionaries» → «Properties» on the server's side (order 312).
 *
 * The node types are the rest of it: the tab «Properties» of a category (its set) and the tab
 * «Specifications» of a product — both patched onto the catalogue's screens by the server — and
 * the parts of the property's own screen that a form field cannot be.
 */
export function catalogProperties(options: CatalogPropertiesOptions = {}): AdminModule[] {
  const catalog = options.path ?? '/catalog'
  const props = { base: catalog }

  return [
    {
      id: 'catalog-properties',
      path: `${catalog}/properties`,
      routes: [
        {
          path: `${catalog}/properties`,
          name: 'webx.catalog-properties',
          component: PropertiesPage,
          props,
        },
        {
          path: `${catalog}/properties/:id(\\d+)`,
          name: 'webx.catalog-properties.edit',
          component: PropertyEditorPage,
          props,
        },
        ...categoryRoutes(propertyGroupsOptions(options)).map(seeded),
      ],
      types: {
        'wx-catalog-property-type': { component: TypeField, kind: 'field' },
        'wx-catalog-property-example': { component: NumberExample, kind: 'display' },
        'wx-catalog-property-values': { component: ValuesField, kind: 'display' },
        'wx-catalog-property-intervals': { component: IntervalsField, kind: 'display' },
        'wx-catalog-category-properties': { component: CategoryPropertiesField, kind: 'display' },
        // Wide: a group of the card is a column of fields, and half a form is too narrow for a
        // combobox of values beside its label.
        'wx-catalog-product-properties': {
          component: ProductPropertiesField,
          kind: 'field',
          wide: true,
        },
      },
    },
  ]
}
