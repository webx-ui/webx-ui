import { defineAsyncComponent } from 'vue'
import './style.css'

export { catalogFeeds, type CatalogFeedsOptions } from './module'
export { catalogFeedsMessages } from './messages'
export {
  createFeedsApi,
  formValues,
  payloadOf,
  FEEDS_API,
  FORM_FIELDS,
  type CatalogFeedsApi,
} from './api'
export { feedEditorKey, provideFeedEditor, useFeedEditor, type FeedEditorContext } from './editor'
export {
  parseHint,
  renderFile,
  renderItem,
  type FeedParts,
  type FeedVariableParts,
  type ParseHint,
  type RenderedFeed,
} from './preview'
export const WxCatalogFeedsPage = defineAsyncComponent(() => import('./FeedsPage.vue'))
export const WxCatalogFeedEditor = defineAsyncComponent(() => import('./FeedEditorPage.vue'))
export type * from './types'
