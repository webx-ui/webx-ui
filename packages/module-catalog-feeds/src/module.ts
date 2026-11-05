import type { AdminModule } from '@webx-ui/module-admin'
import FeedAddressField from './FeedAddressField.vue'
import FeedBuildsField from './FeedBuildsField.vue'
import FeedCategoriesField from './FeedCategoriesField.vue'
import FeedExclusionsField from './FeedExclusionsField.vue'
import FeedFilterField from './FeedFilterField.vue'
import FeedLabelsField from './FeedLabelsField.vue'
import FeedLocaleField from './FeedLocaleField.vue'
import FeedStockMapField from './FeedStockMapField.vue'
import FeedTemplateField from './FeedTemplateField.vue'

export interface CatalogFeedsOptions {
  /** Where the catalogue lives inside the panel — the same as `catalog({ path })`. */
  path?: string
}

/**
 * Feeds as a section of the panel (§11 of the feeds spec): «Catalog» → «Feeds» after «Landings» on
 * the server's side (order 304), the list and the page of one, which is `catalog.feed-form`. A new
 * feed is the same page at `…/feeds/new?preset=…`: its first save creates it.
 *
 * The node types are the parts of the form a plain field cannot be. Those without a `name` edit
 * several keys of the feed, or none of it, through the page's context (`editor.ts`).
 */
export function catalogFeeds(options: CatalogFeedsOptions = {}): AdminModule[] {
  const catalog = options.path ?? '/catalog'
  const props = { base: catalog }

  return [
    {
      id: 'catalog-feeds',
      path: `${catalog}/feeds`,
      routes: [
        {
          path: `${catalog}/feeds`,
          name: 'webx.catalog-feeds',
          component: () => import('./FeedsPage.vue'),
          props,
        },
        {
          path: `${catalog}/feeds/new`,
          name: 'webx.catalog-feeds.new',
          component: () => import('./FeedEditorPage.vue'),
          props,
        },
        {
          path: `${catalog}/feeds/:id(\\d+)`,
          name: 'webx.catalog-feeds.edit',
          component: () => import('./FeedEditorPage.vue'),
          props,
        },
      ],
      types: {
        'wx-catalog-feed-locale': { component: FeedLocaleField, kind: 'field' },
        'wx-catalog-feed-address': { component: FeedAddressField, kind: 'display' },
        'wx-catalog-feed-template': { component: FeedTemplateField, kind: 'display' },
        'wx-catalog-feed-filter': { component: FeedFilterField, kind: 'field', wide: true },
        'wx-catalog-feed-exclusions': { component: FeedExclusionsField, kind: 'display' },
        'wx-catalog-feed-stock-map': { component: FeedStockMapField, kind: 'field', wide: true },
        'wx-catalog-feed-categories': { component: FeedCategoriesField, kind: 'display' },
        'wx-catalog-feed-labels': { component: FeedLabelsField, kind: 'display' },
        'wx-catalog-feed-builds': { component: FeedBuildsField, kind: 'display' },
      },
    },
  ]
}
