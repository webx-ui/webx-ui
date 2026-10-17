import { defineAsyncComponent } from 'vue'
export { snippets, type SnippetsOptions } from './module'
export { createSnippetsApi, SNIPPETS_API, type SnippetsApi } from './api'
export { snippetsMessages } from './messages'
export { coverageOf, localesOf } from './summary'
export const WxSnippetsPage = defineAsyncComponent(() => import('./SnippetsPage.vue'))
export { default as WxSnippetPane } from './SnippetPane.vue'
export type { SnippetConsent, SnippetDetail, SnippetPlace, SnippetRow, SnippetsList } from './types'
