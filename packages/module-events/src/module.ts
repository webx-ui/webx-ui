import { categoryRoutes, type AdminModule, type CategoriesOptions } from '@webx-ui/module-admin'
import EventEditorPage from './EventEditorPage.vue'
import EventHistory from './EventHistory.vue'
import EventsPage from './EventsPage.vue'

export interface EventsOptions {
  /** Where the events live inside the panel. The categories sit under it. */
  path?: string
}

/**
 * The categories of events as the panel's shared category screens see them: where they answer,
 * what they are edited on and what this module calls them. Exported so that a panel mounting the
 * screens somewhere of its own does not have to repeat the words.
 */
export function eventCategoriesOptions(path = '/events'): CategoriesOptions {
  return {
    api: 'events/categories',
    path: `${path}/categories`,
    name: 'webx.events.categories',
    module: 'event-categories',
    screen: 'events.category-form',
    manage: 'events.categories.manage',
    count: 'events_count',
    // Every event of the category, past ones included: "what is filed here" is the question.
    items: (id) => ({ path, query: { category: String(id), view: 'all' } }),
    words: {
      new: 'webx-events::category.new',
      empty: 'webx-events::category.empty',
      'empty-help': 'webx-events::category.empty-help',
      order: 'webx-events::category.order',
      hidden: 'webx-events::category.hidden',
      'no-address': 'webx-events::category.no-address',
      count: 'webx-events::category.events',
      'show-items': 'webx-events::category.show-events',
      'delete-blocked': 'webx-events::category.delete-blocked',
      'delete-text': 'webx-events::category.delete-text',
      deleted: 'webx-events::category.deleted',
      saved: 'webx-events::category.saved',
      'field-title': 'webx-events::category.field-title',
      'field-slug': 'webx-events::category.field-slug',
      'address-moving': 'webx-events::category.address-moving',
    },
  }
}

/**
 * The events as sections of the panel: the events and their categories (§4.9).
 *
 * Two modules rather than one, because the navigation is one entry per module; the server puts
 * them in the `events` group, which is what draws them under one heading. A section whose server
 * half is not installed never appears — the entry is built from the manifest.
 */
export function events(options: EventsOptions = {}): AdminModule[] {
  const path = options.path ?? '/events'

  return [
    {
      id: 'events',
      path,
      routes: [
        { path, name: 'webx.events', component: EventsPage, props: { base: path } },
        {
          path: `${path}/:id(\\d+)`,
          name: 'webx.events.edit',
          component: EventEditorPage,
          props: { base: path },
        },
      ],
      /*
       * The one part of `events.form` only this module can draw. Everything else on it is the
       * panel's (`wx-slug`, `wx-categories`, `wx-relations`, `wx-gallery`, `wx-rich-text`,
       * `wx-repeater`, `wx-date-picker`).
       */
      types: {
        'wx-event-history': { component: EventHistory, kind: 'display' },
      },
    },
    {
      id: 'event-categories',
      path: `${path}/categories`,
      routes: categoryRoutes(eventCategoriesOptions(path)),
    },
  ]
}
