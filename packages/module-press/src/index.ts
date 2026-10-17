import { defineAsyncComponent } from 'vue'
export { press, type PressOptions } from './module'
export { createPressApi, PRESS_API, type PressApi } from './api'
export { pressMessages } from './messages'
export const WxPressPage = defineAsyncComponent(() => import('./PressPage.vue'))
export { default as WxOutletPane } from './OutletPane.vue'
export type {
  ArticleValues,
  OutletDetail,
  OutletQuery,
  OutletRow,
  OutletsList,
  OutletSummary,
} from './types'
