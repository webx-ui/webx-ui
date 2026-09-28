export { vacancies, vacancyCategoriesOptions, type VacanciesOptions } from './module'
export { createVacanciesApi, VACANCIES_API, type VacanciesApi } from './api'
export { vacanciesMessages } from './messages'
export {
  provideVacancyEditor,
  vacancyEditorKey,
  useVacancyEditor,
  type VacancyEditorContext,
} from './editor'
export { default as WxVacanciesPage } from './VacanciesPage.vue'
export { default as WxVacancyCreateDialog } from './VacancyCreateDialog.vue'
export { default as WxVacancyEditorPage } from './VacancyEditorPage.vue'
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
