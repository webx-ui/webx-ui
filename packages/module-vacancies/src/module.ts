import { categoryRoutes, type AdminModule, type CategoriesOptions } from '@webx-ui/module-admin'
import VacanciesPage from './VacanciesPage.vue'
import VacancyEditorPage from './VacancyEditorPage.vue'
import VacancyHistory from './VacancyHistory.vue'

export interface VacanciesOptions {
  /** Where the vacancies live inside the panel. The categories sit under it. */
  path?: string
}

/**
 * The categories of vacancies as the panel's shared category screens see them. Exported so that a
 * panel mounting the screens somewhere of its own does not have to repeat the words.
 *
 * A category here is a group and a filter, not a page (decision 2): its slug is the key of
 * `?category=` and of `vacancies()->in()`, and the list says "no page of its own" where the other
 * modules print an address.
 */
export function vacancyCategoriesOptions(path = '/vacancies'): CategoriesOptions {
  return {
    api: 'vacancies/categories',
    path: `${path}/categories`,
    name: 'webx.vacancies.categories',
    module: 'vacancy-categories',
    screen: 'vacancies.category-form',
    manage: 'vacancies.categories.manage',
    count: 'vacancies_count',
    // Every vacancy of the category, closed ones included: "what is filed here" is the question.
    items: (id) => ({ path, query: { category: String(id), view: 'all' } }),
    words: {
      new: 'webx-vacancies::category.new',
      empty: 'webx-vacancies::category.empty',
      'empty-help': 'webx-vacancies::category.empty-help',
      order: 'webx-vacancies::category.order',
      hidden: 'webx-vacancies::category.hidden',
      'no-address': 'webx-vacancies::category.no-page',
      count: 'webx-vacancies::category.vacancies',
      'show-items': 'webx-vacancies::category.show-vacancies',
      'delete-blocked': 'webx-vacancies::category.delete-blocked',
      'delete-text': 'webx-vacancies::category.delete-text',
      deleted: 'webx-vacancies::category.deleted',
      saved: 'webx-vacancies::category.saved',
      'field-title': 'webx-vacancies::category.field-title',
      'field-slug': 'webx-vacancies::category.field-slug',
    },
  }
}

/**
 * The vacancies as sections of the panel: the vacancies and their categories (§4.10).
 *
 * Two modules rather than one, because the navigation is one entry per module; the server puts
 * them in the `vacancies` group, which is what draws them under one heading. A section whose
 * server half is not installed never appears — the entry is built from the manifest.
 */
export function vacancies(options: VacanciesOptions = {}): AdminModule[] {
  const path = options.path ?? '/vacancies'

  return [
    {
      id: 'vacancies',
      path,
      routes: [
        { path, name: 'webx.vacancies', component: VacanciesPage, props: { base: path } },
        {
          path: `${path}/:id(\\d+)`,
          name: 'webx.vacancies.edit',
          component: VacancyEditorPage,
          props: { base: path },
        },
      ],
      /*
       * The one part of `vacancies.form` only this module can draw. Everything else on it is the
       * panel's (`wx-slug`, `wx-categories`, `wx-relations`, `wx-rich-text`, `wx-repeater`,
       * `wx-date-picker`, `wx-checkbox-group`).
       */
      types: {
        'wx-vacancy-history': { component: VacancyHistory, kind: 'display' },
      },
    },
    {
      id: 'vacancy-categories',
      path: `${path}/categories`,
      routes: categoryRoutes(vacancyCategoriesOptions(path)),
    },
  ]
}
