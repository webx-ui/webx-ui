import { defineAsyncComponent } from 'vue'
export { faq, faqCategoriesOptions, type FaqOptions } from './module'
export { createFaqApi, FAQ_API, type FaqApi } from './api'
export { faqMessages } from './messages'
export {
  provideQuestionEditor,
  questionEditorKey,
  useQuestionEditor,
  type QuestionEditorContext,
} from './editor'
export const WxFaqQuestionsPage = defineAsyncComponent(() => import('./QuestionsPage.vue'))
export { default as WxFaqQuestionPane } from './QuestionPane.vue'
export { default as WxFaqAnchor } from './AnchorField.vue'
export type {
  QuestionCategoryRef,
  QuestionDetail,
  QuestionQuery,
  QuestionRow,
  QuestionsList,
} from './types'
