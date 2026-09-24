import { categoryRoutes, type AdminModule, type CategoriesOptions } from '@webx-ui/module-admin'
import AnchorField from './AnchorField.vue'
import QuestionsPage from './QuestionsPage.vue'

export interface FaqOptions {
  /** Where the questions live inside the panel. The categories sit under it. */
  path?: string
}

/**
 * The categories of the FAQ as the panel's shared category screens see them. Exported so that a
 * panel mounting the screens somewhere of its own does not have to repeat the words.
 *
 * No words about addresses: a category here has none (decision 2) — it is a button of the filter
 * on the site, and the shared list leaves the address line out when the server says so.
 */
export function faqCategoriesOptions(path = '/faq'): CategoriesOptions {
  return {
    api: 'faq/categories',
    path: `${path}/categories`,
    name: 'webx.faq.categories',
    module: 'faq-categories',
    screen: 'faq.category-form',
    manage: 'faq.categories.manage',
    count: 'questions_count',
    // The list narrowed to one category is also where its own order is dragged.
    items: (id) => ({ path, query: { category: String(id) } }),
    words: {
      new: 'webx-faq::category.new',
      empty: 'webx-faq::category.empty',
      'empty-help': 'webx-faq::category.empty-help',
      order: 'webx-faq::category.order',
      hidden: 'webx-faq::category.hidden',
      count: 'webx-faq::category.questions',
      'show-items': 'webx-faq::category.show-questions',
      'delete-blocked': 'webx-faq::category.delete-blocked',
      'delete-text': 'webx-faq::category.delete-text',
      deleted: 'webx-faq::category.deleted',
      saved: 'webx-faq::category.saved',
      'field-title': 'webx-faq::category.field-title',
    },
  }
}

/**
 * The FAQ as sections of the panel: questions, and their categories (§4.5).
 *
 * Two modules rather than one, because the navigation is one entry per module; the server puts
 * both in the `faq` group, which is what draws them under one heading. A section whose server
 * half is not installed never appears — the entry is built from the manifest.
 */
export function faq(options: FaqOptions = {}): AdminModule[] {
  const path = options.path ?? '/faq'

  return [
    {
      id: 'faq',
      path,
      // One route: the open question is in the address (`?question=`), beside the list it was
      // opened from, so a link to it is a link to both.
      routes: [{ path, name: 'webx.faq', component: QuestionsPage, props: { base: path } }],
      /*
       * The one node of `faq.form` only this module can draw. A field in the screen's eyes — it
       * gets the label and the help of its node — but without a name, so nothing is bound.
       */
      types: {
        'wx-faq-anchor': { component: AnchorField, kind: 'field' },
      },
    },
    {
      id: 'faq-categories',
      path: `${path}/categories`,
      routes: categoryRoutes(faqCategoriesOptions(path)),
    },
  ]
}
