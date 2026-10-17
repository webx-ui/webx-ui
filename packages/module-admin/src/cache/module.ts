import type { AdminModule } from '../types'
import CachePage from './CachePage.vue'

export interface CacheOptions {
  /** Where the page lives inside the panel. */
  path?: string
}

/**
 * «System → Cache» (WEBX_UI_CACHE.md §5): what the site remembers between requests, by group,
 * and «Reset the site cache». The server half is the frame itself, so a panel lists this beside
 * its sections; the entry shows only to an administrator with `cache.view` or `cache.reset`.
 */
export function cache(options: CacheOptions = {}): AdminModule {
  const path = options.path ?? '/cache'

  return {
    id: 'cache',
    path,
    routes: [{ path, name: 'webx.cache', component: CachePage }],
  }
}
