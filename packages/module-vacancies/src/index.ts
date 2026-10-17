import { defineAsyncComponent } from 'vue'
export { vacancies, vacancyCategoriesOptions, type VacanciesOptions } from './module'
export { createVacanciesApi, VACANCIES_API, type VacanciesApi } from './api'
export { vacanciesMessages } from './messages'
export {
  provideVacancyEditor,
  vacancyEditorKey,
  useVacancyEditor,
  type VacancyEditorContext,
} from './editor'
export const WxVacanciesPage = defineAsyncComponent(() => import('./VacanciesPage.vue'))
export { default as WxVacancyCreateDialog } from './VacancyCreateDialog.vue'
export const WxVacancyEditorPage = defineAsyncComponent(() => import('./VacancyEditorPage.vue'))
export { default as WxVacancyHistory } from './VacancyHistory.vue'
export type {
  VacanciesList,
  VacancyClosedReason,
  VacancyConflict,
  VacancyDetail,
  VacancyInput,
  VacancyQuery,
  VacancyRow,
  VacancySave,
  VacancyState,
  VacancyStatus,
  VacancyTermRef,
  VacancyVersion,
  VacancyWorkplace,
} from './types'
