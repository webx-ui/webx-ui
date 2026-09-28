import { categoryRoutes, type AdminModule, type CategoriesOptions } from '@webx-ui/module-admin'
import TariffsPage from './TariffsPage.vue'

export interface TariffsOptions {
  /** Where the tariffs live inside the panel. The groups sit under it. */
  path?: string
}

/**
 * The groups of the tariffs as the panel's shared category screens see them. Exported so that a
 * panel mounting the screens somewhere of its own does not have to repeat the words.
 *
 * Categories in the code and groups in the words (decision 14): the API and the tables speak the
 * shared code's language, the editor reads "group" — “For individuals”, “For business”. No words
 * about addresses: a group has none (decision 2), and the shared list leaves the address line out
 * when the server says so.
 */
export function tariffGroupsOptions(path = '/tariffs'): CategoriesOptions {
  return {
    api: 'tariffs/categories',
    path: `${path}/groups`,
    name: 'webx.tariffs.groups',
    module: 'tariff-groups',
    screen: 'tariffs.category-form',
    manage: 'tariffs.groups.manage',
    count: 'tariffs_count',
    // The list narrowed to one group is also where its own order is dragged.
    items: (id) => ({ path, query: { category: String(id) } }),
    words: {
      new: 'webx-tariffs::category.new',
      empty: 'webx-tariffs::category.empty',
      'empty-help': 'webx-tariffs::category.empty-help',
      order: 'webx-tariffs::category.order',
      hidden: 'webx-tariffs::category.hidden',
      count: 'webx-tariffs::category.tariffs',
      'show-items': 'webx-tariffs::category.show-tariffs',
      'delete-blocked': 'webx-tariffs::category.delete-blocked',
      'delete-text': 'webx-tariffs::category.delete-text',
      deleted: 'webx-tariffs::category.deleted',
      saved: 'webx-tariffs::category.saved',
      'field-title': 'webx-tariffs::category.field-title',
    },
  }
}

/**
 * The tariffs as sections of the panel: tariffs, and their groups (§5.2).
 *
 * Two modules rather than one, because the navigation is one entry per module; the server puts
 * both in the `tariffs` group, which is what draws them under one heading. The panel includes
 * them as `...tariffs()` — the line `extra.webx.panel.register` of the composer package names. A
 * section whose server half is not installed never appears — the entry is built from the manifest.
 */
export function tariffs(options: TariffsOptions = {}): AdminModule[] {
  const path = options.path ?? '/tariffs'

  return [
    {
      id: 'tariffs',
      path,
      // One route: the open tariff is in the address (`?tariff=`), beside the list it was opened
      // from, so a link to it is a link to both.
      routes: [{ path, name: 'webx.tariffs', component: TariffsPage, props: { base: path } }],
    },
    {
      id: 'tariff-groups',
      path: `${path}/groups`,
      routes: categoryRoutes(tariffGroupsOptions(path)),
    },
  ]
}
