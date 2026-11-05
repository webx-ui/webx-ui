import { defineAsyncComponent } from 'vue'
import './style.css'

export { catalogFeeds, type CatalogFeedsOptions } from './module'
export { catalogFeedsMessages } from './messages'
export { createFeedsApi, FEEDS_API, type CatalogFeedsApi } from './api'
export const WxCatalogFeedsPage = defineAsyncComponent(() => import('./FeedsPage.vue'))
export type { FeedBuildState, FeedRow, FeedsPage } from './types'
