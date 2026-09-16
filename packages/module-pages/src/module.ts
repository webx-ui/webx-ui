import type { AdminModule } from '@webx-ui/module-admin'
import PageDanger from './PageDanger.vue'
import PageEditorPage from './PageEditorPage.vue'
import PageHistory from './PageHistory.vue'
import PagePlace from './PagePlace.vue'
import PagesPage from './PagesPage.vue'

export interface PagesOptions {
  /** Where the section lives inside the panel. */
  path?: string
}

/**
 * The site's pages as a section of the panel.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: the section shows up when both halves are installed.
 */
export function pages(options: PagesOptions = {}): AdminModule {
  const path = options.path ?? '/pages'

  return {
    id: 'pages',
    path,
    routes: [
      { path, name: 'webx.pages', component: PagesPage, props: { base: path } },
      {
        path: `${path}/:id(\\d+)`,
        name: 'webx.pages.edit',
        component: PageEditorPage,
        props: { base: path },
      },
    ],
    /*
     * The parts of `pages.form` that are not fields.
     *
     * The editor is a described screen so that a module can add a tab to it with a patch, and
     * the price of that is that everything on it has to be a node type — where the page sits,
     * what may be done to it, what its history is. Each of these draws only, and each reads the
     * page from the editor above it rather than from the description.
     */
    types: {
      'wx-page-place': { component: PagePlace, kind: 'display' },
      'wx-page-danger': { component: PageDanger, kind: 'display' },
      'wx-page-history': { component: PageHistory, kind: 'display' },
    },
  }
}
