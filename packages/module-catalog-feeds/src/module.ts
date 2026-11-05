import type { AdminModule } from '@webx-ui/module-admin'

export interface CatalogFeedsOptions {
  /** Where the catalogue lives inside the panel — the same as `catalog({ path })`. */
  path?: string
}

/**
 * Feeds as a section of the panel (§11 of the feeds spec): «Catalog» → «Feeds» after «Landings»
 * on the server's side (order 304).
 */
export function catalogFeeds(options: CatalogFeedsOptions = {}): AdminModule[] {
  const catalog = options.path ?? '/catalog'

  return [
    {
      id: 'catalog-feeds',
      path: `${catalog}/feeds`,
      routes: [
        {
          path: `${catalog}/feeds`,
          name: 'webx.catalog-feeds',
          component: () => import('./FeedsPage.vue'),
        },
      ],
    },
  ]
}
