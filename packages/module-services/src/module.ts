import { categoryRoutes, type AdminModule, type CategoriesOptions } from '@webx-ui/module-admin'
import ServiceEditorPage from './ServiceEditorPage.vue'
import ServiceHistory from './ServiceHistory.vue'
import ServicesPage from './ServicesPage.vue'

export interface ServicesOptions {
  /** Where the catalogue lives inside the panel. The categories sit under it. */
  path?: string
}

/**
 * The categories of services as the panel's shared category screens see them: where they answer,
 * what they are edited on and what this module calls them. Exported so that a panel mounting the
 * screens somewhere of its own does not have to repeat the words.
 *
 * Only the words that name the thing are this module's — "services" — and the rest ("Edit",
 * "Cancel", "Leave without saving?") is the panel's, said the same way in every module.
 */
export function serviceCategoriesOptions(path = '/services'): CategoriesOptions {
  return {
    api: 'services/categories',
    path: `${path}/categories`,
    name: 'webx.services.categories',
    module: 'service-categories',
    screen: 'services.category-form',
    manage: 'services.categories.manage',
    count: 'services_count',
    // The list narrowed to one category is also where its own order is dragged.
    items: (id) => ({ path, query: { category: String(id) } }),
    words: {
      new: 'webx-services::category.new',
      empty: 'webx-services::category.empty',
      'empty-help': 'webx-services::category.empty-help',
      order: 'webx-services::category.order',
      hidden: 'webx-services::category.hidden',
      'no-address': 'webx-services::category.no-address',
      count: 'webx-services::category.services',
      'show-items': 'webx-services::category.show-services',
      'delete-blocked': 'webx-services::category.delete-blocked',
      'delete-text': 'webx-services::category.delete-text',
      deleted: 'webx-services::category.deleted',
      saved: 'webx-services::category.saved',
      'field-title': 'webx-services::category.field-title',
      'field-slug': 'webx-services::category.field-slug',
      'address-moving': 'webx-services::category.address-moving',
    },
  }
}

/**
 * The catalogue as sections of the panel: services, and their categories (§4.6).
 *
 * Two modules rather than one, because the navigation is one entry per module; the server puts
 * both in the `services` group, which is what draws them under one heading. A section whose
 * server half is not installed never appears — the entry is built from the manifest.
 */
export function services(options: ServicesOptions = {}): AdminModule[] {
  const path = options.path ?? '/services'

  return [
    {
      id: 'services',
      path,
      routes: [
        { path, name: 'webx.services', component: ServicesPage, props: { base: path } },
        {
          path: `${path}/:id(\\d+)`,
          name: 'webx.services.edit',
          component: ServiceEditorPage,
          props: { base: path },
        },
      ],
      /*
       * The one part of `services.form` only this module can draw. Everything else on it is the
       * panel's (`wx-slug`, `wx-categories`, `wx-media`, `wx-blocks`) — the address field in
       * particular is shared, and reads the prefix from the editor hosting the screen.
       */
      types: {
        'wx-service-history': { component: ServiceHistory, kind: 'display' },
      },
    },
    {
      id: 'service-categories',
      path: `${path}/categories`,
      routes: categoryRoutes(serviceCategoriesOptions(path)),
    },
  ]
}
