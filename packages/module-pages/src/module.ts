import type { AdminModule } from '@webx-ui/module-admin'
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
    routes: [{ path, name: 'webx.pages', component: PagesPage, props: { base: path } }],
  }
}
