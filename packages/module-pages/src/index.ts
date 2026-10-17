import { defineAsyncComponent } from 'vue'
export { pages, type PagesOptions } from './module'
export { createPagesApi, type PagesApi } from './api'
export { pageEditorKey, providePageEditor, usePageEditor, type PageEditorContext } from './editor'
export { pagesMessages } from './messages'
export { slugify } from './slug'
export const WxPagesPage = defineAsyncComponent(() => import('./PagesPage.vue'))
export const WxPageEditorPage = defineAsyncComponent(() => import('./PageEditorPage.vue'))
export { default as WxPagePicker } from './PagePicker.vue'
export { default as WxPagePlace } from './PagePlace.vue'
export { default as WxPageDanger } from './PageDanger.vue'
export { default as WxPageHistory } from './PageHistory.vue'
export type {
  PageCapabilities,
  PageConflict,
  PageDetail,
  PageDropZone,
  PageInput,
  PageLevel,
  PageMoveResult,
  PageQuery,
  PageRow,
  PageSave,
  PageStatus,
  PageVersion,
} from './types'
