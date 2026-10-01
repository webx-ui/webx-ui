import type { AdminModule } from '@webx-ui/module-admin'
import SearchIndexPage from './SearchIndexPage.vue'

export interface CatalogManticoreOptions {
  /** Where the page lives inside the panel. */
  path?: string
}

/**
 * «System → Search index» (decision 27 of the Manticore spec): the server, each language's table
 * against the database, the queue, and the rebuild as a job with its progress.
 *
 * The server reports the section only when the catalogue runs on Manticore, so a panel on the
 * database engine has no such entry even with this module listed. The notice over the list of
 * products, when the panel answers from the database, is the catalogue's own and needs nothing here.
 */
export function catalogManticore(options: CatalogManticoreOptions = {}): AdminModule[] {
  const path = options.path ?? '/search-index'

  return [
    {
      id: 'search-index',
      path,
      routes: [{ path, name: 'webx.search-index', component: SearchIndexPage }],
    },
  ]
}
