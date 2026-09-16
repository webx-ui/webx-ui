export { pages, type PagesOptions } from './module'
export { createPagesApi, type PagesApi } from './api'
export { pageEditorKey, providePageEditor, usePageEditor, type PageEditorContext } from './editor'
export { pagesMessages } from './messages'
export { slugify } from './slug'
export { default as WxPagesPage } from './PagesPage.vue'
export { default as WxPageEditorPage } from './PageEditorPage.vue'
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
